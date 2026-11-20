<?php

namespace Tests\Feature;

use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\PaymentEngine;
use App\Actions\Payments\PaymentLinkAdmission;
use App\Actions\Payments\PaymentLinkService;
use App\Actions\Payments\PaymentStore;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\FakePaymentLinkAdmission;
use Tests\Support\LedgerHttpHarness;
use Tests\Support\LedgerStoreFixture;
use Tests\Support\MockPaymentJourney;
use Tests\Support\PaymentLinkHttpHarness;
use Tests\Support\PaymentLinkStoreFixture;
use Tests\TestCase;

class FunctionalPaymentLinkCheckoutTest extends TestCase
{
    use MockPaymentJourney;

    private FakePaymentLinkAdmission $linkAdmission;

    private FakeLedgerAdmission $ledgerAdmission;

    private PaymentLinkHttpHarness $checkout;

    private LedgerHttpHarness $ledgerHttp;

    private function ready(): array
    {
        [$owner, $merchant] = $this->eligible();
        LedgerStoreFixture::install($this->paymentPdo);
        PaymentLinkStoreFixture::install($this->paymentPdo);
        $this->paymentAdmission->allow = true;
        $this->linkAdmission = new FakePaymentLinkAdmission;
        $this->linkAdmission->actor = $owner->id;
        $this->linkAdmission->merchant = $merchant->id;
        $this->linkAdmission->allow = $this->linkAdmission->customers = true;
        $this->ledgerAdmission = new FakeLedgerAdmission;
        $this->ledgerAdmission->actor = $owner->id;
        $this->ledgerAdmission->merchant = $merchant->id;
        $this->ledgerAdmission->allow = true;
        $store = new PaymentStore($this->paymentPdo);
        $ledger = new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission);
        $this->checkout = new PaymentLinkHttpHarness(new PaymentLinkService($this->payments->engine, $store, $ledger, $this->linkAdmission));
        $this->checkout->register();
        $this->ledgerHttp = new LedgerHttpHarness($ledger);
        $this->ledgerHttp->register();
        app('router')->getRoutes()->refreshNameLookups();

