<?php

namespace App\Actions\Payments;

use App\Models\User;
use Closure;
use Illuminate\Support\Str;

/** Append-only lifecycle extension of P5's authoritative ledger, on the same store/transaction. */
final class LedgerLifecycle
{
    public function __construct(private readonly MockPaymentLedger $ledger, private readonly FundsAdmission $admission, private readonly FictionalFundsEvidence $evidence) {}

    public function run(User $actor, string $merchant, string $operation, Closure $work): array
    {
        abort_unless(PHP_SAPI === 'cli' && getenv('XLIPA_SYNTHETIC_ONLY') === '1' && app()->environment('testing') && $this->admission->authorized($actor->id, $merchant, $operation), 503);

        return $this->ledger->lifecycleTransaction($actor, $merchant, function (PaymentStore $store, array $base) use ($actor, $merchant, $operation, $work): array {
            abort_unless($this->admission->authorized($actor->id, $merchant, $operation), 503);
            $now = $this->admission->milliseconds();
            abort_unless($now >= 0 && $now <= 253402214399999, 503);
            $position = $this->projection($store, $merchant, $base);
            $result = $work($store, $position, $now);
            $this->projection($store, $merchant, $base);
            abort_unless($this->admission->authorized($actor->id, $merchant, $operation) && $this->admission->milliseconds() >= $now, 503);

            return $result;
        });
    }

    public function settle(User $actor, string $merchant, string $body, string $signature): array
    {
        return $this->run($actor, $merchant, 'settle', function (PaymentStore $store, array $position, int $now) use ($merchant, $body, $signature): array {
            $facts = $this->evidence->verify('settlement', $body, $signature, $now);
            abort_unless($facts['merchant_id'] === $merchant, 404);
            $source = $store->rows('SELECT j.*,c.confirmed_at FROM ledger_journals j JOIN ledger_seals s ON s.journal_id=j.id JOIN payment_confirmations c ON c.id=j.confirmation_id WHERE j.merchant_id=? AND j.confirmation_id=? AND j.payment_reference=?', [$merchant, $facts['confirmation_id'], $facts['payment_reference']])[0] ?? null;
            abort_unless($source !== null, 404);
            abort_unless($source['amount_tzs'] === $facts['amount_tzs'] && $facts['occurred_at'] >= $source['posted_at'] && $now >= $facts['occurred_at'], 409);
            $old = $store->rows('SELECT * FROM ledger_lifecycle_journals WHERE source_journal=? OR event_id=?', [$source['id'], $facts['event_id']]);
            if ($old !== []) {
                abort_unless(count($old) === 1 && $old[0]['merchant_id'] === $merchant && hash_equals($old[0]['evidence_hash'], hash('sha256', $body)), 409);

                return ['status' => 'settled_simulated', 'journal_id' => $old[0]['id'], 'amount_tzs' => $old[0]['amount_tzs']];
            }
            $id = $this->post($store, $merchant, 'settlement', $facts['amount_tzs'], $facts['event_id'], $body, $signature, $now, $source['id']);

            return ['status' => 'settled_simulated', 'journal_id' => $id, 'amount_tzs' => $facts['amount_tzs']];
        });
    }

    public function overview(User $actor, string $merchant): array
    {
        return $this->run($actor, $merchant, 'read', function (PaymentStore $store, array $position) use ($merchant): array {
            return ['position' => $position, 'settlements' => $store->rows("SELECT id,amount_tzs,source_journal,posted_at FROM ledger_lifecycle_journals WHERE merchant_id=? AND kind='settlement' ORDER BY posted_at,id LIMIT 100", [$merchant]),
                'withdrawals' => $store->rows("SELECT w.*,CASE WHEN j.kind='payout' THEN 'confirmed_fictional_payout' WHEN j.kind='release' THEN 'failed_reconciled' WHEN EXISTS(SELECT 1 FROM funds_events e WHERE e.withdrawal_reference=w.reference AND e.status='unresolved') THEN 'unresolved' WHEN EXISTS(SELECT 1 FROM funds_events e WHERE e.withdrawal_reference=w.reference AND e.status IN ('confirmed','failed','pending')) THEN 'pending_reconciliation' WHEN EXISTS(SELECT 1 FROM funds_dispatches d WHERE d.withdrawal_reference=w.reference) THEN 'unresolved' ELSE 'reserved' END AS status,j.id AS receipt_id FROM funds_withdrawals w LEFT JOIN ledger_lifecycle_journals j ON j.related_withdrawal=w.reference WHERE w.merchant_id=? ORDER BY w.created_at,w.reference LIMIT 100", [$merchant])];
        });
    }

