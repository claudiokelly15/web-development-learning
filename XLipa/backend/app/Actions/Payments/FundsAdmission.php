<?php

namespace App\Actions\Payments;

use LogicException;

/** No operational settlement, withdrawal, payout or financial-admin grants are provisioned. */
class FundsAdmission
{
    public function authorized(int $actor, string $merchant, string $operation): bool
    {
        return false;
    }

    public function destination(int $actor, string $merchant, string $reference): ?array
    {
        return null;
    }

    public function milliseconds(): int
    {
        throw new LogicException('Fictional funds admission is unavailable.');
    }
}
