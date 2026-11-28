<?php

namespace App\Actions\Payments;

use App\Actions\Merchants\SubmissionTransaction;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Staging\StagingFinancialAuthority;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Fictional pending accounting only. Fees, settlement, withdrawals and corrections have no admission. */
final class MockPaymentLedger
{
    public const CONSUMER = 'mock-payment-accounting-v1';

    private const SOURCE_SQL = 'SELECT c.*,p.actor_id,p.auth_version,p.membership_id,p.authority_version,p.application_id,p.provider,p.status,p.dispatch_state,p.provider_reference,p.created_at,p.last_sequence,
        p.amount_tzs AS payment_amount,p.currency AS payment_currency,p.merchant_id AS payment_merchant,
        e.provider AS event_provider,e.provider_event_id,e.payment_reference AS event_payment,e.body_hash,e.raw_body,e.normalized_status,e.outcome,e.sequence,e.occurred_at,e.received_at';

    private const SOURCE_FROM = ' FROM payment_confirmations c JOIN payment_intents p ON p.reference=c.payment_reference JOIN payment_events e ON e.id=c.event_id';

    public function __construct(private readonly PaymentEngine $payments, private readonly LedgerAdmission $admission) {}

    public function post(User $actor, string $merchant, string $confirmation, string $payment, mixed $amount, string $currency): array
    {
        $this->validateAmount($amount, $currency);

        return $this->run($actor, $merchant, 'post', function (PaymentStore $store, array $authority, int $time) use ($merchant, $confirmation, $payment, $amount, $currency): array {
            $source = $this->source($store, $merchant, $confirmation);
            foreach ($authority as $field => $value) {
                abort_unless($source[$field] === $value, 404);
            }
            abort_unless($source['payment_reference'] === $payment && $source['amount_tzs'] === $amount && $source['currency'] === $currency, 409);
            $hash = $this->sourceHash($source);
            $existing = $this->receipt($store, $confirmation);
            if ($existing !== null) {
                abort_unless($existing['merchant_id'] === $merchant && hash_equals($existing['source_hash'], $hash), 409);

                return $this->safeReceipt($existing);
            }
            abort_unless($time >= $source['confirmed_at'], 409);
            foreach (['simulated_clearing_receivable' => 'asset', 'merchant_pending_obligation' => 'liability'] as $kind => $type) {
                if ($store->rows('SELECT id FROM ledger_accounts WHERE merchant_id=? AND kind=? AND currency=?', [$merchant, $kind, 'TZS']) === []) {
                    $store->write('INSERT INTO ledger_accounts(id,merchant_id,kind,account_type,currency,created_at) VALUES(?,?,?,?,?,?)', [(string) Str::uuid(), $merchant, $kind, $type, 'TZS', $time]);
                }
            }
            $id = (string) Str::uuid();
            $store->write('INSERT INTO ledger_journals(id,consumer,confirmation_id,payment_reference,merchant_id,amount_tzs,currency,source_hash,posted_at) VALUES(?,?,?,?,?,?,?,?,?)', [$id, self::CONSUMER, $confirmation, $payment, $merchant, $source['amount_tzs'], 'TZS', $hash, $time]);
            foreach ([1 => ['debit', 'simulated_clearing_receivable'], 2 => ['credit', 'merchant_pending_obligation']] as $line => [$direction, $kind]) {
                $account = $store->rows('SELECT id FROM ledger_accounts WHERE merchant_id=? AND kind=? AND currency=?', [$merchant, $kind, 'TZS'])[0];
                $store->write('INSERT INTO ledger_postings(journal_id,line,account_id,merchant_id,currency,kind,direction,amount_tzs) VALUES(?,?,?,?,?,?,?,?)', [$id, $line, $account['id'], $merchant, 'TZS', $kind, $direction, $source['amount_tzs']]);
            }
            $store->write('INSERT INTO ledger_seals(journal_id) VALUES(?)', [$id]);

            return $this->safeReceipt($this->receipt($store, $confirmation));
        });
    }

