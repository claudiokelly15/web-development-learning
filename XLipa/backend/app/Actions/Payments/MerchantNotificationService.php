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
            ->join('merchant_memberships',function ($join): void {
                $join->on('merchant_memberships.merchant_id','=','merchants.id')->on('merchant_memberships.user_id','=','users.id');
            })->where('merchant_memberships.role','owner')->where('merchant_memberships.status','active')->whereNull('merchant_memberships.revoked_at')
            ->where('merchants.id', $merchant)->where('users.account_status', 'active')->whereNotNull('users.email_verified_at')
            ->select(['users.id','users.email','users.email_binding_version'])->first();
        abort_unless($owner !== null, 409);
        if ($channel === 'email') {
            abort_unless(filter_var($owner->email, FILTER_VALIDATE_EMAIL) !== false && preg_match('/[\r\n]/', $owner->email) === 0, 409);

            return $owner->email;
        }
        $recipient = $this->verifiedSmsRecipient === null ? null : ($this->verifiedSmsRecipient)($merchant, $owner->id);
        abort_unless($recipient === null || (is_string($recipient) && preg_match('/\A\+[1-9][0-9]{7,14}\z/D', $recipient) === 1), 409);

        return $recipient;
    }

    public function sweep(User $actor, string $merchant): array
    {
        $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant): array {
            $rows = $store->rows('SELECT id,payment_reference,amount_tzs,confirmed_at FROM payment_confirmations WHERE merchant_id=? ORDER BY id LIMIT 1001', [$merchant]);
            abort_unless(count($rows) <= 1000, 422, 'Notification sweep requires a reviewed larger-batch policy.');
            foreach ($rows as $row) {
                $this->record($store, $merchant, 'payment.confirmed', $row['id'], ['reference'=>$row['payment_reference'],'amount_tzs'=>$row['amount_tzs'],'status'=>'Confirmed payment; settlement is separate','occurred_at'=>$row['confirmed_at'],'destination_mask'=>null,'evidence'=>$row['id']], ['email']);
            }

            return [];
        });
        $this->funds->run($actor, $merchant, 'read', function (PaymentStore $store) use ($merchant): array {
            $rows = $store->rows('SELECT w.*,j.id AS payout_id,j.posted_at,j.body FROM funds_withdrawals w LEFT JOIN ledger_lifecycle_journals j ON j.related_withdrawal=w.reference AND j.kind=\'payout\' JOIN ledger_lifecycle_seals s ON s.journal_id=(SELECT id FROM ledger_lifecycle_journals WHERE withdrawal_reference=w.reference) WHERE w.merchant_id=? ORDER BY w.reference LIMIT 1001', [$merchant]);
            abort_unless(count($rows) <= 1000, 422, 'Notification sweep requires a reviewed larger-batch policy.');
            foreach ($rows as $row) {
                $payload = ['reference'=>$row['reference'],'amount_tzs'=>$row['amount_tzs'],'status'=>'Withdrawal requested; payout NOT confirmed','occurred_at'=>$row['created_at'],'destination_mask'=>$row['destination_mask'],'evidence'=>$row['reference']];
                $this->record($store, $merchant, 'withdrawal.requested', $row['reference'], $payload, ['email','sms']);
                if ($row['payout_id'] !== null) {
                    $facts = json_decode($row['body'], true, flags: JSON_THROW_ON_ERROR);
                    $this->record($store, $merchant, 'payout.confirmed', $row['payout_id'], [...$payload,'status'=>'Independently confirmed fictional payout','occurred_at'=>$facts['occurred_at'],'evidence'=>$row['payout_id']], ['email','sms']);
                }
            }

            return [];
        });

        return $this->history($actor, $merchant);
    }

    private function record(PaymentStore $store, string $merchant, string $kind, string $source, array $payload, array $channels): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $old = $store->rows('SELECT payload_hash FROM p10_events WHERE merchant_id=? AND kind=? AND source_id=?', [$merchant,$kind,$source]);
        if ($old !== []) {
            abort_unless(hash_equals($old[0]['payload_hash'], hash('sha256', $body)), 409);

            return;
        }
        $id = (string) Str::uuid();
        $store->write('INSERT INTO p10_events VALUES(?,?,?,?,?,?,?,?)', [$id,$merchant,$kind,$source,$body,hash('sha256', $body),$payload['occurred_at'],$this->now()]);
        foreach ($channels as $channel) {
            $recipient = $this->recipient($merchant, $channel);
            $delivery = (string) Str::uuid();
            $status = $recipient === null ? 'permanently_failed' : 'pending';
            $store->write('INSERT INTO p10_deliveries VALUES(?,?,?,?,?,?,?,0,?,NULL,NULL)', [$delivery,$merchant,$id,$channel,$recipient === null ? null : $this->cipher->encryptString($recipient),$recipient === null ? null : hash('sha256', $recipient),$status,$this->now()]);
            $this->audit($store, $merchant, $delivery, 0, $status, $recipient === null ? 'Verified SMS contact unavailable' : 'Event recorded; delivery not attempted');
        }
    }

    private function audit(PaymentStore $store, string $merchant, string $delivery, int $attempt, string $status, string $reason): void
    {
        $store->write('INSERT INTO p10_history VALUES(?,?,?,?,?,?,?)', [(string) Str::uuid(),$merchant,$delivery,$attempt,$status,$reason,$this->now()]);
    }

    public function history(User $actor, string $merchant): array
    {
        return $this->ledger->financialRead($actor, $merchant, fn (PaymentStore $store): array => [
            'events'=>$store->rows('SELECT id,kind,source_id,payload,occurred_at FROM p10_events WHERE merchant_id=? ORDER BY occurred_at DESC,id LIMIT 200', [$merchant]),
            'deliveries'=>$store->rows('SELECT id,event_id,channel,status,attempts,next_at FROM p10_deliveries WHERE merchant_id=? ORDER BY id LIMIT 500', [$merchant]),
            'history'=>$store->rows('SELECT delivery_id,attempt,status,reason,occurred_at FROM p10_history WHERE merchant_id=? ORDER BY occurred_at,id LIMIT 1000', [$merchant]),
        ]);
    }

    public function deliver(User $actor, string $merchant, string $delivery): array
    {
        $claim = $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant,$delivery): array {
            $row = $store->rows('SELECT d.*,e.kind,e.payload FROM p10_deliveries d JOIN p10_events e ON e.id=d.event_id AND e.merchant_id=d.merchant_id WHERE d.id=? AND d.merchant_id=?', [$delivery,$merchant])[0] ?? null;
            abort_unless($row !== null, 404);
            if (in_array($row['status'], ['delivered','permanently_failed'], true) || $row['next_at'] > $this->now()) {
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
            $store->write("UPDATE p10_deliveries SET status='queued',attempts=?,claim=?,next_at=? WHERE id=?", [$attempt,$claim,$this->now()+60000,$delivery]);
            $this->audit($store, $merchant, $delivery, $attempt, 'queued', 'Durable delivery claim; NOT delivery confirmation');

            return [...$row,'claim'=>$claim,'attempts'=>$attempt,'recipient_plain'=>$this->cipher->decryptString($row['recipient'])];
        });
        if ($claim !== []) {
            $template = $this->message($claim['kind'], json_decode($claim['payload'], true, flags: JSON_THROW_ON_ERROR));
            try {
                $result = $this->transport->send($claim['channel'], $claim['recipient_plain'], $template['subject'], $template['text'], $delivery);
                abort_unless(in_array($result['status'] ?? null, ['delivered','queued','failed'], true) && (($result['status'] !== 'delivered') || (is_string($result['receipt'] ?? null) && preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D', $result['receipt']) === 1)), 503);
            } catch (Throwable) {
                $result = ['status'=>'failed','receipt'=>null];
            }
            $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant,$delivery,$claim,$result): array {
                $status = $result['status'] === 'failed' ? ($claim['attempts'] >= 3 ? 'permanently_failed' : 'retry_scheduled') : $result['status'];
                if ($result['status'] === 'failed') {
                    $this->audit($store, $merchant, $delivery, $claim['attempts'], 'failed', 'Transport did not confirm delivery');
                }
                $changed = $store->write('UPDATE p10_deliveries SET status=?,receipt=?,next_at=? WHERE id=? AND merchant_id=? AND claim=? AND status=\'queued\'', [$status,$result['status'] === 'delivered' ? $result['receipt'] : null,$this->now()+($status === 'queued' ? 60000 : 1000*(2 ** $claim['attempts'])),$delivery,$merchant,$claim['claim']]);
                abort_unless($changed === 1, 409);
                $this->audit($store, $merchant, $delivery, $claim['attempts'], $status, $status === 'delivered' ? 'Transport confirmed delivery' : 'Delivery not confirmed');

                return [];
            });
        }

        return $this->history($actor, $merchant);
    }

    public function message(string $kind, array $payload): array
    {
        $title = match ($kind) {'payment.confirmed'=>'Confirmed payment','withdrawal.requested'=>'Withdrawal request advice','payout.confirmed'=>'Confirmed payout receipt',default=>abort(422)};
        $text = $title."\nReference: ".$payload['reference']."\nAmount: TZS ".number_format($payload['amount_tzs'], 0, '.', ',')."\nStatus: ".$payload['status']."\nTime: ".self::eat($payload['occurred_at']);
        if ($payload['destination_mask'] !== null) {
            $text .= "\nDestination: ".$payload['destination_mask'];
        }
        $text .= "\nConfirmed payment value is not settled or withdrawable funds. Operational activation remains disabled for these synthetic records.";

        return ['subject'=>'XLipa - '.$title,'text'=>$text];
    }

    public static function eat(int $milliseconds): string
    {
        return (new DateTimeImmutable('@'.intdiv($milliseconds,1000)))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d H:i:s').' EAT';
    }

    public function document(User $actor, string $merchant, string $event): array
    {
        return $this->funds->run($actor, $merchant, 'read', function (PaymentStore $store) use ($actor,$merchant,$event): array {
            $row = $store->rows("SELECT * FROM p10_events WHERE id=? AND merchant_id=? AND kind IN ('withdrawal.requested','payout.confirmed')", [$event,$merchant])[0] ?? null;
            abort_unless($row !== null && hash_equals($row['payload_hash'], hash('sha256',$row['payload'])), 404);
            $payload = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
            if ($row['kind'] === 'payout.confirmed') {
                $source = $store->rows("SELECT w.reference,w.amount_tzs,w.destination_mask,j.body FROM ledger_lifecycle_journals j JOIN ledger_lifecycle_seals s ON s.journal_id=j.id JOIN funds_withdrawals w ON w.reference=j.related_withdrawal AND w.merchant_id=j.merchant_id WHERE j.id=? AND j.merchant_id=? AND j.kind='payout'", [$row['source_id'],$merchant])[0] ?? null;
                abort_unless($source !== null, 409);
                $facts = json_decode($source['body'], true, flags: JSON_THROW_ON_ERROR);
                $occurred = $facts['occurred_at'];
            } else {
                $source = $store->rows("SELECT w.reference,w.amount_tzs,w.destination_mask,w.created_at FROM funds_withdrawals w JOIN ledger_lifecycle_journals j ON j.withdrawal_reference=w.reference AND j.merchant_id=w.merchant_id JOIN ledger_lifecycle_seals s ON s.journal_id=j.id WHERE w.reference=? AND w.merchant_id=? AND j.kind='reserve'", [$row['source_id'],$merchant])[0] ?? null;
                abort_unless($source !== null, 409);
                $occurred = $source['created_at'];
            }
            abort_unless($payload['reference'] === $source['reference'] && $payload['amount_tzs'] === $source['amount_tzs'] && $payload['destination_mask'] === $source['destination_mask'] && $payload['occurred_at'] === $occurred && $payload['evidence'] === $row['source_id'],409);
            $title = $row['kind'] === 'payout.confirmed' ? 'Confirmed payout receipt' : 'Withdrawal request advice';
            $pdf = FinancialReportExport::pdf($title, ['Document: '.$row['id'],'Merchant: '.$merchant,'Evidence: '.$payload['evidence'],...explode("\n",$this->message($row['kind'],$payload)['text'])]);
            $store->write('INSERT INTO p10_downloads VALUES(?,?,?,?,?,?)', [(string) Str::uuid(),$merchant,$actor->id,$row['id'],hash('sha256',$pdf),$this->now()]);

            return ['reference'=>$row['id'],'content'=>$pdf];
        });
    }
}
