<?php

namespace App\Actions\Payments;

use LogicException;

/** No operational link-management or guest-payment capability is provisioned. */
class PaymentLinkAdmission
{
    public function authorized(int $actor, string $merchant): bool
    {
        return false;
    }

    public function customerAuthorized(string $merchant, string $operation): bool
    {
        return false;
    }

    public function milliseconds(): int
    {
        throw new LogicException('Payment link admission is unavailable.');
    }
}