        return [$owner->fresh(), $merchant->fresh()];
    }

    private function merchantUrl(Merchant $merchant): string
    {
        return '/merchants/'.$merchant->id.'/__isolated-links';
    }

    private function customerUrl(array $link): string
    {
        return '/__isolated-checkout/'.$link['public_token'];
    }

    private function syncSession(): void
    {
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
    }

    private function createLink(Merchant $merchant, array $changes = []): TestResponse
    {
        $this->syncSession();

        return $this->postJson($this->merchantUrl($merchant), ['_token' => session()->token(), 'amount_tzs' => 12500, 'currency' => 'TZS', 'description' => 'Fictional order 1048', 'request_key' => 'fictional-link-order-0001', ...$changes]);
    }

    private function customer(?string $session = null): void
    {
        auth()->logout();
        session()->invalidate();
        if ($session !== null) {
            session()->setId($session);
        }
        session()->regenerateToken();
        $this->syncSession();
        $this->assertGuest();
    }

    private function start(array $link, array $changes = []): TestResponse
    {
        $this->syncSession();

        return $this->postJson($this->customerUrl($link), ['_token' => session()->token(), 'request_nonce' => session('checkout.nonces.'.$link['public_token']), ...$changes]);
    }

    private function postAction(Merchant $merchant, string $path): TestResponse
    {
        $this->syncSession();

        return $this->postJson($this->merchantUrl($merchant).$path, ['_token' => session()->token()]);
    }

    private function countRows(string $table): int
    {
        return (int) $this->paymentPdo->query('SELECT count(*) FROM '.$table)->fetchColumn();
    }

    private function useStore(PaymentStore $store): void
    {
        $this->payments->engine = new PaymentEngine($store, $this->provider, $this->paymentAdmission);
        $ledger = new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission);
        $this->checkout->links = new PaymentLinkService($this->payments->engine, $store, $ledger, $this->linkAdmission);
        $this->ledgerHttp->ledger = $ledger;
    }

    public function test_same_merchant_real_p1_to_p6_guest_checkout_and_p5_accounting_journey(): void
    {
        [$owner, $merchant] = $this->ready();
        $merchantBefore = $merchant->fresh()->getAttributes();
        $this->get($this->merchantUrl($merchant))->assertOk()->assertSee('Payment links')->assertSee('Amount in whole TZS')->assertSee('xlipa-checkout.css');
        $link = $this->createLink($merchant)->assertOk()->assertJsonPath('amount_tzs', 12500)->json();
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/D', $link['public_token']);
        $this->assertSame($merchant->business_name, $link['business_name']);
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk()->assertSee($merchant->business_name)->assertSee('12,500')->assertSee('Start mock payment')->assertDontSee('Fictional payment confirmed');
        $result = $this->start($link)->assertOk()->assertJsonPath('status', 'pending')->json();
        $this->assertGuest();
        $this->assertNull(session('auth_version'));
        $this->assertSame(1, $this->provider->creates);
        $payment = $result['payment_reference'];
        $attempt = $result['attempt_id'];
        $association = $this->paymentPdo->query('SELECT link_id,payment_reference FROM checkout_attempts')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['link_id' => $link['id'], 'payment_reference' => $payment], $association);
        $this->get($this->customerUrl($link).'/attempts/'.$attempt)->assertOk()->assertSee('Payment pending')->assertDontSee('Fictional payment confirmed');
        $this->receiveEvent($this->event($payment))->assertOk();
        $this->get($this->customerUrl($link).'/attempts/'.$attempt)->assertOk()->assertSee('Fictional payment confirmed')->assertSee('does not prove settlement');
        $this->assertGuest();
        $this->postAction($merchant, '/attempts/'.$attempt.'/consume')->assertUnauthorized();
        $this->assertSame(0, $this->countRows('ledger_journals'));
        $this->signIn($owner->fresh());
        $receipt = $this->postAction($merchant, '/attempts/'.$attempt.'/consume')->assertOk()->assertJsonPath('status', 'posted')->json();
        $this->postAction($merchant, '/attempts/'.$attempt.'/consume')->assertExactJson($receipt);
        $this->postAction($merchant, '/attempts/'.$attempt.'/reconcile')->assertExactJson($receipt);
        $this->getJson($this->merchantUrl($merchant))->assertOk()->assertJsonPath('attempts.0.payment_reference', $payment)->assertJsonPath('attempts.0.status', 'confirmed')->assertJsonPath('attempts.0.journal_id', $receipt['id']);
        $this->getJson('/merchants/'.$merchant->id.'/__isolated-ledger')->assertOk()->assertJsonPath('pending_merchant_obligation_tzs', 12500)->assertJsonPath('withdrawable_tzs', 0)->assertJsonPath('settlement_status', 'unproven');
        $this->assertSame(1, $this->countRows('ledger_journals'));
        $this->assertSame([12500, 12500], $this->paymentPdo->query('SELECT amount_tzs FROM ledger_postings ORDER BY line')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame($merchantBefore, $merchant->fresh()->getAttributes());
        $this->assertSame([], $this->paymentPdo->query('PRAGMA foreign_key_check')->fetchAll());
    }

    public function test_link_creation_is_idempotent_and_fixed_inputs_are_validated(): void
    {
        [, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->assertOk()->json();
        $this->createLink($merchant)->assertExactJson($link);
        $this->createLink($merchant, ['amount_tzs' => 12501])->assertStatus(409);
        foreach ([0, -1, 1.5, '12500', true, null, PHP_INT_MAX, 1000000000001] as $amount) {
            $this->createLink($merchant, ['amount_tzs' => $amount, 'request_key' => (string) Str::uuid()])->assertStatus(422);
        }
        foreach ([['currency' => 'USD'], ['description' => ''], ['description' => str_repeat('x', 201)], ['description' => "unsafe\ncontrol"], ['expires_at' => 1], ['expires_at' => 'later'], ['actor_id' => 999], ['_token' => 'wrong']] as $change) {
            $this->createLink($merchant, [...$change, 'request_key' => (string) Str::uuid()])->assertStatus(isset($change['_token']) ? 419 : 422);
        }
        $this->assertSame(1, $this->countRows('payment_links'));
        $this->assertSame(0, $this->countRows('payment_intents'));
    }

    public function test_guest_nonce_csrf_and_amount_or_merchant_tampering_are_rejected(): void
    {
        [, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->json();
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk();
        foreach ([['amount_tzs' => 1], ['currency' => 'USD'], ['merchant_id' => 'foreign'], ['status' => 'confirmed'], ['provider_result' => 'ok']] as $claim) {
            $this->start($link, $claim)->assertStatus(422);
        }
        $this->start($link, ['request_nonce' => str_repeat('0', 64)])->assertStatus(409);
        $this->start($link, ['_token' => 'wrong'])->assertStatus(419);
        $this->assertSame(0, $this->countRows('checkout_attempts'));
        $this->assertSame(0, $this->provider->creates);
        $result = $this->start($link)->assertOk()->json();
        $this->start($link)->assertExactJson($result);
        $this->assertSame(1, $this->provider->creates);
        $this->assertSame(1, $this->countRows('payment_intents'));
        $this->assertSame(1, $this->countRows('checkout_attempts'));
    }

    public static function outcomes(): array
    {
        return [['pending', 'Payment pending'], ['failed', 'Mock payment failed'], ['unknown', 'Payment outcome unknown']];
    }

    #[DataProvider('outcomes')]
    public function test_truthful_pending_failed_and_unknown_screens(string $state, string $message): void
    {
        [$owner, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->json();
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk();
        $this->provider->scenario = $state === 'unknown' ? 'unknown' : 'pending';
        $result = $this->start($link)->assertOk()->json();
        if ($state === 'failed') {
            $this->receiveEvent($this->event($result['payment_reference'], 'declined'))->assertOk();
        }
        $this->get($this->customerUrl($link).'/attempts/'.$result['attempt_id'])->assertOk()->assertSee($message)->assertDontSee('Fictional payment confirmed');
        $this->start($link)->assertOk()->assertJsonPath('status', $state);
        $this->assertSame(1, $this->provider->creates);
        $this->signIn($owner->fresh());
        $this->postAction($merchant, '/attempts/'.$result['attempt_id'].'/consume')->assertStatus(409);
        $this->assertSame(0, $this->countRows('ledger_journals'));
    }

    public function test_authenticated_events_deduplicate_and_terminal_conflicts_preserve_ledger(): void
    {
        [$owner, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->json();
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk();
        $result = $this->start($link)->json();
        $body = $this->event($result['payment_reference']);
        $this->receiveEvent($body, str_repeat('0', 64))->assertStatus(401);
        $payload = json_decode($body, true);
        $mismatch = json_encode([...$payload, 'correlation' => 'foreign'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->receiveEvent($mismatch)->assertStatus(409);
        $this->assertSame(0, $this->countRows('payment_confirmations'));
        $this->receiveEvent($body)->assertJsonPath('status', 'applied');
        $this->receiveEvent($body)->assertJsonPath('status', 'duplicate');
        $this->receiveEvent($this->event($result['payment_reference'], 'declined', 2))->assertJsonPath('status', 'conflict');
        $this->getJson($this->customerUrl($link).'/attempts/'.$result['attempt_id'])->assertJsonPath('status', 'confirmed');
        $this->signIn($owner->fresh());
        $receipt = $this->postAction($merchant, '/attempts/'.$result['attempt_id'].'/consume')->assertOk()->json();
        $this->postAction($merchant, '/attempts/'.$result['attempt_id'].'/consume')->assertExactJson($receipt);
        $this->assertSame(1, $this->countRows('payment_confirmations'));
        $this->assertSame(1, $this->countRows('ledger_seals'));
    }

    public function test_disabled_and_expired_links_reject_new_attempts_but_keep_historical_status(): void
    {
        [$owner, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->json();
        $expired = $this->createLink($merchant, ['request_key' => 'fictional-expiring-0001', 'expires_at' => $this->linkAdmission->time + 50])->json();
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk();
        $result = $this->start($link)->assertOk()->json();
        $customer = session()->getId();
        $this->signIn($owner->fresh());
        $this->postAction($merchant, '/'.$link['id'].'/disable')->assertOk()->assertJsonPath('state', 'disabled');
        $this->postAction($merchant, '/'.$link['id'].'/disable')->assertOk();
        $this->linkAdmission->time += 100;
        $this->paymentAdmission->time += 100;
        $this->customer($customer);
        $this->get($this->customerUrl($link).'/attempts/'.$result['attempt_id'])->assertOk()->assertSee('Payment pending');
        $this->get($this->customerUrl($expired))->assertStatus(410);
        $this->customer();
        $this->get($this->customerUrl($link))->assertStatus(410);
        session(['checkout.nonces.'.$link['public_token'] => str_repeat('a', 64)]);
        $this->start($link)->assertStatus(410);
        $this->get('/__isolated-checkout/'.str_repeat('b', 64))->assertNotFound();
        $this->assertSame(1, $this->provider->creates);
        $this->assertSame(1, $this->countRows('checkout_attempts'));
    }

    public function test_customer_sessions_do_not_share_attempts_or_merchant_privileges(): void
    {
        [, $merchant] = $this->ready();
        $one = $this->createLink($merchant)->json();
        $two = $this->createLink($merchant, ['request_key' => 'fictional-other-link-0001'])->json();
        $this->customer();
        $this->get($this->customerUrl($one))->assertOk();
        $result = $this->start($one)->assertOk()->json();
        $this->getJson($this->merchantUrl($merchant))->assertUnauthorized();
        $this->getJson($this->customerUrl($two).'/attempts/'.$result['attempt_id'])->assertNotFound();
        $this->customer();
        $this->getJson($this->customerUrl($one).'/attempts/'.$result['attempt_id'])->assertNotFound()->assertDontSee($result['payment_reference']);
        $this->get($this->customerUrl($one))->assertOk();
        $second = $this->start($one)->assertOk()->json();
        $this->assertNotSame($result['payment_reference'], $second['payment_reference']);
        $this->assertSame(2, $this->countRows('checkout_attempts'));
    }

    public function test_foreign_merchants_and_revoked_authority_cannot_manage_or_initiate(): void
    {
        [$owner, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->json();
        $foreign = Merchant::factory()->create(['created_by_user_id' => $owner->id]);
        $this->postAction($foreign, '/'.$link['id'].'/disable')->assertStatus(503);
        $this->getJson($this->merchantUrl($foreign))->assertStatus(503);
        $other = User::factory()->create();
        $this->signIn($other);
        $this->linkAdmission->actor = $this->paymentAdmission->actor = $other->id;
        $this->createLink($merchant)->assertNotFound();
        $this->postAction($merchant, '/'.$link['id'].'/disable')->assertNotFound();
        $this->linkAdmission->actor = $this->paymentAdmission->actor = $owner->id;
        $this->signIn($owner->fresh());
        DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('user_id', $owner->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'authority_version' => 2]);
        $this->postAction($merchant, '/'.$link['id'].'/disable')->assertNotFound();
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk();
        $this->start($link)->assertStatus(403);
        $this->assertSame(0, $this->countRows('payment_intents'));
        $this->assertSame(0, $this->provider->creates);
    }

    public function test_html_escapes_descriptions_and_regular_form_submission_uses_real_services(): void
    {
        [, $merchant] = $this->ready();
        $this->syncSession();
        $this->post($this->merchantUrl($merchant), ['_token' => session()->token(), 'amount_tzs' => '12500', 'currency' => 'TZS', 'description' => '<script>alert("test")</script>', 'request_key' => 'fictional-form-link-0001'])->assertStatus(303);
        $link = $this->getJson($this->merchantUrl($merchant))->json('links.0');
        $this->get($this->merchantUrl($merchant))->assertOk()->assertSee('<script>alert("test")</script>')->assertDontSee('<script>alert("test")</script>', false);
        $this->customer();
        $page = $this->get($this->customerUrl($link))->assertOk()->assertSee('<script>alert("test")</script>')->assertDontSee('<script>alert("test")</script>', false)->assertSee('xlipa-checkout.js')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('name="viewport"', $page->getContent());
        $this->syncSession();
        $this->post($this->customerUrl($link), ['_token' => session()->token(), 'request_nonce' => session('checkout.nonces.'.$link['public_token'])])->assertStatus(303);
        $this->assertSame(1, $this->provider->creates);
    }

    public function test_merchant_form_rotates_creation_key_after_success_and_preserves_duplicate_receipt(): void
    {
        [, $merchant] = $this->ready();
        $this->get($this->merchantUrl($merchant))->assertOk();
        $key = session('link_request_key');
        $this->createLink($merchant, ['request_key' => $key])->assertOk();
        $this->assertNull(session('link_request_key'));
        $this->get($this->merchantUrl($merchant))->assertOk();
        $next = session('link_request_key');
        $this->assertNotSame($key, $next);
        $this->createLink($merchant, ['request_key' => $key])->assertOk();
        $this->assertSame($next, session('link_request_key'));
        $this->createLink($merchant, ['request_key' => $next, 'description' => 'Second fictional order'])->assertOk();
        $this->assertSame(2, $this->countRows('payment_links'));
    }

    public static function commitOutcomes(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('commitOutcomes')]
    public function test_checkout_reservation_uncertainty_never_dispatches_on_duplicate(bool $committed): void
    {
        [, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->json();
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk();
        $store = new class($this->paymentPdo, $committed) extends PaymentStore
        {
            public int $commits = 0;

            public function __construct(PDO $pdo, private readonly bool $committed)
            {
                parent::__construct($pdo);
            }

            protected function commit(): void
            {
                $this->commits++;
                if ($this->committed) {
                    parent::commit();
                }
                throw new RuntimeException('PRIVATE lost acknowledgement');
            }
        };
        $this->useStore($store);
        $this->start($link)->assertStatus(202)->assertJsonPath('status', 'unresolved');
        $this->start($link)->assertStatus(202);
        $this->assertSame(1, $store->commits);
        $this->assertSame(0, $this->provider->creates);
        $this->assertSame(0, $this->countRows('payment_intents'));
        $this->assertSame($committed ? 1 : 0, $this->countRows('checkout_attempts'));
        $this->useStore(new PaymentStore($this->paymentPdo));
        if ($committed) {
            $this->start($link)->assertOk()->assertJsonPath('status', 'unresolved');
            $this->assertSame(0, $this->provider->creates);
        } else {
            $this->start($link)->assertOk()->assertJsonPath('status', 'pending');
            $this->assertSame(1, $this->provider->creates);
        }
    }

    #[DataProvider('commitOutcomes')]
    public function test_checkout_accounting_reconciles_ambiguous_commit_without_duplicate_effects(bool $committed): void
    {
        [$owner, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->json();
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk();
        $result = $this->start($link)->assertOk()->json();
        $this->receiveEvent($this->event($result['payment_reference']))->assertOk();
        $this->signIn($owner->fresh());
        $store = new class($this->paymentPdo, $committed) extends PaymentStore
        {
            private bool $posting = false;

            public function __construct(PDO $pdo, private readonly bool $committed)
            {
                parent::__construct($pdo);
            }

            public function write(string $sql, array $parameters = []): int
            {
                $this->posting = $this->posting || str_starts_with($sql, 'INSERT INTO ledger_journals');

                return parent::write($sql, $parameters);
            }

            protected function commit(): void
            {
                if (! $this->posting || $this->committed) {
                    parent::commit();
                }
                if ($this->posting) {
                    throw new RuntimeException('PRIVATE lost acknowledgement');
                }
            }
        };
        $this->useStore($store);
        $path = '/attempts/'.$result['attempt_id'];
        $this->postAction($merchant, $path.'/consume')->assertStatus(202)->assertDontSee('PRIVATE');
        $this->postAction($merchant, $path.'/consume')->assertStatus(202);
        $this->assertSame($committed ? 1 : 0, $this->countRows('ledger_journals'));
        $this->useStore(new PaymentStore($this->paymentPdo));
        $this->postAction($merchant, $path.'/reconcile')->assertOk()->assertJsonPath('status', $committed ? 'posted' : 'not_recorded');
        $this->assertSame($committed ? 1 : 0, $this->countRows('ledger_journals'));
        $receipt = $this->postAction($merchant, $path.'/consume')->assertOk()->json();
        $this->postAction($merchant, $path.'/consume')->assertExactJson($receipt);
        $this->assertSame(1, $this->countRows('ledger_journals'));
        $this->assertSame(2, $this->countRows('ledger_postings'));
        $this->getJson('/merchants/'.$merchant->id.'/__isolated-ledger')->assertOk()->assertJsonPath('withdrawable_tzs', 0);
    }

    public function test_identity_and_association_history_cannot_be_rewritten(): void
    {
        [, $merchant] = $this->ready();
        $link = $this->createLink($merchant)->json();
        $this->customer();
        $this->get($this->customerUrl($link))->assertOk();
        $this->start($link)->assertOk();
        foreach (['UPDATE payment_links SET amount_tzs=1', 'DELETE FROM payment_links', 'INSERT OR REPLACE INTO payment_links SELECT * FROM payment_links', 'UPDATE checkout_attempts SET payment_reference=NULL', 'DELETE FROM checkout_attempts', 'INSERT OR REPLACE INTO checkout_attempts SELECT * FROM checkout_attempts'] as $sql) {
            try {
                $this->paymentPdo->exec($sql);
                $this->fail('Link/payment history must be immutable.');
            } catch (PDOException) {
                $this->assertSame(1, $this->countRows('payment_links'));
                $this->assertSame(1, $this->countRows('checkout_attempts'));
            }
        }
    }

    public function test_default_admission_and_ordinary_runtime_have_no_checkout_or_link_routes(): void
    {
        [, $merchant] = $this->ready();
        $this->linkAdmission->allow = false;
        $this->createLink($merchant)->assertStatus(503);
        $ordinary = $this->createApplication();
        $default = $ordinary->make(PaymentLinkAdmission::class);
        $this->assertFalse($default->authorized(1, $merchant->id));
        $this->assertFalse($default->customerAuthorized($merchant->id, 'initiate'));
        foreach ($ordinary->make('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('__isolated-links', $route->uri());
            $this->assertStringNotContainsString('__isolated-checkout', $route->uri());
        }
    }
}
