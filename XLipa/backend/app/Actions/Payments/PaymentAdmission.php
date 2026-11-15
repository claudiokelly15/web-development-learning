<?php

namespace App\Actions\Payments;

use LogicException;

/** Separate financial capability, never inferred from application approval. */
class PaymentAdmission
{
    public function authorized(int $actor, string $merchant, string $operation): bool
    {
        return false;
    }

    public function eventsAuthorized(string $provider): bool
    {
        return false;
    }

    public function milliseconds(): int
    {
        throw new LogicException('Payment admission is unavailable.');
    }
}
