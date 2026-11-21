<?php

namespace App\Actions\Payments;

use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/** No live payout binding. Durable claim precedes dispatch; ambiguous commands are never repeated. */
final class MerchantWithdrawals
{
    public function __construct(private readonly LedgerLifecycle $ledger, private readonly FundsAdmission $admission, private readonly FictionalFundsEvidence $evidence, private readonly ReconciledPayoutProvider $provider) {}

    public function request(User $actor, string $merchant, mixed $amount, string $destination, string $key): array
    {
        abort_unless(is_int($amount) && $amount > 0 && $amount <= PaymentEngine::MAX_AMOUNT_TZS && preg_match('/\A[A-Za-z0-9_-]{16,80}\z/D', $key) === 1, 422);

        return $this->ledger->run($actor, $merchant, 'withdraw', function (PaymentStore $store, array $position, int $now) use ($actor, $merchant, $amount, $destination, $key): array {
            $approved = $this->admission->destination($actor->id, $merchant, $destination);
            abort_unless($approved !== null && $approved['reference'] === $destination && is_string($approved['mask']) && strlen($approved['mask']) <= 100, 422);
            $existing = $store->rows('SELECT * FROM funds_withdrawals WHERE merchant_id=? AND request_key=?', [$merchant, $key])[0] ?? null;
            if ($existing !== null) {
                abort_unless($existing['amount_tzs'] === $amount && $existing['destination_reference'] === $destination && $existing['actor_id'] === $actor->id, 409);

                return $existing;
            }
            abort_unless($amount <= $position['simulated_available_tzs'], 409);
            $reference = (string) Str::uuid();
            $store->write('INSERT INTO funds_withdrawals VALUES(?,?,?,?,?,?,?,?)', [$reference, $merchant, $actor->id, $key, $destination, $approved['mask'], $amount, $now]);
            $request = $store->rows('SELECT * FROM funds_withdrawals WHERE reference=?', [$reference])[0];
            $body = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->ledger->post($store, $merchant, 'reserve', $amount, 'reserve_'.$reference, $body, '', $now, null, $reference);

            return $request;
        });
    }

    public function dispatch(User $actor, string $merchant, string $reference): array
    {
        $claim = $this->ledger->run($actor, $merchant, 'dispatch', function (PaymentStore $store, array $position, int $now) use ($merchant, $reference): array {
            $row = $this->withdrawal($store, $merchant, $reference);
            $prior = $store->rows('SELECT * FROM funds_dispatches WHERE withdrawal_reference=?', [$reference]);
            if ($prior !== []) {
                return ['fresh' => false, 'withdrawal' => $row];
            }
            $store->write('INSERT INTO funds_dispatches VALUES(?,?)', [$reference, $now]);

            return ['fresh' => true, 'withdrawal' => $row];
        });
        if (! $claim['fresh']) {
            return ['status' => 'pending_reconciliation', 'reference' => $reference];
        }
        try {
            $this->ledger->run($actor, $merchant, 'dispatch', function () use ($claim): array {
                return $this->provider->create($claim['withdrawal']);
            });

            return ['status' => 'pending', 'reference' => $reference];
        } catch (Throwable) {
            return ['status' => 'unresolved', 'reference' => $reference];
        }
    }

    public function receive(User $actor, string $merchant, string $body, string $signature): array
    {
        return $this->ledger->run($actor, $merchant, 'event', function (PaymentStore $store, array $position, int $now) use ($merchant, $body, $signature): array {
            $facts = $this->evidence->verify('payout', $body, $signature, $now);
            $this->validate($store, $merchant, $facts, $now);
            $existing = $store->rows('SELECT * FROM funds_events WHERE event_id=?', [$facts['event_id']])[0] ?? null;
            if ($existing !== null) {
                abort_unless(hash_equals($existing['body_hash'], hash('sha256', $body)) && $existing['merchant_id'] === $merchant, 409);

                return ['status' => 'recorded', 'reference' => $facts['withdrawal_reference']];
            }
            $store->write('INSERT INTO funds_events VALUES(?,?,?,?,?,?,?,?)', [$facts['event_id'], $facts['withdrawal_reference'], $merchant, $facts['status'], $body, $signature, hash('sha256', $body), $now]);

            return ['status' => 'recorded_pending_reconciliation', 'reference' => $facts['withdrawal_reference']];
        });
    }

    public function reconcile(User $actor, string $merchant, string $reference): array
    {
        return $this->ledger->run($actor, $merchant, 'reconcile', function (PaymentStore $store, array $position, int $now) use ($merchant, $reference): array {
            $row = $this->withdrawal($store, $merchant, $reference);
            $existing = $store->rows('SELECT id,kind FROM ledger_lifecycle_journals WHERE merchant_id=? AND related_withdrawal=?', [$merchant, $reference])[0] ?? null;
            if ($existing !== null) {
                return ['status' => $existing['kind'] === 'payout' ? 'confirmed_fictional_payout' : 'failed_reconciled', 'receipt_id' => $existing['id'], 'reference' => $reference];
            }
            $events = $store->rows('SELECT * FROM funds_events WHERE merchant_id=? AND withdrawal_reference=? ORDER BY received_at,event_id LIMIT 101', [$merchant, $reference]);
            abort_unless(count($events) <= 100, 409);
            $terminal = [];
            foreach ($events as $event) {
                $facts = $this->evidence->verify('payout', $event['body'], $event['signature'], $event['received_at']);
                $this->validate($store, $merchant, $facts, $event['received_at']);
                abort_unless(hash_equals($event['body_hash'], hash('sha256', $event['body'])), 409);
                if (in_array($facts['status'], ['confirmed', 'failed'], true)) {
                    $terminal[$facts['status']] = true;
                }
            }
            if (count($terminal) !== 1) {
                return ['status' => 'unresolved', 'reference' => $reference];
            }
            $query = $this->provider->reconciliationEvidence($reference, $now);
            $facts = $this->evidence->verify('payout', $query['body'], $query['signature'], $now);
            $this->validate($store, $merchant, $facts, $now);
            if (! isset($terminal[$facts['status']])) {
                return ['status' => 'unresolved', 'reference' => $reference];
            }
            $kind = $facts['status'] === 'confirmed' ? 'payout' : 'release';
            $id = $this->ledger->post($store, $merchant, $kind, $row['amount_tzs'], $facts['event_id'], $query['body'], $query['signature'], $now, null, $reference);

            return ['status' => $kind === 'payout' ? 'confirmed_fictional_payout' : 'failed_reconciled', 'receipt_id' => $id, 'reference' => $reference];
        });
    }

    private function withdrawal(PaymentStore $store, string $merchant, string $reference): array
    {
        $row = $store->rows('SELECT * FROM funds_withdrawals WHERE reference=? AND merchant_id=?', [$reference, $merchant])[0] ?? null;
        abort_unless($row !== null, 404);

        return $row;
    }

    private function validate(PaymentStore $store, string $merchant, array $facts, int $now): void
    {
        abort_unless($facts['merchant_id'] === $merchant, 404);
        $row = $this->withdrawal($store, $merchant, $facts['withdrawal_reference']);
        $dispatch = $store->rows('SELECT attempted_at FROM funds_dispatches WHERE withdrawal_reference=?', [$row['reference']])[0] ?? null;
        abort_unless($dispatch !== null && $facts['amount_tzs'] === $row['amount_tzs'] && $facts['destination_reference'] === $row['destination_reference'] && $facts['provider_transaction'] === 'fictional_'.$row['reference'] && $facts['occurred_at'] >= $dispatch['attempted_at'] && $facts['occurred_at'] <= $now, 409);
    }
}