    public function chiefOverview(User $actor, string $merchant, PaymentStore $store): array
    {
        return $this->ledger->administrativeRead($actor, $merchant, $store, $this->admission, function (PaymentStore $store, array $base) use ($merchant): array {
            $position = $this->projection($store, $merchant, $base);

            return ['position' => $position, 'settlements' => $store->rows("SELECT id,amount_tzs,source_journal,posted_at FROM ledger_lifecycle_journals WHERE merchant_id=? AND kind='settlement' ORDER BY posted_at,id LIMIT 100", [$merchant]),
                'withdrawals' => $store->rows("SELECT w.reference,w.merchant_id,w.destination_mask,w.amount_tzs,w.created_at,CASE WHEN j.kind='payout' THEN 'confirmed_fictional_payout' WHEN j.kind='release' THEN 'failed_reconciled' ELSE 'pending_or_unresolved' END AS status,j.id AS receipt_id FROM funds_withdrawals w LEFT JOIN ledger_lifecycle_journals j ON j.related_withdrawal=w.reference WHERE w.merchant_id=? ORDER BY w.created_at,w.reference LIMIT 100", [$merchant]),
                'exceptions' => $store->rows("SELECT withdrawal_reference,count(DISTINCT status) AS terminal_outcomes FROM funds_events WHERE merchant_id=? AND status IN ('confirmed','failed') GROUP BY withdrawal_reference HAVING count(DISTINCT status)>1 LIMIT 100", [$merchant])];
        });
    }

