<?php

namespace Tests\Feature;

use App\Actions\Merchants\WorkflowSchemaReadiness;
use App\Actions\Payments\FictionalMockProvider;
use App\Actions\Payments\PaymentAdmission;
use App\Actions\Payments\PaymentEngine;
use App\Actions\Payments\PaymentStore;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FakePaymentAdmission;
use Tests\Support\MerchantDecisionJourney;
use Tests\Support\PaymentHttpHarness;
use Tests\Support\PaymentStoreFixture;
use Tests\TestCase;

class FunctionalMockPaymentTest extends TestCase
{
    use MerchantDecisionJourney;

    private PDO $paymentPdo;

    private FictionalMockProvider $provider;

    private FakePaymentAdmission $paymentAdmission;

    private PaymentHttpHarness $payments;

    private function eligible(string $outcome = 'approved'): array
    {
        [$owner, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), $outcome, $outcome === 'rejected' ? 'requirements_not_met' : null);
        $this->requestDecision($merchant, $application->id, $ref)->assertOk()->assertExactJson(['status' => $outcome]);
        $this->signIn($owner->fresh());
        $this->paymentPdo = new PDO('sqlite::memory:');
        PaymentStoreFixture::install($this->paymentPdo);
        $this->provider = new FictionalMockProvider(str_repeat('F', 32));
        $this->paymentAdmission = new FakePaymentAdmission;
        $this->paymentAdmission->actor = $owner->id;
        $this->paymentAdmission->merchant = $merchant->id;
        $this->payments = new PaymentHttpHarness(new PaymentStore($this->paymentPdo), $this->provider, $this->paymentAdmission);
        $this->payments->register();

