<?php

namespace App\Staging;

use App\Actions\Payments\LedgerAdmission;

final class StagingLedgerAdmission extends LedgerAdmission
{
    public function authorized(int $actor, string $merchant, string $operation): bool
    {
        try {
            $c = StagingFinancialAuthority::current();

            return $c['actor'] === $actor && $c['merchant'] === $merchant && match ($operation) {
                'post' => $c['action'] === 'r24.ledger.post',
                'reconcile' => $c['action'] === 'r24.ledger.reconcile',
                'read' => $c['action'] === 'r24.transactions.read',
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