    /** Explicit read of durable outcome on a trusted reopened store; never retries a posting. */
    public function reconcile(User $actor, string $merchant, string $confirmation): array
    {
        return $this->run($actor, $merchant, 'reconcile', function (PaymentStore $store) use ($merchant, $confirmation): array {
            $source = $this->source($store, $merchant, $confirmation);
            $receipt = $this->receipt($store, $confirmation);
            if ($receipt === null) {
                return ['status' => 'not_recorded', 'confirmation_id' => $confirmation];
            }
            abort_unless($receipt['merchant_id'] === $merchant && hash_equals($receipt['source_hash'], $this->sourceHash($source)), 409);

            return $this->safeReceipt($receipt);
        });
    }

    public function position(User $actor, string $merchant): array
    {
        return $this->run($actor, $merchant, 'read', function (PaymentStore $store) use ($merchant): array {
            $confirmed = 0;
            foreach ($store->rows('SELECT id FROM payment_confirmations WHERE merchant_id=? ORDER BY id', [$merchant]) as $row) {
                $source = $this->source($store, $merchant, $row['id']);
                $confirmed = $this->add($confirmed, $source['amount_tzs']);
            }
            $pending = $clearing = 0;
            $history = [];
            foreach ($store->rows('SELECT j.* FROM ledger_journals j JOIN ledger_seals s ON s.journal_id=j.id WHERE j.merchant_id=? ORDER BY j.posted_at,j.id', [$merchant]) as $journal) {
                $source = $this->source($store, $merchant, $journal['confirmation_id']);
                abort_unless(hash_equals($journal['source_hash'], $this->sourceHash($source)), 409);
                $postings = $store->rows('SELECT direction,kind,amount_tzs FROM ledger_postings WHERE journal_id=? ORDER BY line', [$journal['id']]);
                abort_unless($postings === [
                    ['direction' => 'debit', 'kind' => 'simulated_clearing_receivable', 'amount_tzs' => $journal['amount_tzs']],
                    ['direction' => 'credit', 'kind' => 'merchant_pending_obligation', 'amount_tzs' => $journal['amount_tzs']],
                ], 409);
                $clearing = $this->add($clearing, $postings[0]['amount_tzs']);
                $pending = $this->add($pending, $postings[1]['amount_tzs']);
                $history[] = $this->safeReceipt($journal);
            }

            return ['currency' => 'TZS', 'confirmed_fictional_payment_tzs' => $confirmed, 'pending_merchant_obligation_tzs' => $pending,
                'simulated_clearing_receivable_tzs' => $clearing, 'withdrawable_tzs' => 0, 'settlement_status' => 'unproven', 'journals' => $history];
        });
    }

    /** Internal financial projection, under the same current P4/P5 authority and store snapshot. */
    public function financialRead(User $actor, string $merchant, Closure $work): array
    {
        return $this->run($actor, $merchant, 'read', fn (PaymentStore $store): array => $work($store, $this->summary($store, $merchant)));
    }

    /** Independent, explicitly granted Chief read boundary. Never grants merchant or payout authority. */
    public function administrativeRead(User $actor, string $merchant, PaymentStore $store, FundsAdmission $admission, Closure $work): array
    {
        $guard = function () use ($actor, $merchant, $admission): void {
            abort_unless(PHP_SAPI === 'cli' && getenv('XLIPA_SYNTHETIC_ONLY') === '1' && app()->environment('testing') && auth()->id() === $actor->id && $admission->authorized($actor->id, $merchant, 'admin_read'), 503);
            $current = User::query()->findOrFail($actor->id);
            abort_unless($current->getRawOriginal('account_status') === 'active' && $current->email_verified_at !== null && $current->auth_version === $actor->auth_version && session('auth_version') === $current->auth_version && $current->email_binding_version === $actor->email_binding_version, 403);
            Gate::forUser($current)->authorize('viewChiefWorkspace', PlatformRoleAssignment::class);
            abort_unless(DB::table('merchants')->where('id', $merchant)->where('created_by_user_id', '!=', $actor->id)->exists()
                && ! DB::table('merchant_memberships')->where('merchant_id', $merchant)->where('user_id', $actor->id)->exists()
                && ! DB::table('merchant_applications')->where('merchant_id', $merchant)->where('submitted_by_user_id', $actor->id)->exists(), 404);
        };
        $guard();

        return app(SubmissionTransaction::class)->run(function () use ($store, $merchant, $work, $guard): array {
            $guard();

            return $store->transaction(function () use ($store, $merchant, $work, $guard): array {
                $result = $work($store, $this->summary($store, $merchant));
                $guard();

                return $result;
            });
        });
    }