    public function post(PaymentStore $store, string $merchant, string $kind, int $amount, string $event, string $body, string $signature, int $now, ?string $source = null, ?string $withdrawal = null): string
    {
        abort_unless($amount > 0 && $amount <= PaymentEngine::MAX_AMOUNT_TZS, 422);
        $id = (string) Str::uuid();
        $store->write('INSERT INTO ledger_lifecycle_journals VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [$id, $merchant, $kind, $amount, $source, $kind === 'reserve' ? $withdrawal : null, in_array($kind, ['payout', 'release'], true) ? $withdrawal : null, hash('sha256', $body), $event, $body, $signature, $now]);
        foreach ($this->lines($kind) as $index => [$account,$direction]) {
            $store->write('INSERT INTO ledger_lifecycle_postings VALUES(?,?,?,?,?)', [$id, $index + 1, $account, $direction, $amount]);
        }
        $store->write('INSERT INTO ledger_lifecycle_seals VALUES(?)', [$id]);

        return $id;
    }

    private function lines(string $kind): array
    {
        return match ($kind) {
            'settlement' => [['simulated_settled_cash', 'debit'], ['simulated_clearing_receivable', 'credit'], ['merchant_pending_obligation', 'debit'], ['merchant_available_obligation', 'credit']],
            'reserve' => [['merchant_available_obligation', 'debit'], ['merchant_reserved_obligation', 'credit']],
            'release' => [['merchant_reserved_obligation', 'debit'], ['merchant_available_obligation', 'credit']],
            'payout' => [['merchant_reserved_obligation', 'debit'], ['simulated_settled_cash', 'credit']],
        };
    }

    private function projection(PaymentStore $store, string $merchant, array $base): array
    {
        $events = $store->rows('SELECT e.*,w.amount_tzs,w.destination_reference,d.attempted_at FROM funds_events e JOIN funds_withdrawals w ON w.reference=e.withdrawal_reference AND w.merchant_id=e.merchant_id LEFT JOIN funds_dispatches d ON d.withdrawal_reference=w.reference WHERE e.merchant_id=? ORDER BY e.event_id LIMIT 10001', [$merchant]);
        abort_unless(count($events) <= 10000, 422);
        foreach ($events as $event) {
            $facts = $this->evidence->verify('payout', $event['body'], $event['signature'], $event['received_at']);
            abort_unless(hash_equals($event['body_hash'], hash('sha256', $event['body'])) && $facts['event_id'] === $event['event_id'] && $facts['merchant_id'] === $merchant && $facts['withdrawal_reference'] === $event['withdrawal_reference'] && $facts['amount_tzs'] === $event['amount_tzs'] && $facts['destination_reference'] === $event['destination_reference'] && $facts['status'] === $event['status'] && $facts['provider_transaction'] === 'fictional_'.$event['withdrawal_reference'] && $event['attempted_at'] !== null && $facts['occurred_at'] >= $event['attempted_at'] && $facts['occurred_at'] <= $event['received_at'], 409);
        }
        $sums = ['settlement' => 0, 'reserve' => 0, 'release' => 0, 'payout' => 0];
        $after = '';
        do {
            $rows = $store->rows('SELECT j.*,s.journal_id AS seal FROM ledger_lifecycle_journals j LEFT JOIN ledger_lifecycle_seals s ON s.journal_id=j.id WHERE j.merchant_id=? AND j.id>? ORDER BY j.id LIMIT 100', [$merchant, $after]);
            foreach ($rows as $row) {
                $after = $row['id'];
                abort_unless($row['seal'] === $row['id'] && is_int($row['amount_tzs']) && $row['amount_tzs'] > 0 && hash_equals($row['evidence_hash'], hash('sha256', $row['body'])), 409);
                $expected = [];
                foreach ($this->lines($row['kind']) as [$account,$direction]) {
                    $expected[] = ['account' => $account, 'direction' => $direction, 'amount_tzs' => $row['amount_tzs']];
                }
                abort_unless($store->rows('SELECT account,direction,amount_tzs FROM ledger_lifecycle_postings WHERE journal_id=? ORDER BY line', [$row['id']]) === $expected, 409);
                if ($row['kind'] === 'settlement') {
                    $facts = $this->evidence->verify('settlement', $row['body'], $row['signature'], $row['posted_at']);
                    $source = $store->rows('SELECT * FROM ledger_journals WHERE id=? AND merchant_id=?', [$row['source_journal'], $merchant])[0] ?? null;
                    abort_unless($source !== null && $facts['merchant_id'] === $merchant && $facts['confirmation_id'] === $source['confirmation_id'] && $facts['payment_reference'] === $source['payment_reference'] && $facts['amount_tzs'] === $row['amount_tzs'] && $facts['event_id'] === $row['event_id'] && $source['amount_tzs'] === $row['amount_tzs'], 409);
                } else {
                    $reference = $row['withdrawal_reference'] ?? $row['related_withdrawal'];
                    $request = $store->rows('SELECT * FROM funds_withdrawals WHERE reference=? AND merchant_id=?', [$reference, $merchant])[0] ?? null;
                    abort_unless($request !== null && $request['amount_tzs'] === $row['amount_tzs'], 409);
                    if ($row['kind'] !== 'reserve') {
                        $facts = $this->evidence->verify('payout', $row['body'], $row['signature'], $row['posted_at']);
                        abort_unless($facts['merchant_id'] === $merchant && $facts['withdrawal_reference'] === $reference && $facts['destination_reference'] === $request['destination_reference'] && $facts['amount_tzs'] === $row['amount_tzs'] && $facts['status'] === ($row['kind'] === 'payout' ? 'confirmed' : 'failed'), 409);
                    } else {
                        abort_unless($row['body'] === json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 409);
                    }
                }
                abort_unless($sums[$row['kind']] <= PHP_INT_MAX - $row['amount_tzs'], 422);
                $sums[$row['kind']] += $row['amount_tzs'];
            }
        } while (count($rows) === 100);
        $available = $sums['settlement'] - $sums['reserve'] + $sums['release'];
        $reserved = $sums['reserve'] - $sums['release'] - $sums['payout'];
        abort_unless($sums['settlement'] <= $base['pending_merchant_obligation_tzs'] && $available >= 0 && $reserved >= 0 && $sums['payout'] <= $sums['settlement'] && $available + $reserved === $sums['settlement'] - $sums['payout'], 409);

        return ['currency' => 'TZS', 'settled_simulated_tzs' => $sums['settlement'], 'pending_obligation_tzs' => $base['pending_merchant_obligation_tzs'] - $sums['settlement'], 'reserved_tzs' => $reserved, 'simulated_available_tzs' => $available, 'completed_fictional_payout_tzs' => $sums['payout'], 'operational_available_tzs' => 0, 'reconciliation' => 'balanced'];
    }
}
