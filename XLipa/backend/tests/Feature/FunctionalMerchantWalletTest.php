<?php

namespace Tests\Feature;

use App\Actions\Payments\LedgerAdmission;
use App\Actions\Payments\MerchantWallet;
use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\PaymentEngine;
use App\Actions\Payments\PaymentLinkService;
use App\Actions\Payments\PaymentStore;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\User;
use App\ViewModels\MerchantFinancialPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\FakePaymentLinkAdmission;
use Tests\Support\LedgerHttpHarness;
use Tests\Support\LedgerStoreFixture;
use Tests\Support\MerchantWalletHttpHarness;
use Tests\Support\MockPaymentJourney;
use Tests\Support\PaymentLinkHttpHarness;
use Tests\Support\PaymentLinkStoreFixture;
use Tests\TestCase;

class FunctionalMerchantWalletTest extends TestCase
{
    use MockPaymentJourney;

    private FakeLedgerAdmission $ledgerAdmission;

    private FakePaymentLinkAdmission $linkAdmission;

    private MerchantWalletHttpHarness $financial;

    private PaymentLinkHttpHarness $checkout;

    private LedgerHttpHarness $accounting;

    private function ready(): array
    {
        [$owner, $merchant] = $this->eligible();
        LedgerStoreFixture::install($this->paymentPdo);
        PaymentLinkStoreFixture::install($this->paymentPdo);
        $this->paymentAdmission->allow = true;
        $this->ledgerAdmission = new FakeLedgerAdmission;
        $this->ledgerAdmission->actor = $owner->id;
        $this->ledgerAdmission->merchant = $merchant->id;
        $this->ledgerAdmission->allow = true;
        $this->linkAdmission = new FakePaymentLinkAdmission;
        $this->linkAdmission->actor = $owner->id;
        $this->linkAdmission->merchant = $merchant->id;
        $this->linkAdmission->allow = $this->linkAdmission->customers = true;
        $store = new PaymentStore($this->paymentPdo);
        $ledger = new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission);
        $this->checkout = new PaymentLinkHttpHarness(new PaymentLinkService($this->payments->engine, $store, $ledger, $this->linkAdmission));
        $this->checkout->register();
        $this->accounting = new LedgerHttpHarness($ledger);
        $this->accounting->register();
        $this->financial = new MerchantWalletHttpHarness(new MerchantWallet($ledger));
        $this->financial->register();
        app('router')->getRoutes()->refreshNameLookups();

