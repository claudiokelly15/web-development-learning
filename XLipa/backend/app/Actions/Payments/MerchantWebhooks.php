<?php

namespace App\Actions\Payments;

use App\Models\User;
use Closure;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

/** Durable isolated outbox, bounded at-least-once delivery and receiver deduplication. */
final class MerchantWebhooks
{
    private Encrypter $cipher;

    public function __construct(private readonly MerchantDeveloperTools $tools, private readonly PaymentStore $store, private readonly Closure $resolver, #[\SensitiveParameter] string $custodyKey, private readonly FictionalWebhookTransport $transport)
    {
        abort_unless(strlen($custodyKey) === 32, 503);
        $this->cipher = new Encrypter($custodyKey, 'AES-256-CBC');
    }

    public function destination(string $url): string
    {
        $this->tools->guard();
        $parts = parse_url($url);
        abort_unless(strlen($url) <= 2048 && preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 0 && is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['fragment']) && ! isset($parts['query'])
            && (! isset($parts['port']) || $parts['port'] === 443), 422);
        $host = $parts['host'] ?? '';
        abort_unless(preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/D', $host) === 1, 422);
        $addresses = ($this->resolver)($host);
        abort_unless(is_array($addresses) && $addresses !== [] && count($addresses) <= 16, 422);
        foreach ($addresses as $address) {
            abort_unless(is_string($address) && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                && ! str_contains($address, ':') && ! str_starts_with($address, '100.64.') && ! str_starts_with($address, '169.254.'), 422);
            $first = (int) explode('.', $address)[0];
            $second = (int) explode('.', $address)[1];
            abort_unless($first > 0 && $first < 224 && ! ($first === 100 && $second >= 64 && $second <= 127) && ! ($first === 198 && in_array($second, [18, 19], true)), 422);
        }

        return $addresses[0];
    }

    public function register(User $actor, string $merchant, string $url): array
    {
        $this->destination($url);

        return $this->tools->manage($actor, $merchant, function (PaymentStore $store) use ($merchant, $url): array {
            abort_unless(count($store->rows('SELECT id FROM merchant_webhook_endpoints WHERE merchant_id=?', [$merchant])) < 5, 422);
            $id = (string) Str::uuid();
            $secret = bin2hex(random_bytes(32));
            $store->write('INSERT INTO merchant_webhook_endpoints VALUES(?,?,?,?,1,?)', [$id, $merchant, $url, $this->cipher->encryptString($secret), $this->tools->now()]);

            return ['id' => $id, 'secret' => $secret, 'url' => $url];
        });
    }

    public function disable(User $actor, string $merchant, string $id): void
    {
        $this->tools->manage($actor, $merchant, function (PaymentStore $store) use ($merchant, $id): void {
            abort_unless($store->rows('SELECT id FROM merchant_webhook_endpoints WHERE id=? AND merchant_id=?', [$id, $merchant]) !== [], 404);
            $store->write('UPDATE merchant_webhook_endpoints SET enabled=0 WHERE id=?', [$id]);
        });
    }

    /** Sweep confirmed P4 facts; caller-supplied payment states never enter the outbox. */
    public function enqueue(User $actor, string $merchant): array
    {
        return $this->tools->manage($actor, $merchant, function (PaymentStore $store) use ($merchant): array {
            foreach ($store->rows('SELECT id,payment_reference,amount_tzs,currency FROM payment_confirmations WHERE merchant_id=?', [$merchant]) as $confirmation) {
                $existing = $store->rows('SELECT id FROM merchant_webhook_events WHERE confirmation_id=?', [$confirmation['id']]);
                if ($existing !== []) {
                    continue;
                }
                $id = (string) Str::uuid();
                $body = json_encode(['id' => $id, 'type' => 'payment.confirmed', 'api_version' => 'v1', 'created_at' => $this->tools->now(), 'data' => $confirmation], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $store->write('INSERT INTO merchant_webhook_events VALUES(?,?,?,?,?)', [$id, $merchant, $confirmation['id'], $body, $this->tools->now()]);
                foreach ($store->rows('SELECT id FROM merchant_webhook_endpoints WHERE merchant_id=? AND enabled=1', [$merchant]) as $endpoint) {
                    $store->write("INSERT INTO merchant_webhook_deliveries VALUES(?,?,?,?,'pending',0,?,NULL,NULL)", [(string) Str::uuid(), $merchant, $id, $endpoint['id'], $this->tools->now()]);
                }
            }

            return $store->rows('SELECT id,event_id,status,attempts,next_at,http_status FROM merchant_webhook_deliveries WHERE merchant_id=?', [$merchant]);
        });
    }

    public function deliver(User $actor, string $merchant, string $id): array
    {
        $claim = $this->tools->manage($actor, $merchant, function (PaymentStore $store) use ($merchant, $id): ?array {
            $row = $store->rows('SELECT d.*,e.url,e.encrypted_secret,e.enabled,v.body FROM merchant_webhook_deliveries d JOIN merchant_webhook_endpoints e ON e.id=d.endpoint_id JOIN merchant_webhook_events v ON v.id=d.event_id WHERE d.id=? AND d.merchant_id=?', [$id, $merchant])[0] ?? null;
            abort_unless($row !== null, 404);
            if ($row['enabled'] !== 1) {
                $store->write("UPDATE merchant_webhook_deliveries SET status='disabled' WHERE id=? AND status IN ('pending','retry','sending')", [$id]);

                return null;
            }
            if ($row['status'] === 'sending' && $row['next_at'] <= $this->tools->now()) {
                $store->write('UPDATE merchant_webhook_deliveries SET status=? WHERE id=?', [$row['attempts'] >= 3 ? 'exhausted' : 'retry', $id]);
                $row['status'] = $row['attempts'] >= 3 ? 'exhausted' : 'retry';
            }
            if (! in_array($row['status'], ['pending', 'retry'], true) || $row['next_at'] > $this->tools->now() || $row['attempts'] >= 3) {
                return null;
            }
            $address = $this->destination($row['url']);
            $attempt = (string) Str::uuid();
            $number = $row['attempts'] + 1;
            $store->write("UPDATE merchant_webhook_deliveries SET status='sending',attempts=?,claim=?,next_at=? WHERE id=?", [$number, $attempt, $this->tools->now() + 60000, $id]);
            $store->write('INSERT INTO merchant_webhook_attempts VALUES(?,?,?,?,NULL)', [$attempt, $id, $number, $this->tools->now()]);

            return [...$row, 'claim' => $attempt, 'attempts' => $number, 'address' => $address];
        });
        if ($claim !== null) {
            $timestamp = intdiv($this->tools->now(), 1000);
            $secret = $this->cipher->decryptString($claim['encrypted_secret']);
            $headers = ['X-XLipa-Event' => $claim['event_id'], 'X-XLipa-Delivery' => $id, 'X-XLipa-Timestamp' => (string) $timestamp,
                'X-XLipa-Signature' => 'v1='.hash_hmac('sha256', $timestamp.'.'.$claim['body'], $secret), 'Content-Type' => 'application/json'];
            $status = $this->transport->send($claim['url'], $claim['address'], $claim['body'], $headers);
            $this->tools->manage($actor, $merchant, function (PaymentStore $store) use ($claim, $id, $status): void {
                $success = $status >= 200 && $status < 300;
                $store->write('UPDATE merchant_webhook_deliveries SET status=?,http_status=?,next_at=? WHERE id=? AND claim=? AND status=\'sending\'', [$success ? 'delivered' : ($claim['attempts'] >= 3 ? 'exhausted' : 'retry'), $status, $this->tools->now() + (1000 * (2 ** $claim['attempts'])), $id, $claim['claim']]);
                $store->write('UPDATE merchant_webhook_attempts SET http_status=? WHERE id=?', [$status, $claim['claim']]);
            });
        }

        return $this->tools->manage($actor, $merchant, fn (PaymentStore $store): array => $store->rows('SELECT id,event_id,status,attempts,next_at,http_status FROM merchant_webhook_deliveries WHERE id=? AND merchant_id=?', [$id, $merchant])[0]);
    }

    /** Receiver example: reserve event id before business work in the same durable transaction. */
    public function accept(string $body, array $headers, #[\SensitiveParameter] string $secret, Closure $businessWork): bool
    {
        $this->tools->guard();
        abort_unless(strlen($body) <= 16384 && strlen($secret) === 64, 400);
        $timestamp = $headers['X-XLipa-Timestamp'] ?? '';
        abort_unless(is_string($timestamp) && preg_match('/\A[0-9]{1,12}\z/D', $timestamp) === 1 && abs(intdiv($this->tools->now(), 1000) - (int) $timestamp) <= 300
            && hash_equals('v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret), $headers['X-XLipa-Signature'] ?? ''), 401);
        $event = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($event) && is_string($event['id'] ?? null) && $event['id'] === ($headers['X-XLipa-Event'] ?? null) && ($event['type'] ?? null) === 'payment.confirmed' && ($event['api_version'] ?? null) === 'v1', 400);

        return $this->store->transaction(function () use ($event, $body, $businessWork): bool {
            $existing = $this->store->rows('SELECT body_hash FROM merchant_webhook_receipts WHERE event_id=?', [$event['id']]);
            if ($existing !== []) {
                abort_unless(hash_equals($existing[0]['body_hash'], hash('sha256', $body)), 409);

                return false;
            }
            $this->store->write('INSERT INTO merchant_webhook_receipts VALUES(?,?,?)', [$event['id'], hash('sha256', $body), $this->tools->now()]);
            $businessWork($this->store, $event);

            return true;
        });
    }
}
