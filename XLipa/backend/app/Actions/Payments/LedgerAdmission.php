<?php

namespace App\Actions\Payments;

use LogicException;

/** Independent accounting capability; no operational grants or clock are provisioned. */
class LedgerAdmission
{
    public function authorized(int $actor, string $merchant, string $operation): bool
    {
        return false;
    }

    public function milliseconds(): int
    {
        throw new LogicException('Ledger admission is unavailable.');
    }
}