        return [$owner->fresh(), $merchant->fresh()];
    }

    private function base(Merchant $merchant): string
    {
        return '/merchants/'.$merchant->id.'/__isolated-finances';
    }

    private function read(string $url, bool $json = true): TestResponse
    {
        $response = $json ? $this->getJson($url) : $this->get($url);
        $failure = $this->financial->lastFailure;
        $this->assertSame(200, $response->status(), $url.' HTTP '.$response->status().' '.$response->getContent().' '.($failure ? $failure::class.': '.$failure->getMessage().' at '.$failure->getFile().':'.$failure->getLine() : ''));

        return $response;
    }

    private function postLink(Merchant $merchant, string $suffix, array $data = []): TestResponse
    {
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();

        return $this->postJson('/merchants/'.$merchant->id.'/__isolated-links'.$suffix, ['_token' => session()->token(), ...$data]);
    }

    private function checkoutPayment(User $owner, Merchant $merchant, int $amount = 12500, string $description = 'Fictional order P7'): array
    {
        $this->signIn($owner->fresh());
        $link = $this->postLink($merchant, '', ['amount_tzs' => $amount, 'currency' => 'TZS', 'description' => $description, 'request_key' => (string) Str::uuid()])->assertOk()->json();
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $url = '/__isolated-checkout/'.$link['public_token'];
        $this->get($url)->assertOk();
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $result = $this->postJson($url, ['_token' => session()->token(), 'request_nonce' => session('checkout.nonces.'.$link['public_token'])])->assertOk()->json();
        $this->assertGuest();
        $this->signIn($owner->fresh());

        return [$link, $result];
    }

    private function useFinancialStore(PaymentStore $store): void
    {
        $engine = new PaymentEngine($store, $this->provider, $this->paymentAdmission);
        $this->financial->wallet = new MerchantWallet(new MockPaymentLedger($engine, $this->ledgerAdmission));
    }

    private function financialFingerprint(): string
    {
        $tables = [];
        foreach (['payment_intents', 'payment_events', 'payment_confirmations', 'payment_links', 'checkout_attempts', 'ledger_accounts', 'ledger_journals', 'ledger_postings', 'ledger_seals'] as $table) {
            $tables[$table] = $this->paymentPdo->query('SELECT * FROM '.$table.' ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
        }

        return hash('sha256', json_encode($tables, JSON_THROW_ON_ERROR));
    }

    public function test_same_merchant_p1_to_p7_journey_shows_actual_wallet_history_and_detail(): void
    {
        [$owner, $merchant] = $this->ready();
        [$link, $result] = $this->checkoutPayment($owner, $merchant);
        $body = $this->event($result['payment_reference']);
        $this->receiveEvent($body)->assertOk()->assertJsonPath('status', 'applied');
        $this->receiveEvent($body)->assertOk()->assertJsonPath('status', 'duplicate');
        $beforeAccounting = $this->read($this->base($merchant).'/wallet');
        $beforeAccounting->assertJsonPath('position.confirmed_fictional_payment_tzs', 12500)->assertJsonPath('position.confirmed_unaccounted_tzs', 12500)->assertJsonPath('position.pending_merchant_obligation_tzs', 0);
        $receipt = $this->postLink($merchant, '/attempts/'.$result['attempt_id'].'/consume')->assertOk()->json();
        $this->postLink($merchant, '/attempts/'.$result['attempt_id'].'/consume')->assertExactJson($receipt);
        $this->postLink($merchant, '/attempts/'.$result['attempt_id'].'/reconcile')->assertExactJson($receipt);
        $fingerprint = $this->financialFingerprint();
        $merchantBefore = $merchant->fresh()->getAttributes();
        $wallet = $this->read($this->base($merchant).'/wallet');
        $wallet->assertJsonPath('position.confirmed_fictional_payment_tzs', 12500)->assertJsonPath('position.confirmed_unaccounted_tzs', 0)
            ->assertJsonPath('position.pending_merchant_obligation_tzs', 12500)->assertJsonPath('position.simulated_clearing_receivable_tzs', 12500)
            ->assertJsonPath('position.withdrawable_tzs', 0)->assertJsonPath('position.settlement_status', 'unproven')->assertHeader('Cache-Control', 'no-store, private');
        $history = $this->read($this->base($merchant).'/transactions')->assertJsonCount(1, 'transactions')->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('transactions.0.payment_reference', $result['payment_reference'])->assertJsonPath('transactions.0.payment_link_id', $link['id'])
            ->assertJsonPath('transactions.0.checkout_attempt_id', $result['attempt_id'])->assertJsonPath('transactions.0.journal_id', $receipt['id']);
        $record = $history->json('transactions.0.record_id');
        $this->read($this->base($merchant).'/transactions/'.$record)->assertJsonPath('transaction.accounting_status', 'posted')->assertJsonPath('transaction.settlement_status', 'unproven');
        $this->read($this->base($merchant).'/wallet', false)->assertSee('TZS 12,500')->assertSee('TZS 0')->assertSee('Unproven')->assertSee('xlipa-dashboard.css')->assertSee('xlipa-wallet.css');
        $this->read($this->base($merchant).'/transactions', false)->assertSee($result['payment_reference'])->assertSee($receipt['id'])->assertSee('Fictional mock provider');
        $this->read($this->base($merchant).'/transactions/'.$record, false)->assertSee('Ledger posted')->assertSee('do not prove settlement', false);
        $this->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertOk()->assertSee(route('isolated.wallet', ['merchant' => $merchant->id]), false)->assertSee('Isolated functionality');
        $this->assertSame($fingerprint, $this->financialFingerprint());
        $this->assertSame($merchantBefore, $merchant->fresh()->getAttributes());
        $this->assertSame(1, $this->provider->creates);
        $this->assertSame([], $this->paymentPdo->query('PRAGMA foreign_key_check')->fetchAll());
    }

    public function test_all_real_payment_states_filters_and_retention_history_remain_truthful(): void
    {
        [$owner, $merchant] = $this->ready();
        $records = [];
        foreach (['pending', 'failed', 'unknown', 'confirmed'] as $state) {
            $this->provider->scenario = $state === 'unknown' ? 'unknown' : 'pending';
            [, $result] = $this->checkoutPayment($owner, $merchant, 19);
            if (in_array($state, ['failed', 'confirmed'], true)) {
                $this->receiveEvent($this->event($result['payment_reference'], $state === 'failed' ? 'declined' : 'ok'))->assertOk();
            }
            $records[$state] = $result;
            $filter = $state === 'unknown' ? 'unresolved' : $state;
            $this->read($this->base($merchant).'/transactions?status='.$filter)->assertJsonCount(1, 'transactions')->assertJsonPath('transactions.0.payment_status', $state);
        }
        $this->read($this->base($merchant).'/wallet')->assertJsonPath('position.confirmed_fictional_payment_tzs', 19)->assertJsonPath('position.confirmed_unaccounted_tzs', 19)
            ->assertJsonPath('position.pending_merchant_obligation_tzs', 0)->assertJsonPath('payment_states.pending.count', 1)->assertJsonPath('payment_states.failed.count', 1)->assertJsonPath('payment_states.unknown.count', 1);
        $history = $this->read($this->base($merchant).'/transactions')->assertJsonCount(4, 'transactions')->json('transactions');
        foreach ($history as $row) {
            $this->read($this->base($merchant).'/transactions/'.$row['record_id'], false)->assertSee(MerchantFinancialPresentation::status($row['status']))->assertSee('Ledger not yet posted');
        }
        $this->postLink($merchant, '/attempts/'.$records['confirmed']['attempt_id'].'/consume')->assertOk();
        $before = $this->read($this->base($merchant).'/transactions')->json();
        $this->paymentAdmission->time += 86400001;
        $this->assertSame(2, $this->payments->engine->expireEventBodies());
        $this->assertSame(0, (int) $this->paymentPdo->query('SELECT count(*) FROM payment_events WHERE raw_body IS NOT NULL')->fetchColumn());
        $this->read($this->base($merchant).'/transactions')->assertExactJson($before);
        $this->read($this->base($merchant).'/wallet')->assertJsonPath('position.pending_merchant_obligation_tzs', 19)->assertJsonPath('position.withdrawable_tzs', 0);
    }

    public function test_date_reference_filters_and_pagination_are_bounded_and_stable(): void
    {
        [, $merchant] = $this->ready();
        $references = [];
        for ($index = 0; $index < 23; $index++) {
            $references[] = $this->createPayment($merchant, ['idempotency_key' => (string) Str::uuid()])->assertOk()->json('reference');
        }
        $one = $this->read($this->base($merchant).'/transactions?per_page=10')->assertJsonCount(10, 'transactions')->assertJsonPath('pagination.total', 23)->assertJsonPath('pagination.last_page', 3)->json('transactions');
        $two = $this->read($this->base($merchant).'/transactions?per_page=10&page=2')->assertJsonCount(10, 'transactions')->json('transactions');
        $three = $this->read($this->base($merchant).'/transactions?per_page=10&page=3')->assertJsonCount(3, 'transactions')->json('transactions');
        $ids = array_column([...$one, ...$two, ...$three], 'record_id');
        $this->assertCount(23, array_unique($ids));
        $this->read($this->base($merchant).'/transactions?reference='.$references[0])->assertJsonCount(1, 'transactions')->assertJsonPath('transactions.0.payment_reference', $references[0]);
        $day = (new \DateTimeImmutable('@'.intdiv($this->paymentAdmission->time, 1000)))->setTimezone(new \DateTimeZone('Africa/Nairobi'))->format('Y-m-d');
        $this->read($this->base($merchant).'/transactions?from='.$day.'&to='.$day)->assertJsonPath('pagination.total', 23);
        $this->read($this->base($merchant).'/transactions?from=2027-01-01')->assertJsonCount(0, 'transactions');
        $this->read($this->base($merchant).'/transactions?to=2020-01-01')->assertJsonCount(0, 'transactions');
        $this->read($this->base($merchant).'/transactions?per_page=10', false)->assertSee('Next page')->assertSee('name="status"', false)->assertSee('name="viewport"', false);
        $this->read($this->base($merchant).'/transactions?per_page=10&page=3', false)->assertSee('Previous page')->assertDontSee('Next page');
    }

    public function test_malformed_filters_and_details_cannot_change_sql_scope(): void
    {
        [, $merchant] = $this->ready();
        foreach (['page=0', 'page=-1', 'page=10001', 'page=1.5', 'per_page=1000000', 'status=settled', 'status[]=confirmed', 'from=2026-02-30', 'to=garbage', 'from=2026-11-20&to=2026-11-19', 'reference=%25', 'merchant_id=foreign', 'sort=secret', 'reference=%27%20OR%201%3D1--'] as $query) {
            $this->getJson($this->base($merchant).'/transactions?'.$query)->assertStatus(422)->assertDontSee('SELECT');
        }
        $this->getJson($this->base($merchant).'/transactions/payment_'.Str::uuid())->assertNotFound();
        $this->getJson($this->base($merchant).'/transactions/not-a-reference')->assertNotFound();
        $this->getJson($this->base($merchant).'/wallet?amount_tzs=999')->assertStatus(422);
    }

    public static function accessCases(): array
    {
        return [['guest', 401], ['unverified', 403], ['foreign', 404], ['manager', 403], ['staff', 403], ['revoked', 404], ['suspended', 302], ['closed', 302], ['stale-session', 302], ['stale-membership', 409], ['ledger-denied', 503], ['payment-denied', 503]];
    }

    #[DataProvider('accessCases')]
    public function test_current_owner_authority_is_required_for_every_financial_view(string $case, int $status): void
    {
        [$owner, $merchant] = $this->ready();
        $payment = $this->createPayment($merchant)->assertOk()->json('reference');
        if ($case === 'guest') {
            auth()->logout();
        } elseif (in_array($case, ['foreign', 'manager', 'staff'], true)) {
            $other = User::factory()->create();
            if ($case !== 'foreign') {
                MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $other->id, 'role' => $case]);
            }
            $this->signIn($other);
            $this->ledgerAdmission->actor = $this->paymentAdmission->actor = $other->id;
        } elseif ($case === 'unverified') {
            $this->signIn(User::factory()->unverified()->create());
        } elseif (in_array($case, ['suspended', 'closed'], true)) {
            $this->signIn(User::factory()->create(['account_status' => $case]));
        } elseif ($case === 'stale-session') {
            $this->withSession(['auth_version' => 999]);
        } elseif ($case === 'revoked') {
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'authority_version' => 2]);
        } elseif ($case === 'stale-membership') {
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['role' => 'manager', 'authority_version' => 2]);
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['role' => 'owner', 'authority_version' => 3]);
        } elseif ($case === 'ledger-denied') {
            $this->ledgerAdmission->allow = false;
        } else {
            $this->paymentAdmission->allow = false;
        }
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $urls = ['/wallet', '/transactions', '/transactions/payment_'.$payment];
        foreach ($urls as $index => $path) {
            $expected = in_array($case, ['suspended', 'closed', 'stale-session'], true) && $index > 0 ? 401 : $status;
            $this->getJson($this->base($merchant).$path)->assertStatus($expected)->assertHeader('Cache-Control', 'no-store, private')->assertDontSee($payment)->assertDontSee('requested_tzs');
        }
    }

    public function test_foreign_transaction_ids_are_denied_even_with_admission_to_another_owned_merchant(): void
    {
        [$owner, $merchant] = $this->ready();
        $payment = $this->createPayment($merchant)->json('reference');
        $foreign = Merchant::factory()->create(['created_by_user_id' => $owner->id]);
        $submission = $this->submission->prepare($owner, $foreign, session()->getId());
        $this->submitApplication($foreign, $submission)->assertOk();
        $application = $foreign->applications()->sole();
        $chief = $this->chief();
        $decision = $this->decisions->prepare($chief, $application, session()->getId(), 'approved', null);
        $this->requestDecision($foreign, $application->id, $decision)->assertOk();
        $this->signIn($owner->fresh());
        $this->ledgerAdmission->merchant = $this->paymentAdmission->merchant = $foreign->id;
        $otherPayment = $this->createPayment($foreign, ['amount_tzs' => 7])->assertOk()->json('reference');
        $this->receiveEvent($this->event($otherPayment))->assertOk();
        $source = $this->paymentPdo->prepare('SELECT id FROM payment_confirmations WHERE payment_reference=?');
        $source->execute([$otherPayment]);
        $this->accounting->ledger->post($owner->fresh(), $foreign->id, $source->fetchColumn(), $otherPayment, 7, 'TZS');
        $this->read($this->base($foreign).'/wallet')->assertJsonPath('position.confirmed_fictional_payment_tzs', 7)->assertJsonPath('position.pending_merchant_obligation_tzs', 7);
        $this->read($this->base($foreign).'/transactions')->assertJsonCount(1, 'transactions')->assertJsonPath('transactions.0.payment_reference', $otherPayment)->assertDontSee($payment);
        $this->getJson($this->base($foreign).'/transactions/payment_'.$payment)->assertNotFound()->assertDontSee($payment);
        $this->ledgerAdmission->merchant = $this->paymentAdmission->merchant = $merchant->id;
        $this->read($this->base($merchant).'/wallet')->assertJsonPath('position.confirmed_fictional_payment_tzs', 0)->assertJsonPath('position.pending_merchant_obligation_tzs', 0);
        $this->read($this->base($merchant).'/transactions')->assertJsonCount(1, 'transactions')->assertJsonPath('transactions.0.payment_reference', $payment)->assertDontSee($otherPayment);
        $this->getJson($this->base($merchant).'/transactions/payment_'.$otherPayment)->assertNotFound();
        $this->getJson($this->base($merchant).'/transactions/payment_'.Str::uuid())->assertNotFound();
    }

    public static function corruptedEvidence(): array
    {
        return [['journal_hash', 'invalid'], ['credit_amount', 1], ['normalized_status', 'failed'], ['seal', null], ['raw_body', '{}']];
    }

    #[DataProvider('corruptedEvidence')]
    public function test_corrupted_evidence_never_becomes_a_wallet_balance(string $field, mixed $value): void
    {
        [$owner, $merchant] = $this->ready();
        [, $result] = $this->checkoutPayment($owner, $merchant);
        $this->receiveEvent($this->event($result['payment_reference']))->assertOk();
        $this->postLink($merchant, '/attempts/'.$result['attempt_id'].'/consume')->assertOk();
        $store = new class($this->paymentPdo, $field, $value) extends PaymentStore
        {
            public function __construct(PDO $pdo, private readonly string $field, private readonly mixed $value)
            {
                parent::__construct($pdo);
            }

            public function rows(string $sql, array $parameters = []): array
            {
                $rows = parent::rows($sql, $parameters);
                if (str_contains($sql, 'journal_consumer') && $rows !== []) {
                    $rows[0][$this->field] = $this->value;
                }

                return $rows;
            }
        };
        $this->useFinancialStore($store);
        foreach (['/wallet', '/transactions', '/transactions/payment_'.$result['payment_reference']] as $path) {
            $this->getJson($this->base($merchant).$path)->assertStatus(409)->assertDontSee('position')->assertDontSee('source_hash');
        }
    }

    public function test_html_escapes_stored_descriptions_and_never_exposes_private_evidence(): void
    {
        [$owner, $merchant] = $this->ready();
        $description = '<script>alert("fictional")</script>';
        [, $result] = $this->checkoutPayment($owner, $merchant, 12500, $description);
        $this->receiveEvent($this->event($result['payment_reference']))->assertOk();
        $record = 'payment_'.$result['payment_reference'];
        foreach (['/wallet', '/transactions', '/transactions/'.$record] as $path) {
            foreach ([true, false] as $json) {
                $response = $this->read($this->base($merchant).$path, $json)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
                foreach (['raw_body', 'body_hash', 'source_hash', 'nonce_hash', 'customer_hash', 'auth_version', 'HMAC', '"event_id"'] as $private) {
                    $response->assertDontSee($private, false);
                }
                if (! $json && $path !== '/wallet') {
                    $response->assertSee($description)->assertDontSee($description, false);
                }
            }
        }
    }

    public function test_exact_integer_tzs_formatting_and_empty_wallet_do_not_invent_funds(): void
    {
        [, $merchant] = $this->ready();
        $this->assertSame('TZS 9,223,372,036,854,775,807', MerchantFinancialPresentation::tzs(PHP_INT_MAX));
        $this->assertSame('TZS 1,000,000,000,000', MerchantFinancialPresentation::tzs(PaymentEngine::MAX_AMOUNT_TZS));
        $this->assertSame('TZS 0', MerchantFinancialPresentation::tzs(0));
        $this->read($this->base($merchant).'/wallet')->assertJsonPath('position.confirmed_fictional_payment_tzs', 0)->assertJsonPath('position.withdrawable_tzs', 0)->assertJsonPath('position.settlement_status', 'unproven');
        $this->read($this->base($merchant).'/transactions', false)->assertSee('No recorded transactions match these filters.');
    }

    public function test_commit_uncertainty_returns_no_fabricated_balance_and_reopening_is_read_only(): void
    {
        [$owner, $merchant] = $this->ready();
        [, $result] = $this->checkoutPayment($owner, $merchant);
        $this->receiveEvent($this->event($result['payment_reference']))->assertOk();
        $this->postLink($merchant, '/attempts/'.$result['attempt_id'].'/consume')->assertOk();
        $before = $this->financialFingerprint();
        $store = new class($this->paymentPdo) extends PaymentStore
        {
            public int $commits = 0;

            protected function commit(): void
            {
                $this->commits++;
                parent::commit();
                throw new RuntimeException('PRIVATE read acknowledgement lost');
            }
        };
        $this->useFinancialStore($store);
        $this->getJson($this->base($merchant).'/wallet')->assertStatus(202)->assertJsonPath('status', 'unresolved')->assertDontSee('PRIVATE')->assertDontSee('position');
        $this->getJson($this->base($merchant).'/transactions')->assertStatus(202);
        $this->assertSame(1, $store->commits);
        $this->useFinancialStore(new PaymentStore($this->paymentPdo));
        $this->read($this->base($merchant).'/wallet')->assertJsonPath('position.pending_merchant_obligation_tzs', 12500);
        $this->assertSame($before, $this->financialFingerprint());
    }

    public function test_unresolved_checkout_reservation_is_visible_without_inventing_an_intent(): void
    {
        [$owner, $merchant] = $this->ready();
        $link = $this->postLink($merchant, '', ['amount_tzs' => 12500, 'currency' => 'TZS', 'description' => 'Reserved fictional order', 'request_key' => (string) Str::uuid()])->json();
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        $url = '/__isolated-checkout/'.$link['public_token'];
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $this->get($url)->assertOk();
        $store = new class($this->paymentPdo) extends PaymentStore
        {
            protected function commit(): void
            {
                parent::commit();
                throw new RuntimeException('PRIVATE reservation acknowledgement lost');
            }
        };
        $ledger = new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission);
        $this->checkout->links = new PaymentLinkService($this->payments->engine, $store, $ledger, $this->linkAdmission);
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $this->postJson($url, ['_token' => session()->token(), 'request_nonce' => session('checkout.nonces.'.$link['public_token'])])->assertStatus(202);
        $this->signIn($owner->fresh());
        $this->read($this->base($merchant).'/wallet')->assertJsonPath('unresolved_checkout_reservations', 1)->assertJsonPath('position.confirmed_fictional_payment_tzs', 0);
        $row = $this->read($this->base($merchant).'/transactions?status=unresolved')->assertJsonCount(1, 'transactions')->assertJsonPath('transactions.0.payment_reference', null)->json('transactions.0');
        $this->read($this->base($merchant).'/transactions/'.$row['record_id'])->assertJsonPath('transaction.accounting_status', 'not_eligible');
        $this->assertSame(0, $this->provider->creates);
    }

    public function test_evidence_reads_are_batched_without_n_plus_one_queries(): void
    {
        [, $merchant] = $this->ready();
        for ($index = 0; $index < 101; $index++) {
            $payment = $this->createPayment($merchant, ['amount_tzs' => 1, 'idempotency_key' => (string) Str::uuid()])->assertOk()->json('reference');
            $this->receiveEvent($this->event($payment))->assertOk();
        }
        $store = new class($this->paymentPdo) extends PaymentStore
        {
            public array $queries = [];

            public function rows(string $sql, array $parameters = []): array
            {
                $this->queries[] = $sql;

                return parent::rows($sql, $parameters);
            }
        };
        $this->useFinancialStore($store);
        $this->read($this->base($merchant).'/transactions?per_page=10')->assertJsonCount(10, 'transactions')->assertJsonPath('pagination.total', 101);
        $this->assertCount(5, $store->queries);
        $this->assertStringContainsString('LIMIT 100', $store->queries[0]);
        $this->assertStringContainsString('LIMIT 100', $store->queries[1]);
        $this->assertStringContainsString('LIMIT ? OFFSET ?', $store->queries[4]);
    }

    public function test_late_read_admission_revocation_discards_the_entire_financial_response(): void
    {
        [, $merchant] = $this->ready();
        $calls = 0;
        $this->ledgerAdmission->inspect = function (string $operation) use (&$calls): void {
            if ($operation === 'read' && ++$calls === 3) {
                $this->ledgerAdmission->allow = false;
            }
        };
        $this->getJson($this->base($merchant).'/wallet')->assertStatus(503)->assertDontSee('position')->assertDontSee('withdrawable_tzs');
    }

    public function test_ordinary_runtime_retains_unavailable_financial_navigation_and_no_p7_routes(): void
    {
        [, $merchant] = $this->eligible();
        $this->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertOk()->assertDontSee('Isolated wallet')->assertSee('Financial services')->assertSee('Unavailable');
        $ordinary = $this->createApplication();
        $this->assertFalse($ordinary->make(LedgerAdmission::class)->authorized(1, $merchant->id, 'read'));
        foreach ($ordinary->make('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('__isolated-finances', $route->uri());
        }
    }
}
