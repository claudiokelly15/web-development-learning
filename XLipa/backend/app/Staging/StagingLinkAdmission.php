<?php

namespace App\Staging;

use App\Actions\Payments\PaymentLinkAdmission;

final class StagingLinkAdmission extends PaymentLinkAdmission
{
    public function authorized(int $actor, string $merchant): bool
    {
        try {
            $c = StagingFinancialAuthority::current();

            return $c['actor'] === $actor && $c['merchant'] === $merchant && in_array($c['action'], ['r24.products.read', 'r24.links.create', 'r24.links.disable', 'r24.widgets.create', 'r24.widgets.read', 'r24.ledger.post', 'r24.ledger.reconcile'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function customerAuthorized(string $merchant, string $operation): bool
    {
        try {
            $c = StagingFinancialAuthority::current();

            return $c['merchant'] === $merchant && match ($operation) {
                'initiate' => $c['action'] === 'r24.checkout.initiate',
                'resolve' => in_array($c['action'], ['r24.checkout.read', 'r24.widgets.read'], true),
                'status' => in_array($c['action'], ['r24.checkout.read', 'r24.checkout.initiate'], true),
                default => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    public function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
