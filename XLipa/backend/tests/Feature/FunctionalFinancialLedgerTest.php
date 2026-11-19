<?php

namespace Tests\Feature;

use App\Actions\Merchants\SubmissionTransaction;
use App\Actions\Payments\LedgerAdmission;
use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\PaymentEngine;
use App\Actions\Payments\PaymentStore;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\LedgerHttpHarness;
use Tests\Support\LedgerStoreFixture;
use Tests\Support\MockPaymentJourney;
use Tests\Support\PaymentStoreFixture;
use Tests\TestCase;

class FunctionalFinancialLedgerTest extends TestCase
{
    use MockPaymentJourney;

    private FakeLedgerAdmission $ledgerAdmission;

    private LedgerHttpHarness $accounting;

    private function ledgerReady(string $decision = 'approved'): array
    {
        [$owner, $merchant, $application] = $this->eligible($decision);
        LedgerStoreFixture::install($this->paymentPdo);
        $this->paymentAdmission->allow = true;
        $this->ledgerAdmission = new FakeLedgerAdmission;
        $this->ledgerAdmission->actor = $owner->id;
        $this->ledgerAdmission->merchant = $merchant->id;
        $this->ledgerAdmission->allow = true;
        $this->accounting = new LedgerHttpHarness(new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission));
        $this->accounting->register();