    /** Internal additive accounting boundary; callers must also enforce independent lifecycle admission. */
    public function lifecycleTransaction(User $actor, string $merchant, Closure $work): array
    {
        return $this->run($actor, $merchant, 'read', fn (PaymentStore $store, array $authority): array => $work($store, $this->summary($store, $merchant), $authority));
    }

    /** Bounded evidence batches; no per-payment queries or mutable balance projection. */
    private function summary(PaymentStore $store, string $merchant): array
    {
        $confirmed = $pending = $clearing = $sources = $journals = 0;
        $after = '';
        do {
            $rows = $store->rows(self::SOURCE_SQL.',j.id AS journal_id,j.consumer AS journal_consumer,j.merchant_id AS journal_merchant,j.payment_reference AS journal_payment,j.amount_tzs AS journal_amount,j.currency AS journal_currency,j.source_hash AS journal_hash,s.journal_id AS seal,
                d.direction AS debit_direction,d.kind AS debit_kind,d.amount_tzs AS debit_amount,d.merchant_id AS debit_merchant,d.currency AS debit_currency,
                k.direction AS credit_direction,k.kind AS credit_kind,k.amount_tzs AS credit_amount,k.merchant_id AS credit_merchant,k.currency AS credit_currency'
                .self::SOURCE_FROM.' LEFT JOIN ledger_journals j ON j.confirmation_id=c.id LEFT JOIN ledger_seals s ON s.journal_id=j.id
                LEFT JOIN ledger_postings d ON d.journal_id=j.id AND d.line=1 LEFT JOIN ledger_postings k ON k.journal_id=j.id AND k.line=2
                WHERE c.merchant_id=? AND c.id>? ORDER BY c.id LIMIT 100', [$merchant, $after]);
            foreach ($rows as $row) {
                $this->validateSource($row, $merchant);
                $confirmed = $this->add($confirmed, $row['amount_tzs']);
                $sources++;
                $after = $row['id'];
                if ($row['journal_id'] === null) {
                    continue;
                }
                abort_unless($row['journal_consumer'] === self::CONSUMER && $row['journal_merchant'] === $merchant && $row['journal_payment'] === $row['payment_reference']
                    && $row['journal_amount'] === $row['amount_tzs'] && $row['journal_currency'] === 'TZS' && $row['seal'] === $row['journal_id']
                    && hash_equals($row['journal_hash'], $this->sourceHash($row))
                    && [$row['debit_direction'], $row['debit_kind'], $row['debit_amount'], $row['debit_merchant'], $row['debit_currency']] === ['debit', 'simulated_clearing_receivable', $row['amount_tzs'], $merchant, 'TZS']
                    && [$row['credit_direction'], $row['credit_kind'], $row['credit_amount'], $row['credit_merchant'], $row['credit_currency']] === ['credit', 'merchant_pending_obligation', $row['amount_tzs'], $merchant, 'TZS'], 409);
                $clearing = $this->add($clearing, $row['debit_amount']);
                $pending = $this->add($pending, $row['credit_amount']);
                $journals++;
            }
        } while (count($rows) === 100);
        $counts = $store->rows('SELECT (SELECT count(*) FROM payment_confirmations WHERE merchant_id=?) AS sources,(SELECT count(*) FROM ledger_journals WHERE merchant_id=?) AS journals', [$merchant, $merchant])[0];
        abort_unless($counts['sources'] === $sources && $counts['journals'] === $journals && $confirmed >= $pending, 409);

        return ['currency' => 'TZS', 'confirmed_fictional_payment_tzs' => $confirmed, 'pending_merchant_obligation_tzs' => $pending,
            'simulated_clearing_receivable_tzs' => $clearing, 'confirmed_unaccounted_tzs' => $confirmed - $pending, 'withdrawable_tzs' => 0, 'settlement_status' => 'unproven'];
    }

    private function run(User $actor, string $merchant, string $operation, Closure $work): array
    {
        abort_unless(((PHP_SAPI === 'cli' && getenv('XLIPA_SYNTHETIC_ONLY') === '1' && app()->environment('testing')) || StagingFinancialAuthority::allows(['r24.ledger.post', 'r24.ledger.reconcile', 'r24.transactions.read'])) && $this->admission->authorized($actor->id, $merchant, $operation), 503);

        return $this->payments->accountingTransaction($actor, $merchant, function (PaymentStore $store, array $authority) use ($actor, $merchant, $operation, $work): array {
            abort_unless($this->admission->authorized($actor->id, $merchant, $operation), 503);
            $time = $this->admission->milliseconds();
            abort_unless($time >= 0 && $time <= 253402214399999, 503);
            $result = $work($store, $authority, $time);
            abort_unless($this->admission->authorized($actor->id, $merchant, $operation) && $this->admission->milliseconds() >= $time, 503);

            return $result;
        });
    }

    private function source(PaymentStore $store, string $merchant, string $confirmation): array
    {
        $rows = $store->rows(self::SOURCE_SQL.self::SOURCE_FROM.' WHERE c.id=? AND c.merchant_id=?', [$confirmation, $merchant]);
        abort_unless(count($rows) === 1, 404);

        return $this->validateSource($rows[0], $merchant);
    }

    private function validateSource(array $row, string $merchant): array
    {
        $this->validateAmount($row['amount_tzs'], $row['currency']);
        abort_unless($row['provider'] === 'fictional_mock' && $row['event_provider'] === $row['provider'] && $row['status'] === 'confirmed' && $row['dispatch_state'] === 'attempted'
            && $row['normalized_status'] === 'confirmed' && $row['outcome'] === 'applied' && $row['payment_merchant'] === $merchant
            && $row['payment_amount'] === $row['amount_tzs'] && $row['payment_currency'] === $row['currency'] && $row['event_payment'] === $row['payment_reference']
            && $row['sequence'] <= $row['last_sequence'] && $row['occurred_at'] >= $row['created_at'] && $row['confirmed_at'] === $row['received_at'], 409);
        if ($row['raw_body'] !== null) {
            abort_unless(hash_equals($row['body_hash'], hash('sha256', $row['raw_body'])), 409);
            $body = json_decode($row['raw_body'], true, 16, JSON_THROW_ON_ERROR);
            abort_unless($body === ['event_id' => $row['provider_event_id'], 'reference' => $row['payment_reference'], 'correlation' => $row['provider_reference'],
                'amount_tzs' => $row['amount_tzs'], 'currency' => 'TZS', 'result' => 'ok', 'sequence' => $row['sequence'], 'occurred_at' => $row['occurred_at']], 409);
        }

        return $row;
    }

    private function sourceHash(array $source): string
    {
        return hash('sha256', json_encode(array_intersect_key($source, array_flip(['id', 'payment_reference', 'event_id', 'merchant_id', 'amount_tzs', 'currency', 'confirmed_at',
            'actor_id', 'auth_version', 'membership_id', 'authority_version', 'application_id', 'provider', 'provider_reference', 'provider_event_id', 'body_hash', 'sequence', 'occurred_at'])), JSON_THROW_ON_ERROR));
    }

    private function receipt(PaymentStore $store, string $confirmation): ?array
    {
        return $store->rows('SELECT j.* FROM ledger_journals j JOIN ledger_seals s ON s.journal_id=j.id WHERE j.consumer=? AND j.confirmation_id=?', [self::CONSUMER, $confirmation])[0] ?? null;
    }

    private function safeReceipt(array $journal): array
    {
        return ['status' => 'posted', ...array_intersect_key($journal, array_flip(['id', 'confirmation_id', 'payment_reference', 'merchant_id', 'amount_tzs', 'currency', 'posted_at']))];
    }

    private function validateAmount(mixed $amount, string $currency): void
    {
        abort_unless(is_int($amount) && $amount > 0 && $amount <= PaymentEngine::MAX_AMOUNT_TZS && $currency === 'TZS', 422);
    }

    private function add(int $total, int $amount): int
    {
        abort_unless($amount > 0 && $total >= 0 && $total <= PHP_INT_MAX - $amount, 422);

        return $total + $amount;
    }
}
