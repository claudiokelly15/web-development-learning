<?php

namespace App\Staging;

use App\Actions\Payments\PaymentAdmission;

final class StagingPaymentAdmission extends PaymentAdmission
{
    public function authorized(int $actor, string $merchant, string $operation): bool
    {
        try {
            $c = StagingFinancialAuthority::current();
            if (($c['owner'] ?? $c['actor']) !== $actor || $c['merchant'] !== $merchant) {
                return false;
            }

            return match ($operation) {
                'create' => in_array($c['action'], ['r24.payment.create', 'r24.checkout.initiate'], true),
                'query' => $c['action'] === 'r24.payment.query',
                'read' => ! in_array($c['action'], ['r24.mock.event', 'r24.checkout.read', 'r24.checkout.initiate'], true),
                default => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    public function eventsAuthorized(string $provider): bool
    {
        return $provider === 'fictional_mock' && StagingFinancialAuthority::allows(['r24.mock.event']);
    }

    public function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
