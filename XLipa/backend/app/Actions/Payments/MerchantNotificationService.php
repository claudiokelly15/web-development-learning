<?php

namespace App\Actions\Payments;

use App\Models\User;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Durable financial event sweep. No transport, scheduler or custody is activated by this class. */
final class MerchantNotificationService
{
    private Encrypter $cipher;

    public function __construct(private readonly MockPaymentLedger $ledger, private readonly LedgerLifecycle $funds, private readonly Closure $clock,
        #[\SensitiveParameter] string $custodyKey, private readonly NotificationTransport $transport = new DeniedNotificationTransport,
        private readonly ?Closure $verifiedSmsRecipient = null)
    {
        abort_unless(strlen($custodyKey) === 32, 503);
        $this->cipher = new Encrypter($custodyKey, 'AES-256-CBC');
    }

    private function now(): int
    {
        $now = ($this->clock)();
        abort_unless(is_int($now) && $now >= 0 && $now <= 253402214399999, 503);

        return $now;
    }

    private function recipient(string $merchant, string $channel): ?string
    {
        $owner = DB::table('merchants')->join('users', 'users.id', '=', 'merchants.created_by_user_id')
            ->join('merchant_memberships', function ($join): void {
                $join->on('merchant_memberships.merchant_id', '=', 'merchants.id')->on('merchant_memberships.user_id', '=', 'users.id');
            })->where('merchant_memberships.role', 'owner')->where('merchant_memberships.status', 'active')->whereNull('merchant_memberships.revoked_at')
            ->where('merchants.id', $merchant)->where('users.account_status', 'active')->whereNotNull('users.email_verified_at')
            ->select(['users.id', 'users.email', 'users.email_binding_version'])->first();
        abort_unless($owner !== null, 409);
        if ($channel === 'email') {
            abort_unless(filter_var($owner->email, FILTER_VALIDATE_EMAIL) !== false && preg_match('/[\r\n]/', $owner->email) === 0, 409);

            return $owner->email;
        }
        $recipient = $this->verifiedSmsRecipient === null ? null : ($this->verifiedSmsRecipient)($merchant, $owner->id);
        abort_unless($recipient === null || (is_string($recipient) && preg_match('/\A\+[1-9][0-9]{7,14}\z/D', $recipient) === 1), 409);

        return $recipient;
    }

