<?php

namespace App\Actions\Payments;

use LogicException;
use RuntimeException;

/** Fictional protocol only. No HTTP client, real credentials or provider network. */
final class FictionalMockProvider implements PaymentProvider
{
    public int $creates = 0;

    public string $scenario = 'pending';

    private array $accepted = [];

    public function __construct(#[\SensitiveParameter] private readonly string $key)
    {
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || strlen($key) !== 32) {
            throw new LogicException('Fictional provider requires isolated testing.');
        }
    }

    public function id(): string
    {
        return 'fictional_mock';
    }

    public function capabilities(): array
    {
        return ['create' => true, 'query' => true, 'events' => true, 'refunds' => false, 'payouts' => false];
    }

    public function create(array $payment): array
    {
        $this->creates++;
        $response = ['reference' => $payment['reference'], 'correlation' => 'mock_'.$payment['reference'], 'amount_tzs' => $payment['amount_tzs'], 'currency' => $payment['currency'], 'status' => 'pending'];
        $this->accepted[$payment['reference']] = $response;
        if ($this->scenario === 'unknown') {
            throw new RuntimeException('Fictional accepted request lost acknowledgement.');
        }
        if ($this->scenario === 'malformed') {
            return [...$response, 'amount_tzs' => 12.5];
        }

        return $response;
    }

    public function query(array $payment): array
    {
        return $this->accepted[$payment['reference']] ?? throw new RuntimeException('No authoritative fictional request.');
    }

    public function signature(string $body): string
    {
        return hash_hmac('sha256', $body, $this->key);
    }

    public function eventBody(string $reference, string $result, int $sequence, int $time, string $eventId): string
    {
        $payment = $this->accepted[$reference] ?? throw new RuntimeException('No fictional request.');

        return json_encode(['event_id' => $eventId, 'reference' => $reference, 'correlation' => $payment['correlation'], 'amount_tzs' => $payment['amount_tzs'],
            'currency' => $payment['currency'], 'result' => $result, 'sequence' => $sequence, 'occurred_at' => $time], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function verifyEvent(string $body, string $signature, int $now): array
    {
        abort_unless(strlen($body) <= 4096 && preg_match('/\A[a-f0-9]{64}\z/D', $signature) === 1 && hash_equals($this->signature($body), $signature), 401);
        $event = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        $keys = ['event_id', 'reference', 'correlation', 'amount_tzs', 'currency', 'result', 'sequence', 'occurred_at'];
        abort_unless(is_array($event) && array_keys($event) === $keys && json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) === $body, 422);
        foreach (['event_id', 'reference'] as $field) {
            abort_unless(is_string($event[$field]) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $event[$field]) === 1, 422);
        }
        abort_unless(is_string($event['correlation']) && preg_match('/\A[A-Za-z0-9_-]{1,80}\z/D', $event['correlation']) === 1
            && is_int($event['amount_tzs']) && $event['currency'] === 'TZS' && is_int($event['sequence']) && $event['sequence'] > 0
            && is_int($event['occurred_at']) && $event['occurred_at'] >= $now - 300000 && $event['occurred_at'] <= $now + 30000, 422);
        $status = match ($event['result']) {
            'waiting' => 'pending', 'ok' => 'confirmed', 'declined' => 'failed', 'uncertain' => 'unknown', default => null,
        };
        abort_unless($status !== null, 422);

        return ['id' => $event['event_id'], 'reference' => $event['reference'], 'correlation' => $event['correlation'], 'amount_tzs' => $event['amount_tzs'], 'currency' => $event['currency'], 'status' => $status, 'sequence' => $event['sequence'], 'occurred_at' => $event['occurred_at']];
    }
}
