<?php

namespace App\Actions\Payments;

use LogicException;

/** Separate fictional method-attestation protocol; never a Selcom network identifier. */
final class AnalyticsEvidence
{
    public function __construct(#[\SensitiveParameter] private readonly string $key)
    {
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || strlen($key) !== 32) {
            throw new LogicException('Analytics attestations require isolated testing.');
        }
    }

    public function signature(string $body): string
    {
        return hash_hmac('sha256', 'xlipa-fictional-method-v1:'.$body, $this->key);
    }

    public function verify(string $body, string $signature, int $now): array
    {
        abort_unless(strlen($body) <= 2048 && preg_match('/\A[a-f0-9]{64}\z/D', $signature) === 1 && hash_equals($this->signature($body), $signature), 401);
        $facts = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        abort_unless(is_array($facts) && array_keys($facts) === ['event_id', 'merchant_id', 'payment_reference', 'confirmation_id', 'amount_tzs', 'currency', 'method', 'occurred_at']
            && json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) === $body, 422);
        foreach (['event_id', 'merchant_id', 'payment_reference', 'confirmation_id'] as $field) {
            abort_unless(is_string($facts[$field]) && preg_match('/\A[A-Za-z0-9_-]{1,80}\z/D', $facts[$field]) === 1, 422);
        }
        abort_unless(is_int($facts['amount_tzs']) && $facts['amount_tzs'] > 0 && $facts['amount_tzs'] <= PaymentEngine::MAX_AMOUNT_TZS && $facts['currency'] === 'TZS'
            && is_string($facts['method']) && array_key_exists($facts['method'], MerchantAnalytics::METHODS) && $facts['method'] !== 'unknown'
            && is_int($facts['occurred_at']) && $facts['occurred_at'] >= $now - 300000 && $facts['occurred_at'] <= $now + 30000, 422);

        return $facts;
    }
}