    public function preferences(User $actor, string $merchant, ?string $mode = null): array
    {
        abort_unless($mode === null || in_array($mode, ['individual', 'daily', 'weekly', 'off'], true), 422);

        return $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($actor, $merchant, $mode): array {
            if ($mode !== null) {
                $store->write('INSERT INTO p10_preferences(merchant_id,actor_id,mode,occurred_at) VALUES(?,?,?,?)', [$merchant, $actor->id, $mode, $this->now()]);
            }

            return ['mode' => $this->mode($store, $merchant), 'notification_fee_tzs' => 0];
        });
    }

    /** Independently admitted Chief scope, never an owner capability or an unscoped platform scan. */
    public function chiefHistory(User $actor, string $merchant, PaymentStore $store, FundsAdmission $admission): array
    {
        return $this->ledger->administrativeRead($actor, $merchant, $store, $admission, fn (PaymentStore $store): array => [
            'merchant_id' => $merchant,
            'states' => $store->rows('SELECT channel,status,count(*) AS count FROM p10_deliveries WHERE merchant_id=? GROUP BY channel,status ORDER BY channel,status', [$merchant]),
            'recent' => $store->rows('SELECT delivery_id,attempt,status,reason,occurred_at FROM p10_history WHERE merchant_id=? ORDER BY occurred_at DESC,id LIMIT 100', [$merchant]),
        ]);
    }

    private function mode(PaymentStore $store, string $merchant): string
    {
        return $store->rows('SELECT mode FROM p10_preferences WHERE merchant_id=? ORDER BY sequence DESC LIMIT 1', [$merchant])[0]['mode'] ?? 'individual';
    }

    /** Cutoffs use EAT observation days and Monday-start weeks, including delayed discovery. */
    private function due(string $mode): int
    {
        $date = (new DateTimeImmutable('@'.intdiv($this->now(), 1000)))->setTimezone(new DateTimeZone('Africa/Nairobi'));
        $end = $mode === 'weekly' ? $date->modify('monday next week')->setTime(0, 0) : $date->modify('+1 day')->setTime(0, 0);

        return $end->getTimestamp() * 1000;
    }

    private function paymentPolicy(PaymentStore $store, string $merchant, string $event): void
    {
        if ($store->rows('SELECT event_id FROM p10_payment_modes WHERE event_id=?', [$event]) !== []) {
            return;
        }
        $existingDelivery = $store->rows('SELECT id FROM p10_deliveries WHERE event_id=? AND merchant_id=?', [$event, $merchant]);
        $mode = $existingDelivery === [] ? $this->mode($store, $merchant) : 'individual';
        $due = in_array($mode, ['daily', 'weekly'], true) ? $this->due($mode) : $this->now();
        $store->write('INSERT INTO p10_payment_modes VALUES(?,?,?,?)', [$event, $merchant, $mode, $due]);
        if ($mode === 'individual' && $existingDelivery === []) {
            $this->enqueue($store, $merchant, $event, 'email');
        }
    }

    private function digests(PaymentStore $store, string $merchant): void
    {
        $groups = $store->rows("SELECT m.mode,m.due_at FROM p10_payment_modes m WHERE m.merchant_id=? AND m.mode IN ('daily','weekly') AND m.due_at<=? AND NOT EXISTS(SELECT 1 FROM p10_batch_members b WHERE b.event_id=m.event_id) GROUP BY m.mode,m.due_at ORDER BY m.due_at,m.mode", [$merchant, $this->now()]);
        foreach ($groups as $group) {
            $events = $store->rows('SELECT e.id,e.payload FROM p10_events e JOIN p10_payment_modes m ON m.event_id=e.id AND m.merchant_id=e.merchant_id WHERE m.merchant_id=? AND m.mode=? AND m.due_at=? ORDER BY e.occurred_at,e.id', [$merchant, $group['mode'], $group['due_at']]);
            $total = 0;
            $lines = [];
            foreach ($events as $event) {
                $facts = json_decode($event['payload'], true, flags: JSON_THROW_ON_ERROR);
                abort_unless(is_int($facts['amount_tzs']) && $facts['amount_tzs'] > 0 && $total <= PHP_INT_MAX - $facts['amount_tzs'], 409);
                $total += $facts['amount_tzs'];
                $lines[] = $facts['reference'].' | TZS '.$facts['amount_tzs'].' | '.self::eat($facts['occurred_at']);
            }
            $delivery = $this->enqueue($store, $merchant, $events[0]['id'], 'email');
            $batch = (string) Str::uuid();
            $payload = json_encode(['subject' => 'XLipa - '.ucfirst($group['mode']).' payment digest', 'text' => ucfirst($group['mode']).' confirmed-payment digest. Merchant: '.$merchant."\nObservation period ending: ".self::eat($group['due_at'])."\nCount: ".count($events)."\nConfirmed gross TZS: ".$total."\n".implode("\n", $lines)."\nConfirmed payments are not settled or withdrawable funds. Notifications are free. Synthetic evidence; no real money movement."], JSON_THROW_ON_ERROR);
            $store->write('INSERT INTO p10_batches VALUES(?,?,?,?,?,?)', [$batch, $merchant, $group['mode'], $group['due_at'], $delivery, $payload]);
            foreach ($events as $event) {
                $store->write('INSERT INTO p10_batch_members VALUES(?,?)', [$event['id'], $batch]);
            }
        }
    }

    public function sweep(User $actor, string $merchant): array
    {
        $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant): array {
            $rows = $store->rows('SELECT id,payment_reference,amount_tzs,confirmed_at FROM payment_confirmations WHERE merchant_id=? ORDER BY id LIMIT 1001', [$merchant]);
            abort_unless(count($rows) <= 1000, 422, 'Notification sweep requires a reviewed larger-batch policy.');
            foreach ($rows as $row) {
                $event = $this->record($store, $merchant, 'payment.confirmed', $row['id'], ['reference' => $row['payment_reference'], 'amount_tzs' => $row['amount_tzs'], 'status' => 'Confirmed payment; settlement is separate', 'occurred_at' => $row['confirmed_at'], 'destination_mask' => null, 'evidence' => $row['id']], []);
                $this->paymentPolicy($store, $merchant, $event);
            }
            $this->digests($store, $merchant);

            return [];
        });
        $this->funds->run($actor, $merchant, 'read', function (PaymentStore $store) use ($merchant): array {
            $rows = $store->rows('SELECT w.*,j.id AS payout_id,j.posted_at,j.body FROM funds_withdrawals w LEFT JOIN ledger_lifecycle_journals j ON j.related_withdrawal=w.reference AND j.kind=\'payout\' JOIN ledger_lifecycle_seals s ON s.journal_id=(SELECT id FROM ledger_lifecycle_journals WHERE withdrawal_reference=w.reference) WHERE w.merchant_id=? ORDER BY w.reference LIMIT 1001', [$merchant]);
            abort_unless(count($rows) <= 1000, 422, 'Notification sweep requires a reviewed larger-batch policy.');
            foreach ($rows as $row) {
                $payload = ['reference' => $row['reference'], 'amount_tzs' => $row['amount_tzs'], 'status' => 'Withdrawal requested; payout NOT confirmed', 'occurred_at' => $row['created_at'], 'destination_mask' => $row['destination_mask'], 'evidence' => $row['reference']];
                $this->record($store, $merchant, 'withdrawal.requested', $row['reference'], $payload, ['email', 'sms']);
                if ($row['payout_id'] !== null) {
                    $facts = json_decode($row['body'], true, flags: JSON_THROW_ON_ERROR);
                    $receiptPayload = [...$payload, 'status' => 'Independently confirmed fictional payout', 'occurred_at' => $facts['occurred_at'], 'evidence' => $row['payout_id']];
                    $event = $this->record($store, $merchant, 'payout.confirmed', $row['payout_id'], $receiptPayload, ['email', 'sms']);
                    $this->storedDocument($store, $merchant, 'payout', $event, 'Confirmed payout receipt', ['Merchant: '.$merchant, 'Evidence: '.$row['payout_id'], ...explode("\n", $this->message('payout.confirmed', $receiptPayload)['text'])]);
                }
            }

            return [];
        });

        return $this->history($actor, $merchant);
    }

    private function record(PaymentStore $store, string $merchant, string $kind, string $source, array $payload, array $channels): string
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $old = $store->rows('SELECT id,payload_hash FROM p10_events WHERE merchant_id=? AND kind=? AND source_id=?', [$merchant, $kind, $source]);
        if ($old !== []) {
            abort_unless(hash_equals($old[0]['payload_hash'], hash('sha256', $body)), 409);

            return $old[0]['id'];
        }
        $id = (string) Str::uuid();
        $store->write('INSERT INTO p10_events VALUES(?,?,?,?,?,?,?,?)', [$id, $merchant, $kind, $source, $body, hash('sha256', $body), $payload['occurred_at'], $this->now()]);
        foreach ($channels as $channel) {
            $this->enqueue($store, $merchant, $id, $channel);
        }

        return $id;
    }

    private function enqueue(PaymentStore $store, string $merchant, string $id, string $channel): string
    {
        $recipient = $this->recipient($merchant, $channel);
        $delivery = (string) Str::uuid();
        $status = $recipient === null ? 'permanently_failed' : 'pending';
        $store->write('INSERT INTO p10_deliveries VALUES(?,?,?,?,?,?,?,0,?,NULL,NULL)', [$delivery, $merchant, $id, $channel, $recipient === null ? null : $this->cipher->encryptString($recipient), $recipient === null ? null : hash('sha256', $recipient), $status, $this->now()]);
        $this->audit($store, $merchant, $delivery, 0, $status, $recipient === null ? 'Verified SMS contact unavailable' : 'Event recorded; delivery not attempted');

        return $delivery;
    }

    private function audit(PaymentStore $store, string $merchant, string $delivery, int $attempt, string $status, string $reason): void
    {
        $store->write('INSERT INTO p10_history VALUES(?,?,?,?,?,?,?)', [(string) Str::uuid(), $merchant, $delivery, $attempt, $status, $reason, $this->now()]);
    }

    public function history(User $actor, string $merchant): array
    {
        return $this->ledger->financialRead($actor, $merchant, fn (PaymentStore $store): array => [
            'events' => $store->rows('SELECT id,kind,source_id,payload,occurred_at FROM p10_events WHERE merchant_id=? ORDER BY occurred_at DESC,id LIMIT 200', [$merchant]),
            'deliveries' => $store->rows('SELECT id,event_id,channel,status,attempts,next_at FROM p10_deliveries WHERE merchant_id=? ORDER BY id LIMIT 500', [$merchant]),
            'history' => $store->rows('SELECT delivery_id,attempt,status,reason,occurred_at FROM p10_history WHERE merchant_id=? ORDER BY occurred_at,id LIMIT 1000', [$merchant]),
            'preferences' => ['mode' => $this->mode($store, $merchant), 'notification_fee_tzs' => 0],
            'digests' => $store->rows('SELECT id,mode,due_at,delivery_id FROM p10_batches WHERE merchant_id=? ORDER BY due_at DESC LIMIT 100', [$merchant]),
        ]);
    }

    public function deliver(User $actor, string $merchant, string $delivery): array
    {
        $claim = $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant, $delivery): array {
            $row = $store->rows('SELECT d.*,e.kind,e.payload FROM p10_deliveries d JOIN p10_events e ON e.id=d.event_id AND e.merchant_id=d.merchant_id WHERE d.id=? AND d.merchant_id=?', [$delivery, $merchant])[0] ?? null;
            abort_unless($row !== null, 404);
            if (in_array($row['status'], ['delivered', 'permanently_failed'], true) || $row['next_at'] > $this->now()) {
                return [];
            }
            if ($row['kind'] === 'payment.confirmed' && $this->mode($store, $merchant) === 'off') {
                $store->write("UPDATE p10_deliveries SET status='permanently_failed' WHERE id=?", [$delivery]);
                $this->audit($store, $merchant, $delivery, $row['attempts'], 'permanently_failed', 'Optional payment email disabled by merchant preference');

                return [];
            }
            if ($row['attempts'] >= 3) {
                $store->write("UPDATE p10_deliveries SET status='permanently_failed' WHERE id=?", [$delivery]);
                $this->audit($store, $merchant, $delivery, 3, 'permanently_failed', 'Attempts exhausted; delivery unconfirmed');

                return [];
            }
            $recipient = $this->recipient($merchant, $row['channel']);
            if ($recipient === null || ! hash_equals($row['recipient_hash'] ?? '', hash('sha256', $recipient))) {
                $store->write("UPDATE p10_deliveries SET status='permanently_failed' WHERE id=?", [$delivery]);
                $this->audit($store, $merchant, $delivery, $row['attempts'], 'permanently_failed', 'Recipient unavailable or changed');

                return [];
            }
            $claim = (string) Str::uuid();
            $attempt = $row['attempts'] + 1;
            $store->write("UPDATE p10_deliveries SET status='queued',attempts=?,claim=?,next_at=? WHERE id=?", [$attempt, $claim, $this->now() + 60000, $delivery]);
            $this->audit($store, $merchant, $delivery, $attempt, 'queued', 'Durable delivery claim; NOT delivery confirmation');

            $batch = $store->rows('SELECT payload FROM p10_batches WHERE delivery_id=? AND merchant_id=?', [$delivery, $merchant])[0] ?? null;

            return [...$row, 'digest' => $batch === null ? null : json_decode($batch['payload'], true, flags: JSON_THROW_ON_ERROR), 'claim' => $claim, 'attempts' => $attempt, 'recipient_plain' => $this->cipher->decryptString($row['recipient'])];
        });
        if ($claim !== []) {
            $template = $claim['digest'] ?? $this->message($claim['kind'], json_decode($claim['payload'], true, flags: JSON_THROW_ON_ERROR));
            try {
                $result = $this->transport->send($claim['channel'], $claim['recipient_plain'], $template['subject'], $template['text'], $delivery);
                abort_unless(in_array($result['status'] ?? null, ['delivered', 'queued', 'accepted', 'failed'], true) && (($result['status'] !== 'delivered') || (is_string($result['receipt'] ?? null) && preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D', $result['receipt']) === 1)), 503);
            } catch (Throwable) {
                $result = ['status' => 'failed', 'receipt' => null];
            }
            $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant, $delivery, $claim, $result): array {
                $status = match ($result['status']) {
                    'failed' => $claim['attempts'] >= 3 ? 'permanently_failed' : 'retry_scheduled',
                    'accepted' => 'queued',
                    default => $result['status'],
                };
                if ($result['status'] === 'accepted') {
                    $this->audit($store, $merchant, $delivery, $claim['attempts'], 'provider_accepted', 'Provider accepted the message; recipient delivery remains unconfirmed');
                }
                if ($result['status'] === 'failed') {
                    $this->audit($store, $merchant, $delivery, $claim['attempts'], 'failed', 'Transport did not confirm delivery');
                }
                $changed = $store->write('UPDATE p10_deliveries SET status=?,receipt=?,next_at=? WHERE id=? AND merchant_id=? AND claim=? AND status=\'queued\'', [$status, $result['status'] === 'delivered' ? $result['receipt'] : null, $this->now() + ($status === 'queued' ? 60000 : 1000 * (2 ** $claim['attempts'])), $delivery, $merchant, $claim['claim']]);
                abort_unless($changed === 1, 409);
                $this->audit($store, $merchant, $delivery, $claim['attempts'], $status, $status === 'delivered' ? 'Transport confirmed delivery' : 'Delivery not confirmed');

                return [];
            });
        }

        return $this->history($actor, $merchant);
    }

    public function message(string $kind, array $payload): array
    {
        $title = match ($kind) {
            'payment.confirmed' => 'Confirmed payment','withdrawal.requested' => 'Withdrawal request advice','payout.confirmed' => 'Confirmed payout receipt',default => abort(422)
        };
        $text = $title."\nReference: ".$payload['reference']."\nAmount: TZS ".number_format($payload['amount_tzs'], 0, '.', ',')."\nStatus: ".$payload['status']."\nTime: ".self::eat($payload['occurred_at']);
        if ($payload['destination_mask'] !== null) {
            $text .= "\nDestination: ".$payload['destination_mask'];
        }
        $text .= "\nNotifications are free: TZS 0. Confirmed payment value is not settled or withdrawable funds. Operational activation remains disabled for these synthetic records.";

        return ['subject' => 'XLipa - '.$title, 'text' => $text];
    }

    public static function eat(int $milliseconds): string
    {
        return (new DateTimeImmutable('@'.intdiv($milliseconds, 1000)))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d H:i:s').' EAT';
    }

    public function document(User $actor, string $merchant, string $event): array
    {
        return $this->funds->run($actor, $merchant, 'read', function (PaymentStore $store) use ($actor, $merchant, $event): array {
            $row = $store->rows("SELECT * FROM p10_events WHERE id=? AND merchant_id=? AND kind IN ('withdrawal.requested','payout.confirmed')", [$event, $merchant])[0] ?? null;
            abort_unless($row !== null && hash_equals($row['payload_hash'], hash('sha256', $row['payload'])), 404);
            $payload = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
            if ($row['kind'] === 'payout.confirmed') {
                $source = $store->rows("SELECT w.reference,w.amount_tzs,w.destination_mask,j.body FROM ledger_lifecycle_journals j JOIN ledger_lifecycle_seals s ON s.journal_id=j.id JOIN funds_withdrawals w ON w.reference=j.related_withdrawal AND w.merchant_id=j.merchant_id WHERE j.id=? AND j.merchant_id=? AND j.kind='payout'", [$row['source_id'], $merchant])[0] ?? null;
                abort_unless($source !== null, 409);
                $facts = json_decode($source['body'], true, flags: JSON_THROW_ON_ERROR);
                $occurred = $facts['occurred_at'];
            } else {
                $source = $store->rows("SELECT w.reference,w.amount_tzs,w.destination_mask,w.created_at FROM funds_withdrawals w JOIN ledger_lifecycle_journals j ON j.withdrawal_reference=w.reference AND j.merchant_id=w.merchant_id JOIN ledger_lifecycle_seals s ON s.journal_id=j.id WHERE w.reference=? AND w.merchant_id=? AND j.kind='reserve'", [$row['source_id'], $merchant])[0] ?? null;
                abort_unless($source !== null, 409);
                $occurred = $source['created_at'];
            }
            abort_unless($payload['reference'] === $source['reference'] && $payload['amount_tzs'] === $source['amount_tzs'] && $payload['destination_mask'] === $source['destination_mask'] && $payload['occurred_at'] === $occurred && $payload['evidence'] === $row['source_id'], 409);
            $title = $row['kind'] === 'payout.confirmed' ? 'Confirmed payout receipt' : 'Withdrawal request advice';
            $document = $this->storedDocument($store, $merchant, $row['kind'] === 'payout.confirmed' ? 'payout' : 'advice', $row['id'], $title, ['Merchant: '.$merchant, 'Evidence: '.$payload['evidence'], ...explode("\n", $this->message($row['kind'], $payload)['text'])]);
            $pdf = $document['content'];
            $store->write('INSERT INTO p10_downloads VALUES(?,?,?,?,?,?)', [(string) Str::uuid(), $merchant, $actor->id, $row['id'], hash('sha256', $pdf), $this->now()]);

            return ['reference' => $row['id'], 'content' => $pdf];
        });
    }

    private function storedDocument(PaymentStore $store, string $merchant, string $kind, string $source, string $title, array $lines): array
    {
        $old = $store->rows('SELECT id,content,content_hash FROM p10_documents WHERE merchant_id=? AND kind=? AND source_id=?', [$merchant, $kind, $source])[0] ?? null;
        if ($old !== null) {
            abort_unless(hash_equals($old['content_hash'], hash('sha256', $old['content'])), 409);

            return ['reference' => $old['id'], 'content' => $old['content']];
        }
        $id = (string) Str::uuid();
        $pdf = FinancialReportExport::pdf($title, ['Document: '.$id, ...$lines]);
        $store->write('INSERT INTO p10_documents VALUES(?,?,?,?,?,?,?)', [$id, $merchant, $kind, $source, $pdf, hash('sha256', $pdf), $this->now()]);

        return ['reference' => $id, 'content' => $pdf];
    }
}