        return [$owner, $merchant, $application];
    }

    private function createPayment(Merchant $merchant, array $changes = []): TestResponse
    {
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();

        return $this->postJson('/merchants/'.$merchant->id.'/__isolated-payments', ['_token' => session()->token(), 'amount_tzs' => 12500, 'currency' => 'TZS', 'idempotency_key' => 'fictional-order-0001', 'order_reference' => 'ORDER_001', ...$changes]);
    }

    private function receiveEvent(string $body, ?string $signature = null): TestResponse
    {
        return $this->call('POST', '/__isolated-provider-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_FICTIONAL_SIGNATURE' => $signature ?? $this->provider->signature($body)], $body);
    }

    private function event(string $reference, string $result = 'ok', int $sequence = 1, ?string $id = null): string
    {
        return $this->provider->eventBody($reference, $result, $sequence, $this->paymentAdmission->time, $id ?? (string) Str::uuid());
    }

    private function countRows(string $table): int
    {
        return (int) $this->paymentPdo->query('SELECT count(*) FROM '.$table)->fetchColumn();
    }

    public function test_same_p3_approved_merchant_creates_and_confirms_a_payment_without_wallet_credit(): void
    {
        [$owner, $merchant] = $this->eligible();
        $primaryBefore = $merchant->fresh()->getAttributes();
        $catalogue = array_column(DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"), 'name');
        $this->createPayment($merchant)->assertStatus(503);
        $this->assertSame(0, $this->countRows('payment_intents'));
        $this->paymentAdmission->allow = true;
        $response = $this->createPayment($merchant)->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('amount_tzs', 12500)->assertJsonPath('currency', 'TZS');
        $reference = $response->json('reference');
        $this->assertSame(1, $this->provider->creates);
        $this->assertSame(1, $this->countRows('payment_intents'));
        $body = $this->event($reference);
        $this->receiveEvent($body)->assertOk()->assertExactJson(['status' => 'applied']);
        $this->getJson('/merchants/'.$merchant->id.'/__isolated-payments/'.$reference)->assertOk()->assertJsonPath('status', 'confirmed')->assertJsonPath('merchant_id', $merchant->id)->assertDontSee('raw_body')->assertDontSee('body_hash');
        $confirmation = $this->paymentPdo->query('SELECT * FROM payment_confirmations')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($reference, $confirmation['payment_reference']);
        $this->assertSame($merchant->id, $confirmation['merchant_id']);
        $this->assertSame(12500, $confirmation['amount_tzs']);
        $this->assertSame($primaryBefore, $merchant->fresh()->getAttributes());
        $this->assertSame($catalogue, array_column(DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"), 'name'));
        $this->assertSame(WorkflowSchemaReadiness::BNC, app(WorkflowSchemaReadiness::class)->profile());
        $this->assertSame([], DB::select("SELECT name FROM sqlite_master WHERE type='table' AND (name LIKE '%wallet%' OR name LIKE '%balance%' OR name LIKE '%ledger%')"));
        $this->assertSame(['payment_confirmations', 'payment_events', 'payment_intents'], $this->paymentPdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN));
        $this->receiveEvent($body)->assertOk()->assertExactJson(['status' => 'duplicate']);
        $this->createPayment($merchant)->assertOk()->assertJsonPath('reference', $reference)->assertJsonPath('status', 'confirmed');
        $this->assertSame(1, $this->provider->creates);
        $this->assertSame(1, $this->countRows('payment_confirmations'));
        $this->assertSame($confirmation, $this->paymentPdo->query('SELECT * FROM payment_confirmations')->fetch(PDO::FETCH_ASSOC));
    }

    public function test_pending_failed_unknown_and_explicit_provider_query_are_truthful(): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $pending = $this->createPayment($merchant)->assertOk()->json('reference');
        $this->assertSame(0, $this->countRows('payment_confirmations'));
        $this->receiveEvent($this->event($pending, 'declined'))->assertOk();
        $this->getJson('/merchants/'.$merchant->id.'/__isolated-payments/'.$pending)->assertOk()->assertJsonPath('status', 'failed');
        $this->provider->scenario = 'unknown';
        $unknown = $this->createPayment($merchant, ['idempotency_key' => 'fictional-order-0002'])->assertOk()->assertJsonPath('status', 'unknown')->json('reference');
        $this->createPayment($merchant, ['idempotency_key' => 'fictional-order-0002'])->assertOk()->assertJsonPath('status', 'unknown');
        $this->assertSame(2, $this->provider->creates);
        $this->postJson('/merchants/'.$merchant->id.'/__isolated-payments/'.$unknown.'/query', ['_token' => session()->token()])->assertOk()->assertJsonPath('status', 'unknown')->assertJsonPath('provider_observation', 'pending');
        $this->receiveEvent($this->event($unknown))->assertOk();
        $this->getJson('/merchants/'.$merchant->id.'/__isolated-payments/'.$unknown)->assertJsonPath('status', 'confirmed');
        $this->assertSame(1, $this->countRows('payment_confirmations'));
        $this->assertSame(['create' => true, 'query' => true, 'events' => true, 'refunds' => false, 'payouts' => false], $this->provider->capabilities());
    }

    public function test_out_of_order_duplicates_and_conflicting_events_cannot_overwrite_terminal_evidence(): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $ref = $this->createPayment($merchant)->json('reference');
        $this->receiveEvent($this->event($ref, 'waiting', 2))->assertJsonPath('status', 'applied');
        $this->receiveEvent($this->event($ref, 'ok', 1))->assertJsonPath('status', 'out_of_order');
        $this->assertSame(0, $this->countRows('payment_confirmations'));
        $this->receiveEvent($this->event($ref, 'ok', 3))->assertJsonPath('status', 'applied');
        $this->receiveEvent($this->event($ref, 'declined', 4))->assertJsonPath('status', 'conflict');
        $this->receiveEvent($this->event($ref, 'waiting', 5))->assertJsonPath('status', 'out_of_order');
        $this->receiveEvent($this->event($ref, 'ok', 6))->assertJsonPath('status', 'duplicate_outcome');
        $this->assertSame('confirmed', $this->paymentPdo->query('SELECT status FROM payment_intents')->fetchColumn());
        $this->assertSame(1, $this->countRows('payment_confirmations'));
    }

    public function test_malformed_provider_response_stays_unknown_until_authenticated_event_evidence(): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $this->provider->scenario = 'malformed';
        $ref = $this->createPayment($merchant)->assertOk()->assertJsonPath('status', 'unknown')->json('reference');
        $this->assertSame(0, $this->countRows('payment_confirmations'));
        $this->createPayment($merchant)->assertJsonPath('status', 'unknown');
        $this->assertSame(1, $this->provider->creates);
        $this->receiveEvent($this->event($ref))->assertJsonPath('status', 'applied');
        $this->assertSame(1, $this->countRows('payment_confirmations'));
    }

    public static function invalidAmounts(): array
    {
        return [[0], [-1], [1.5], ['12500'], [true], [null], [PHP_INT_MAX], [1000000000001], ['999999999999999999999999999999']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_money_must_be_exact_positive_bounded_integer_tzs(mixed $amount): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $this->createPayment($merchant, ['amount_tzs' => $amount])->assertStatus(422);
        $this->assertSame(0, $this->countRows('payment_intents'));
        $this->assertSame(0, $this->provider->creates);
    }

    public function test_currency_idempotency_conflicts_and_bad_client_claims_are_rejected(): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $this->createPayment($merchant, ['currency' => 'USD'])->assertStatus(422);
        $this->createPayment($merchant, ['allow' => true])->assertStatus(422);
        $this->createPayment($merchant, ['_token' => 'wrong'])->assertStatus(419);
        $this->createPayment($merchant)->assertOk();
        $this->createPayment($merchant, ['amount_tzs' => 12501])->assertStatus(409);
        $this->createPayment($merchant, ['order_reference' => 'OTHER'])->assertStatus(409);
        $this->assertSame(1, $this->provider->creates);
        $this->assertSame(1, $this->countRows('payment_intents'));
    }

    public static function deniedActors(): array
    {
        return [['guest', 401], ['foreign', 404], ['manager', 403], ['revoked', 404], ['stale', 302], ['stale-authority', 409], ['rejected', 409]];
    }

    #[DataProvider('deniedActors')]
    public function test_authority_and_approval_are_required_even_with_a_fictional_grant(string $case, int $status): void
    {
        [$owner, $merchant] = $this->eligible($case === 'rejected' ? 'rejected' : 'approved');
        $this->paymentAdmission->allow = true;
        if ($case === 'guest') {
            auth()->logout();
        } elseif (in_array($case, ['foreign', 'manager'], true)) {
            $other = User::factory()->create();
            if ($case === 'manager') {
                MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $other->id, 'role' => 'manager']);
            }
            $this->signIn($other);
            $this->paymentAdmission->actor = $other->id;
        } elseif ($case === 'revoked') {
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('user_id', $owner->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'authority_version' => 2]);
        } elseif ($case === 'stale') {
            $this->withSession(['auth_version' => 999]);
        } elseif ($case === 'stale-authority') {
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['role' => 'manager', 'authority_version' => 2]);
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['role' => 'owner', 'authority_version' => 3]);
        }
        $this->createPayment($merchant)->assertStatus($status);
        $this->assertSame(0, $this->countRows('payment_intents'));
        $this->assertSame(0, $this->provider->creates);
    }

    public function test_cross_tenant_read_and_event_authenticity_replay_and_correlation_checks(): void
    {
        [$owner, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $ref = $this->createPayment($merchant)->json('reference');
        $body = $this->event($ref);
        $this->receiveEvent($body, str_repeat('0', 64))->assertStatus(401);
        $this->receiveEvent($body.' ')->assertStatus(422);
        $payload = json_decode($body, true);
        foreach (['correlation' => 'foreign', 'amount_tzs' => 12501, 'currency' => 'USD', 'occurred_at' => $this->paymentAdmission->time - 300001] as $field => $value) {
            $changed = json_encode([...$payload, $field => $value], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->receiveEvent($changed)->assertStatus(in_array($field, ['correlation', 'amount_tzs'], true) ? 409 : 422);
        }
        $this->assertSame(0, $this->countRows('payment_events'));
        $this->receiveEvent($body)->assertOk();
        $changed = json_encode([...$payload, 'result' => 'declined'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->receiveEvent($changed)->assertStatus(409);
        $foreign = Merchant::factory()->create(['created_by_user_id' => $owner->id]);
        $foreignReference = (string) Str::uuid();
        $insert = $this->paymentPdo->prepare("INSERT INTO payment_intents(reference,merchant_id,actor_id,auth_version,membership_id,authority_version,application_id,provider,amount_tzs,currency,idempotency_key,order_reference,status,dispatch_state,created_at,updated_at,version,last_sequence)
            SELECT ?,?,actor_id,auth_version,membership_id,authority_version,application_id,provider,amount_tzs,currency,'foreign-idempotency-0001',order_reference,'pending','unattempted',created_at,updated_at,1,0 FROM payment_intents WHERE reference=?");
        $insert->execute([$foreignReference, $foreign->id, $ref]);
        $this->getJson('/merchants/'.$merchant->id.'/__isolated-payments/'.$foreignReference)->assertNotFound()->assertDontSee('amount_tzs');
        $this->getJson('/merchants/'.$foreign->id.'/__isolated-payments/'.$ref)->assertStatus(503);
        $this->paymentAdmission->merchant = $foreign->id;
        $this->getJson('/merchants/'.$foreign->id.'/__isolated-payments/'.$ref)->assertStatus(409);
        $this->assertSame(1, $this->countRows('payment_confirmations'));
    }

    public function test_event_transaction_failure_rolls_back_every_effect_and_retry_is_idempotent(): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $store = new class($this->paymentPdo) extends PaymentStore
        {
            public bool $fail = true;

            public function write(string $sql, array $parameters = []): int
            {
                $changed = parent::write($sql, $parameters);
                if ($this->fail && str_starts_with($sql, 'INSERT INTO payment_confirmations')) {
                    throw new RuntimeException('PRIVATE fictional transaction failure');
                }

                return $changed;
            }
        };
        $this->payments->engine = new PaymentEngine($store, $this->provider, $this->paymentAdmission);
        $ref = $this->createPayment($merchant)->json('reference');
        $body = $this->event($ref);
        $this->receiveEvent($body)->assertStatus(503)->assertDontSee('PRIVATE');
        $this->assertSame('pending', $this->paymentPdo->query('SELECT status FROM payment_intents')->fetchColumn());
        $this->assertSame(0, $this->countRows('payment_events'));
        $this->assertSame(0, $this->countRows('payment_confirmations'));
        $store->fail = false;
        $this->receiveEvent($body)->assertJsonPath('status', 'applied');
        $this->receiveEvent($body)->assertJsonPath('status', 'duplicate');
        $this->assertSame(1, $this->countRows('payment_confirmations'));
    }

    public static function lostCommits(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    #[DataProvider('lostCommits')]
    public function test_lost_acknowledgements_quarantine_the_store_without_automatic_retry(bool $committed, bool $event): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $store = new class($this->paymentPdo, $committed) extends PaymentStore
        {
            public bool $lose = false;

            public int $lostAttempts = 0;

            public function __construct(PDO $pdo, private readonly bool $committed)
            {
                parent::__construct($pdo);
            }

            protected function commit(): void
            {
                if (! $this->lose) {
                    parent::commit();

                    return;
                }
                $this->lostAttempts++;
                if ($this->committed) {
                    parent::commit();
                }
                throw new RuntimeException('PRIVATE commit loss');
            }
        };
        $this->payments->engine = new PaymentEngine($store, $this->provider, $this->paymentAdmission);
        if ($event) {
            $ref = $this->createPayment($merchant)->assertOk()->json('reference');
            $store->lose = true;
            $body = $this->event($ref);
            $this->receiveEvent($body)->assertStatus(202)->assertExactJson(['status' => 'unresolved']);
            $this->assertSame($committed ? 1 : 0, $this->countRows('payment_confirmations'));
            $this->receiveEvent($body)->assertStatus(202);
            $this->assertSame(1, $this->provider->creates);
        } else {
            $store->lose = true;
            $this->createPayment($merchant)->assertStatus(202)->assertDontSee('PRIVATE');
            $this->assertSame($committed ? 1 : 0, $this->countRows('payment_intents'));
            $this->createPayment($merchant)->assertStatus(202);
            $this->assertSame(0, $this->provider->creates);
        }
        $this->assertSame(1, $store->lostAttempts);
    }

    public function test_two_disposable_file_connections_contend_and_share_one_idempotent_intent(): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $path = tempnam(sys_get_temp_dir(), 'xlipa-p4-');
        $this->assertNotFalse($path);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($path)));
        $this->assertMatchesRegularExpression('/^(xlipa-p4-|xli)[A-Za-z0-9.]+$/D', basename($path));
        unlink($path);
        $one = $two = null;
        try {
            $this->paymentPdo->exec('VACUUM INTO '.$this->paymentPdo->quote($path));
            $one = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $two = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            foreach ([$one, $two] as $pdo) {
                $pdo->exec('PRAGMA foreign_keys=ON');
                $pdo->exec('PRAGMA busy_timeout=0');
            }
            unset($pdo);
            $one->exec('BEGIN IMMEDIATE');
            $this->payments->engine = new PaymentEngine(new PaymentStore($two), $this->provider, $this->paymentAdmission);
            $this->createPayment($merchant)->assertStatus(503);
            $one->exec('ROLLBACK');
            $this->payments->engine = new PaymentEngine(new PaymentStore($one), $this->provider, $this->paymentAdmission);
            $ref = $this->createPayment($merchant)->assertOk()->json('reference');
            $this->payments->engine = new PaymentEngine(new PaymentStore($two), $this->provider, $this->paymentAdmission);
            $this->createPayment($merchant)->assertOk()->assertJsonPath('reference', $ref);
            $this->assertSame(1, (int) $one->query('SELECT count(*) FROM payment_intents')->fetchColumn());
            $this->assertSame(1, $this->provider->creates);
            $this->assertSame([], $two->query('PRAGMA foreign_key_check')->fetchAll());
            try {
                $duplicate = $two->prepare("INSERT INTO payment_intents(reference,merchant_id,actor_id,auth_version,membership_id,authority_version,application_id,provider,amount_tzs,currency,idempotency_key,order_reference,status,dispatch_state,created_at,updated_at,version,last_sequence)
                    SELECT ?,merchant_id,actor_id,auth_version,membership_id,authority_version,application_id,provider,amount_tzs,currency,idempotency_key,order_reference,'pending','unattempted',created_at,updated_at,1,0 FROM payment_intents");
                $duplicate->execute([(string) Str::uuid()]);
                $this->fail('Store uniqueness must reject duplicate scoped idempotency.');
            } catch (PDOException) {
                $this->assertSame(1, (int) $two->query('SELECT count(*) FROM payment_intents')->fetchColumn());
            }
        } finally {
            $this->payments->engine = new PaymentEngine(new PaymentStore($this->paymentPdo), $this->provider, $this->paymentAdmission);
            unset($duplicate);
            $one = $two = null;
            foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }

    public function test_event_body_retention_and_confirmation_evidence_survive_privately(): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $ref = $this->createPayment($merchant)->json('reference');
        $body = $this->event($ref);
        $this->receiveEvent($body)->assertOk();
        $this->assertSame($body, $this->paymentPdo->query('SELECT raw_body FROM payment_events')->fetchColumn());
        $this->assertSame(0, $this->payments->engine->expireEventBodies());
        $this->paymentAdmission->time += 86400000;
        $this->assertSame(1, $this->payments->engine->expireEventBodies());
        $this->assertNull($this->paymentPdo->query('SELECT raw_body FROM payment_events')->fetchColumn());
        $this->assertSame(hash('sha256', $body), $this->paymentPdo->query('SELECT body_hash FROM payment_events')->fetchColumn());
        $this->receiveEvent($body)->assertStatus(422);
        $this->assertSame(1, $this->countRows('payment_confirmations'));
        try {
            $insert = $this->paymentPdo->prepare('INSERT INTO payment_events SELECT ?,provider,provider_event_id,payment_reference,body_hash,raw_body,received_at,retain_until,normalized_status,sequence,outcome,occurred_at FROM payment_events');
            $insert->execute([(string) Str::uuid()]);
            $this->fail('Provider event identity must be unique in the database.');
        } catch (PDOException) {
            $this->assertSame(1, $this->countRows('payment_events'));
        }
        try {
            $this->paymentPdo->exec('UPDATE payment_confirmations SET amount_tzs=1');
            $this->fail('Confirmation evidence must be immutable.');
        } catch (PDOException) {
            $this->assertSame(12500, (int) $this->paymentPdo->query('SELECT amount_tzs FROM payment_confirmations')->fetchColumn());
        }
        $confirmation = $this->paymentPdo->query('SELECT * FROM payment_confirmations')->fetch(PDO::FETCH_ASSOC);
        try {
            $replacement = $this->paymentPdo->prepare('INSERT OR REPLACE INTO payment_confirmations SELECT ?,payment_reference,event_id,merchant_id,amount_tzs,currency,confirmed_at FROM payment_confirmations');
            $replacement->execute([(string) Str::uuid()]);
            $this->fail('Replacement must not erase confirmation history.');
        } catch (PDOException) {
            $this->assertSame($confirmation, $this->paymentPdo->query('SELECT * FROM payment_confirmations')->fetch(PDO::FETCH_ASSOC));
        }
    }

    public function test_payment_constraint_enforcement_cannot_be_disabled_silently(): void
    {
        [, $merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        $this->paymentPdo->exec('PRAGMA recursive_triggers=OFF');
        $this->createPayment($merchant)->assertStatus(503);
        $this->assertSame(0, $this->countRows('payment_intents'));
        $this->assertSame(0, $this->provider->creates);
    }

    public function test_ordinary_runtime_has_no_mock_endpoints_or_payment_grant(): void
    {
        $ordinary = $this->createApplication();
        $this->assertFalse($ordinary->make(PaymentAdmission::class)->authorized(1, 'merchant', 'create'));
        $this->assertFalse($ordinary->make(PaymentAdmission::class)->eventsAuthorized('fictional_mock'));
        foreach ($ordinary->make('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('__isolated-payments', $route->uri());
            $this->assertStringNotContainsString('__isolated-provider-events', $route->uri());
        }
    }
}
