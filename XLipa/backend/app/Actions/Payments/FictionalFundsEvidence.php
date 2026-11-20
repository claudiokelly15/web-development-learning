<?php

namespace App\Actions\Payments;

use LogicException;

/** Independent fictional protocol. Keys/domains are separate from P4 payment confirmation. */
final class FictionalFundsEvidence
{
    public function __construct(#[\SensitiveParameter] private readonly string $settlementKey, #[\SensitiveParameter] private readonly string $payoutKey)
    {
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || strlen($settlementKey) !== 32 || strlen($payoutKey) !== 32 || hash_equals($settlementKey, $payoutKey)) {
            throw new LogicException('Independent fictional evidence keys require isolated testing.');
        }
    }

    public function signature(string $domain, string $body): string
    {
        abort_unless(in_array($domain, ['settlement', 'payout'], true), 422);

        return hash_hmac('sha256', 'xlipa-fictional-'.$domain.'-v1:'.$body, $domain === 'settlement' ? $this->settlementKey : $this->payoutKey);
    }

    public function body(array $facts): string
    {
        return json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function verify(string $domain, string $body, string $signature, int $now): array
    {
        abort_unless(strlen($body) <= 4096 && preg_match('/\A[a-f0-9]{64}\z/D', $signature) === 1 && hash_equals($this->signature($domain, $body), $signature), 401);
        $facts = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        $keys = $domain === 'settlement'
            ? ['event_id', 'merchant_id', 'payment_reference', 'confirmation_id', 'amount_tzs', 'currency', 'provider_transaction', 'occurred_at']
            : ['event_id', 'merchant_id', 'withdrawal_reference', 'destination_reference', 'amount_tzs', 'currency', 'provider_transaction', 'status', 'occurred_at'];
        abort_unless(is_array($facts) && array_keys($facts) === $keys && $this->body($facts) === $body, 422);
        foreach ($keys as $field) {
            if (! in_array($field, ['amount_tzs', 'occurred_at'], true)) {
                abort_unless(is_string($facts[$field]) && preg_match('/\A[A-Za-z0-9_-]{1,80}\z/D', $facts[$field]) === 1, 422);
            }
        }
        abort_unless(is_int($facts['amount_tzs']) && $facts['amount_tzs'] > 0 && $facts['amount_tzs'] <= PaymentEngine::MAX_AMOUNT_TZS && $facts['currency'] === 'TZS'
            && is_int($facts['occurred_at']) && $facts['occurred_at'] >= 0 && $facts['occurred_at'] >= $now - 300000 && $facts['occurred_at'] <= $now + 30000
            && ($domain === 'settlement' || in_array($facts['status'], ['pending', 'confirmed', 'failed', 'unresolved'], true)), 422);

        return $facts;
    }
}