        return [$owner, $merchant, $application];
    }

    private function confirmed(Merchant $merchant, int $amount = 12500): array
    {
        $reference = $this->createPayment($merchant, ['amount_tzs' => $amount, 'idempotency_key' => (string) Str::uuid()])->assertOk()->json('reference');
        $this->receiveEvent($this->event($reference))->assertOk()->assertJsonPath('status', 'applied');
        $statement = $this->paymentPdo->prepare('SELECT id FROM payment_confirmations WHERE payment_reference=?');
        $statement->execute([$reference]);

        return [$reference, $statement->fetchColumn()];
    }

    private function postJournal(Merchant $merchant, string $payment, string $confirmation, array $changes = []): TestResponse
    {
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();

        return $this->postJson($this->ledgerUrl($merchant), ['_token' => session()->token(), 'confirmation_id' => $confirmation, 'payment_reference' => $payment, 'amount_tzs' => 12500, 'currency' => 'TZS', ...$changes]);
    }

    private function ledgerUrl(Merchant $merchant): string
    {
        return '/merchants/'.$merchant->id.'/__isolated-ledger';
    }

    private function useStore(PaymentStore $store): void
    {
        $this->payments->engine = new PaymentEngine($store, $this->provider, $this->paymentAdmission);
        $this->accounting->ledger = new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission);
    }

    private function countLedger(string $table = 'ledger_journals'): int
    {
        return (int) $this->paymentPdo->query('SELECT count(*) FROM '.$table)->fetchColumn();
    }

    private function denied(Closure $work, int $status): void
    {
        try {
            $work();
            $this->fail('Operation must be denied.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    public function test_same_approved_merchant_and_actual_p4_confirmation_post_balanced_fictional_accounts(): void
    {
        [, $merchant] = $this->ledgerReady();
        $before = $merchant->fresh()->getAttributes();
        $catalogue = DB::select('SELECT type,name,sql FROM sqlite_master ORDER BY type,name');
        [$payment, $confirmation] = $this->confirmed($merchant);
        $this->getJson($this->ledgerUrl($merchant))->assertOk()->assertJsonPath('confirmed_fictional_payment_tzs', 12500)->assertJsonPath('pending_merchant_obligation_tzs', 0)->assertJsonPath('journals', []);
        $this->ledgerAdmission->allow = false;
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(503);
        $this->ledgerAdmission->allow = true;
        $receipt = $this->postJournal($merchant, $payment, $confirmation)->assertOk()->assertJsonPath('status', 'posted')->assertJsonPath('amount_tzs', 12500)->json();
        $this->assertSame($payment, $receipt['payment_reference']);
        $this->assertSame($confirmation, $receipt['confirmation_id']);
        $this->getJson($this->ledgerUrl($merchant))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('confirmed_fictional_payment_tzs', 12500)->assertJsonPath('pending_merchant_obligation_tzs', 12500)
            ->assertJsonPath('simulated_clearing_receivable_tzs', 12500)->assertJsonPath('withdrawable_tzs', 0)->assertJsonPath('settlement_status', 'unproven')
            ->assertJsonPath('journals.0.id', $receipt['id'])->assertDontSee('source_hash')->assertDontSee('raw_body');
        $this->assertSame([['direction' => 'credit', 'total' => 12500], ['direction' => 'debit', 'total' => 12500]], $this->paymentPdo->query('SELECT direction,sum(amount_tzs) AS total FROM ledger_postings GROUP BY direction ORDER BY direction')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame(['asset', 'liability'], $this->paymentPdo->query('SELECT account_type FROM ledger_accounts ORDER BY account_type')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame($before, $merchant->fresh()->getAttributes());
        $this->assertEquals($catalogue, DB::select('SELECT type,name,sql FROM sqlite_master ORDER BY type,name'));
        $this->assertSame([], $this->paymentPdo->query('PRAGMA foreign_key_check')->fetchAll());
        $this->assertSame(1, $this->countLedger('ledger_seals'));
    }

    public function test_duplicate_confirmation_and_conflicting_commands_never_repost(): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $receipt = $this->postJournal($merchant, $payment, $confirmation)->assertOk()->json();
        $snapshot = $this->paymentPdo->query('SELECT * FROM ledger_postings')->fetchAll(PDO::FETCH_ASSOC);
        $this->postJournal($merchant, $payment, $confirmation)->assertExactJson($receipt);
        $this->receiveEvent($this->event($payment, 'ok', 2))->assertJsonPath('status', 'duplicate_outcome');
        $this->postJournal($merchant, $payment, $confirmation)->assertExactJson($receipt);
        $this->postJournal($merchant, $payment, $confirmation, ['amount_tzs' => 12501])->assertStatus(409);
        $this->postJournal($merchant, (string) Str::uuid(), $confirmation)->assertStatus(409);
        $this->assertSame(1, $this->countLedger());
        $this->assertSame($snapshot, $this->paymentPdo->query('SELECT * FROM ledger_postings')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame(1, $this->provider->creates);
    }

    public static function invalidAmounts(): array
    {
        return [[0], [-1], [1.5], ['12500'], [true], [null], [PHP_INT_MAX], [1000000000001]];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_posting_amounts_cannot_create_history(mixed $amount): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $this->postJournal($merchant, $payment, $confirmation, ['amount_tzs' => $amount])->assertStatus(422);
        $this->assertSame(0, $this->countLedger());
        $this->assertSame(0, $this->countLedger('ledger_accounts'));
    }

    public function test_non_tzs_client_authority_claims_and_csrf_are_rejected(): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $this->postJournal($merchant, $payment, $confirmation, ['currency' => 'USD'])->assertStatus(422);
        $this->postJournal($merchant, $payment, $confirmation, ['settled' => true])->assertStatus(422);
        $this->postJournal($merchant, $payment, $confirmation, ['_token' => 'wrong'])->assertStatus(419);
        $this->assertSame(0, $this->countLedger());
    }

    public static function excludedStates(): array
    {
        return [['pending'], ['failed'], ['unknown'], ['unauthenticated']];
    }

    #[DataProvider('excludedStates')]
    public function test_nonconfirmed_or_unauthenticated_payments_have_no_accounting_source(string $state): void
    {
        [, $merchant] = $this->ledgerReady();
        $this->provider->scenario = $state === 'unknown' ? 'unknown' : 'pending';
        $payment = $this->createPayment($merchant)->assertOk()->json('reference');
        if ($state === 'failed') {
            $this->receiveEvent($this->event($payment, 'declined'))->assertOk();
        } elseif ($state === 'unauthenticated') {
            $this->receiveEvent($this->event($payment), str_repeat('0', 64))->assertStatus(401);
        }
        $this->postJournal($merchant, $payment, (string) Str::uuid())->assertNotFound();
        $this->assertSame(0, $this->countLedger('payment_confirmations'));
        $this->getJson($this->ledgerUrl($merchant))->assertOk()->assertJsonPath('confirmed_fictional_payment_tzs', 0)->assertJsonPath('withdrawable_tzs', 0);
        $this->assertSame(0, $this->countLedger());
    }

    public static function deniedActors(): array
    {
        return [['guest', 401], ['foreign', 404], ['manager', 403], ['revoked', 404], ['stale', 302], ['rejected', 409], ['payment-grant', 503], ['ledger-grant', 503]];
    }

    #[DataProvider('deniedActors')]
    public function test_current_merchant_and_independent_authorizations_are_required(string $case, int $status): void
    {
        [$owner, $merchant] = $this->ledgerReady($case === 'rejected' ? 'rejected' : 'approved');
        [$payment, $confirmation] = $case === 'rejected' ? [(string) Str::uuid(), (string) Str::uuid()] : $this->confirmed($merchant);
        if ($case === 'guest') {
            auth()->logout();
        } elseif (in_array($case, ['foreign', 'manager'], true)) {
            $other = User::factory()->create();
            if ($case === 'manager') {
                MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $other->id, 'role' => 'manager']);
            }
            $this->signIn($other);
            $this->paymentAdmission->actor = $this->ledgerAdmission->actor = $other->id;
        } elseif ($case === 'revoked') {
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('user_id', $owner->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'authority_version' => 2]);
        } elseif ($case === 'stale') {
            $this->withSession(['auth_version' => 999]);
        } elseif ($case === 'payment-grant') {
            $this->paymentAdmission->allow = false;
        } elseif ($case === 'ledger-grant') {
            $this->ledgerAdmission->allow = false;
        }
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus($status);
        $this->getJson($this->ledgerUrl($merchant))->assertStatus($case === 'stale' ? 401 : $status);
        $this->assertSame(0, $this->countLedger());
    }

    public function test_foreign_confirmation_and_financial_reads_are_denied_at_domain_boundary(): void
    {
        [$owner, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $this->postJournal($merchant, $payment, $confirmation)->assertOk();
        $foreign = Merchant::factory()->create(['created_by_user_id' => $owner->id]);
        $this->paymentAdmission->merchant = $this->ledgerAdmission->merchant = $foreign->id;
        $this->denied(fn () => $this->accounting->ledger->position($owner, $foreign->id), 409);
        $this->denied(fn () => $this->accounting->ledger->post($owner, $foreign->id, $confirmation, $payment, 12500, 'TZS'), 409);
        $this->paymentAdmission->merchant = $this->ledgerAdmission->merchant = $merchant->id;
        $this->postJournal($merchant, $payment, (string) Str::uuid())->assertNotFound()->assertDontSee('12500');
        $this->assertSame(1, $this->countLedger());
    }

    public function test_generated_real_confirmations_all_post_balanced_and_reads_are_historical(): void
    {
        [, $merchant] = $this->ledgerReady();
        $total = 0;
        foreach ([1, 2, 19, 12500, 999999, 1000000000000, 500000000001] as $amount) {
            [$payment, $confirmation] = $this->confirmed($merchant, $amount);
            $receipt = $this->postJournal($merchant, $payment, $confirmation, ['amount_tzs' => $amount])->assertOk()->json();
            $this->assertSame($amount, $receipt['amount_tzs']);
            $total += $amount;
        }
        $journals = $this->paymentPdo->query("SELECT j.id,j.amount_tzs,sum(CASE WHEN p.direction='debit' THEN p.amount_tzs ELSE 0 END) AS debits,sum(CASE WHEN p.direction='credit' THEN p.amount_tzs ELSE 0 END) AS credits FROM ledger_journals j JOIN ledger_postings p ON p.journal_id=j.id GROUP BY j.id")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(7, $journals);
        foreach ($journals as $journal) {
            $this->assertIsInt($journal['debits']);
            $this->assertSame($journal['amount_tzs'], $journal['debits']);
            $this->assertSame($journal['debits'], $journal['credits']);
        }
        $this->ledgerAdmission->time += 86400000;
        $this->paymentAdmission->time += 86400000;
        $this->assertSame(7, $this->payments->engine->expireEventBodies());
        $this->getJson($this->ledgerUrl($merchant))->assertOk()->assertJsonPath('confirmed_fictional_payment_tzs', $total)->assertJsonPath('pending_merchant_obligation_tzs', $total)->assertJsonPath('simulated_clearing_receivable_tzs', $total)->assertJsonPath('withdrawable_tzs', 0)->assertJsonCount(7, 'journals');
        $this->postJournal($merchant, $payment, $confirmation, ['amount_tzs' => $amount])->assertExactJson($receipt);
        $this->assertSame(2, $this->countLedger('ledger_accounts'));
    }

    public static function failurePoints(): array
    {
        return [['journal'], ['credit'], ['seal'], ['after-seal']];
    }

    #[DataProvider('failurePoints')]
    public function test_interrupted_posting_rolls_back_accounts_journal_postings_and_receipt(string $point): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $store = new class($this->paymentPdo, $point) extends PaymentStore
        {
            public function __construct(PDO $pdo, private readonly string $point)
            {
                parent::__construct($pdo);
            }

            public function write(string $sql, array $parameters = []): int
            {
                if (($this->point === 'journal' && str_starts_with($sql, 'INSERT INTO ledger_journals')) || ($this->point === 'credit' && ($parameters[1] ?? null) === 2 && str_starts_with($sql, 'INSERT INTO ledger_postings')) || ($this->point === 'seal' && str_starts_with($sql, 'INSERT INTO ledger_seals'))) {
                    throw new RuntimeException('Fictional interrupted posting');
                }
                $result = parent::write($sql, $parameters);
                if ($this->point === 'after-seal' && str_starts_with($sql, 'INSERT INTO ledger_seals')) {
                    throw new RuntimeException('Fictional interrupted posting');
                }

                return $result;
            }
        };
        $this->useStore($store);
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(503);
        foreach (['ledger_accounts', 'ledger_journals', 'ledger_postings', 'ledger_seals'] as $table) {
            $this->assertSame(0, $this->countLedger($table));
        }
        $this->assertSame(1, $this->countLedger('payment_confirmations'));
        $this->useStore(new PaymentStore($this->paymentPdo));
        $this->getJson($this->ledgerUrl($merchant).'/'.$confirmation.'/reconciliation')->assertOk()->assertJsonPath('status', 'not_recorded');
        $this->postJournal($merchant, $payment, $confirmation)->assertOk();
        $this->assertSame(1, $this->countLedger());
    }

    public function test_accounting_grant_loss_after_posting_rolls_back_every_ledger_effect(): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $this->ledgerAdmission->inspect = function (): void {
            if ($this->countLedger('ledger_seals') !== 0) {
                $this->ledgerAdmission->allow = false;
            }
        };
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(503);
        $this->assertSame(0, $this->countLedger());
        $this->assertSame(0, $this->countLedger('ledger_accounts'));
    }

    public function test_membership_revocation_before_final_accounting_commit_rolls_back_postings(): void
    {
        [$owner, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $this->ledgerAdmission->inspect = function () use ($owner, $merchant): void {
            if ($this->countLedger('ledger_seals') !== 0) {
                DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('user_id', $owner->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'authority_version' => 2]);
            }
        };
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(403);
        $this->assertSame(0, $this->countLedger());
        $this->assertSame(0, $this->countLedger('ledger_postings'));
    }

    public static function corruptedEvidence(): array
    {
        return [['actor_id', 404], ['auth_version', 404], ['membership_id', 404], ['authority_version', 404], ['application_id', 404], ['provider', 409], ['confirmation_amount', 409], ['event_hash', 409], ['merchant_id', 409]];
    }

    #[DataProvider('corruptedEvidence')]
    public function test_corrupted_source_and_exact_payment_ownership_cannot_be_accounted(string $field, int $status): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $replacement = new PDO('sqlite::memory:');
        PaymentStoreFixture::install($replacement);
        LedgerStoreFixture::install($replacement);
        foreach (['payment_intents', 'payment_events', 'payment_confirmations'] as $table) {
            $row = $this->paymentPdo->query('SELECT * FROM '.$table)->fetch(PDO::FETCH_ASSOC);
            if ($table === 'payment_intents' && ! in_array($field, ['confirmation_amount', 'event_hash'], true)) {
                $row[$field] = is_int($row[$field]) ? $row[$field] + 100 : 'foreign';
            } elseif ($table === 'payment_events' && $field === 'event_hash') {
                $row['body_hash'] = str_repeat('0', 64);
            } elseif ($table === 'payment_confirmations' && $field === 'confirmation_amount') {
                $row['amount_tzs']++;
            }
            $insert = $replacement->prepare('INSERT INTO '.$table.'('.implode(',', array_keys($row)).') VALUES('.implode(',', array_fill(0, count($row), '?')).')');
            $insert->execute(array_values($row));
        }
        $this->paymentPdo = $replacement;
        $this->useStore(new PaymentStore($replacement));
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus($status);
        $this->assertSame(0, $this->countLedger());
        $this->assertSame(0, $this->countLedger('ledger_accounts'));
        $this->assertSame(1, $this->provider->creates);
    }

    public function test_totals_reject_overflow_without_float_conversion(): void
    {
        $this->ledgerReady();
        $ledger = $this->accounting->ledger;
        $sum = new \ReflectionMethod(MockPaymentLedger::class, 'add');
        $this->assertSame(PHP_INT_MAX, $sum->invoke($ledger, PHP_INT_MAX - 1, 1));
        $this->denied(fn () => $sum->invoke($ledger, PHP_INT_MAX, 1), 422);
    }

    public static function uncertainCommits(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('uncertainCommits')]
    public function test_ambiguous_commits_quarantine_until_explicit_authoritative_reconciliation(bool $committed): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $store = new class($this->paymentPdo, $committed) extends PaymentStore
        {
            public int $attempts = 0;

            public function __construct(PDO $pdo, private readonly bool $committed)
            {
                parent::__construct($pdo);
            }

            protected function commit(): void
            {
                $this->attempts++;
                if ($this->committed) {
                    parent::commit();
                }
                throw new RuntimeException('PRIVATE lost commit acknowledgement');
            }
        };
        $this->useStore($store);
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(202)->assertExactJson(['status' => 'unresolved']);
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(202);
        $this->getJson($this->ledgerUrl($merchant).'/'.$confirmation.'/reconciliation')->assertStatus(202);
        $this->assertSame(1, $store->attempts);
        $this->assertSame($committed ? 1 : 0, $this->countLedger());
        $this->useStore(new PaymentStore($this->paymentPdo));
        $status = $this->getJson($this->ledgerUrl($merchant).'/'.$confirmation.'/reconciliation')->assertOk()->assertJsonPath('status', $committed ? 'posted' : 'not_recorded')->json();
        $this->assertSame($committed ? 1 : 0, $this->countLedger());
        $receipt = $this->postJournal($merchant, $payment, $confirmation)->assertOk()->json();
        if ($committed) {
            $this->assertSame($status, $receipt);
        }
        $this->postJournal($merchant, $payment, $confirmation)->assertExactJson($receipt);
        $this->assertSame(1, $this->countLedger());
        $this->assertSame(2, $this->countLedger('ledger_postings'));
        $this->assertSame(1, $this->provider->creates);
    }

    public function test_immutable_history_resists_updates_deletes_and_replacement(): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $this->postJournal($merchant, $payment, $confirmation)->assertOk();
        foreach (['ledger_accounts', 'ledger_journals', 'ledger_postings', 'ledger_seals'] as $table) {
            $before = $this->paymentPdo->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);
            foreach (['UPDATE '.$table.' SET '.($table === 'ledger_seals' ? 'journal_id=journal_id' : ($table === 'ledger_postings' ? 'amount_tzs=amount_tzs' : 'id=id')), 'DELETE FROM '.$table, 'INSERT OR REPLACE INTO '.$table.' SELECT * FROM '.$table] as $sql) {
                try {
                    $this->paymentPdo->exec($sql);
                    $this->fail('Ledger mutation must fail.');
                } catch (PDOException) {
                    $this->assertSame($before, $this->paymentPdo->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC));
                }
            }
        }
    }

    public function test_database_rejects_incomplete_unbalanced_invalid_or_foreign_postings(): void
    {
        [, $merchant] = $this->ledgerReady();
        [$firstPayment, $firstConfirmation] = $this->confirmed($merchant);
        $this->postJournal($merchant, $firstPayment, $firstConfirmation)->assertOk();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $accounts = $this->paymentPdo->query('SELECT id,kind FROM ledger_accounts')->fetchAll(PDO::FETCH_KEY_PAIR);
        $byKind = array_flip($accounts);
        foreach (['missing-credit', 'missing-seal', 'unbalanced', 'zero', 'negative', 'fraction', 'overflow', 'currency', 'foreign-account', 'foreign-journal', 'wrong-direction', 'wrong-type', 'wrong-source', 'duplicate-source'] as $case) {
            $this->paymentPdo->beginTransaction();
            try {
                $id = (string) Str::uuid();
                $insert = $this->paymentPdo->prepare('INSERT INTO ledger_journals(id,consumer,confirmation_id,payment_reference,merchant_id,amount_tzs,currency,source_hash,posted_at) VALUES(?,?,?,?,?,?,?,?,?)');
                $insert->execute([$id, MockPaymentLedger::CONSUMER, $case === 'duplicate-source' ? $firstConfirmation : $confirmation, $case === 'wrong-source' ? $firstPayment : ($case === 'duplicate-source' ? $firstPayment : $payment), $case === 'foreign-journal' ? 'foreign' : $merchant->id, 12500, $case === 'currency' ? 'USD' : 'TZS', str_repeat('a', 64), $this->ledgerAdmission->time]);
                if ($case === 'wrong-type') {
                    $badAccount = $this->paymentPdo->prepare('INSERT INTO ledger_accounts(id,merchant_id,kind,account_type,currency,created_at) VALUES(?,?,?,?,?,?)');
                    $badAccount->execute([(string) Str::uuid(), 'foreign', 'merchant_pending_obligation', 'asset', 'TZS', $this->ledgerAdmission->time]);
                }
                foreach ([1 => ['debit', 'simulated_clearing_receivable'], 2 => ['credit', 'merchant_pending_obligation']] as $line => [$direction, $kind]) {
                    if ($case === 'missing-credit' && $line === 2) {
                        continue;
                    }
                    $value = $line === 1 ? 12500 : match ($case) {
                        'unbalanced' => 12501, 'zero' => 0, 'negative' => -1, 'fraction' => 1.5, 'overflow' => PHP_INT_MAX, default => 12500,
                    };
                    $posting = $this->paymentPdo->prepare('INSERT INTO ledger_postings(journal_id,line,account_id,merchant_id,currency,kind,direction,amount_tzs) VALUES(?,?,?,?,?,?,?,?)');
                    $posting->execute([$id, $line, $case === 'foreign-account' ? (string) Str::uuid() : $byKind[$kind], $merchant->id, 'TZS', $kind, $case === 'wrong-direction' ? 'credit' : $direction, $value]);
                }
                if ($case !== 'missing-seal') {
                    $seal = $this->paymentPdo->prepare('INSERT INTO ledger_seals(journal_id) VALUES(?)');
                    $seal->execute([$id]);
                }
                $this->paymentPdo->commit();
                $this->fail('Database must reject '.$case);
            } catch (PDOException) {
                if ($this->paymentPdo->inTransaction()) {
                    $this->paymentPdo->rollBack();
                }
                $this->assertSame(1, $this->countLedger(), $case);
                $this->assertSame(2, $this->countLedger('ledger_postings'), $case);
            }
        }
        $this->postJournal($merchant, $payment, $confirmation)->assertOk();
        $this->assertSame(2, $this->countLedger());
    }

    public function test_fabricated_confirmation_without_confirmed_event_lineage_is_denied(): void
    {
        [, $merchant] = $this->ledgerReady();
        $payment = $this->createPayment($merchant)->assertOk()->json('reference');
        $this->receiveEvent($this->event($payment, 'waiting'))->assertOk();
        $confirmation = (string) Str::uuid();
        $event = $this->paymentPdo->query('SELECT id FROM payment_events')->fetchColumn();
        $insert = $this->paymentPdo->prepare('INSERT INTO payment_confirmations(id,payment_reference,event_id,merchant_id,amount_tzs,currency,confirmed_at) VALUES(?,?,?,?,?,?,?)');
        $insert->execute([$confirmation, $payment, $event, $merchant->id, 12500, 'TZS', $this->paymentAdmission->time]);
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(409);
        $this->getJson($this->ledgerUrl($merchant))->assertStatus(409)->assertDontSee('12500');
        try {
            $journal = $this->paymentPdo->prepare('INSERT INTO ledger_journals(id,consumer,confirmation_id,payment_reference,merchant_id,amount_tzs,currency,source_hash,posted_at) VALUES(?,?,?,?,?,?,?,?,?)');
            $journal->execute([(string) Str::uuid(), MockPaymentLedger::CONSUMER, $confirmation, $payment, $merchant->id, 12500, 'TZS', str_repeat('a', 64), $this->ledgerAdmission->time]);
            $this->fail('Database must require authoritative confirmation lineage.');
        } catch (PDOException) {
            $this->assertSame(0, $this->countLedger());
        }
    }

    public function test_two_owned_connections_contend_then_consume_the_source_once(): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $path = tempnam(sys_get_temp_dir(), 'xlipa-p5-');
        $this->assertNotFalse($path);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($path)));
        $this->assertMatchesRegularExpression('/^(xlipa-p5-|xli)[A-Za-z0-9.]+$/D', basename($path));
        unlink($path);
        $one = $two = null;
        try {
            $this->paymentPdo->exec('VACUUM INTO '.$this->paymentPdo->quote($path));
            $one = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $two = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $first = new PaymentStore($one);
            $second = new PaymentStore($two);
            $one->exec('PRAGMA busy_timeout=0');
            $two->exec('PRAGMA busy_timeout=0');
            $one->exec('BEGIN IMMEDIATE');
            $this->useStore($second);
            $this->postJournal($merchant, $payment, $confirmation)->assertStatus(503);
            $this->assertSame(0, (int) $two->query('SELECT count(*) FROM ledger_journals')->fetchColumn());
            $one->exec('ROLLBACK');
            $this->useStore($first);
            $receipt = $this->postJournal($merchant, $payment, $confirmation)->assertOk()->json();
            $this->useStore($second);
            $this->postJournal($merchant, $payment, $confirmation)->assertOk()->assertExactJson($receipt);
            $this->postJournal($merchant, $payment, $confirmation, ['amount_tzs' => 12501])->assertStatus(409);
            $this->assertSame(1, (int) $two->query('SELECT count(*) FROM ledger_journals')->fetchColumn());
            $this->assertSame(2, (int) $two->query('SELECT count(*) FROM ledger_postings')->fetchColumn());
            $this->assertSame([], $two->query('PRAGMA foreign_key_check')->fetchAll());
        } finally {
            $this->useStore(new PaymentStore($this->paymentPdo));
            unset($first, $second);
            $one = $two = null;
            foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }

    #[DataProvider('uncertainCommits')]
    public function test_authority_acknowledgement_loss_does_not_imply_ledger_failure(bool $committed): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $transaction = new class($committed) extends SubmissionTransaction
        {
            public function __construct(private readonly bool $committed) {}

            protected function commit(Connection $connection): void
            {
                if ($this->committed) {
                    parent::commit($connection);
                }
                throw new RuntimeException('Fictional authority acknowledgement loss');
            }
        };
        app()->instance(SubmissionTransaction::class, $transaction);
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(202)->assertExactJson(['status' => 'unresolved']);
        $this->assertSame(1, $this->countLedger());
        DB::connection()->setPdo($this->ownedPdo);
        app()->instance(SubmissionTransaction::class, new SubmissionTransaction);
        $this->getJson($this->ledgerUrl($merchant).'/'.$confirmation.'/reconciliation')->assertOk()->assertJsonPath('status', 'posted');
        $this->postJournal($merchant, $payment, $confirmation)->assertOk();
        $this->assertSame(1, $this->countLedger());
    }

    public function test_no_operational_routes_grants_or_disabled_constraints_allow_posting(): void
    {
        [, $merchant] = $this->ledgerReady();
        [$payment, $confirmation] = $this->confirmed($merchant);
        $this->paymentPdo->exec('PRAGMA recursive_triggers=OFF');
        $this->postJournal($merchant, $payment, $confirmation)->assertStatus(503);
        $this->assertSame(0, $this->countLedger());
        $ordinary = $this->createApplication();
        $this->assertFalse($ordinary->make(LedgerAdmission::class)->authorized(1, $merchant->id, 'post'));
        foreach ($ordinary->make('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('__isolated-ledger', $route->uri());
        }
    }
}
