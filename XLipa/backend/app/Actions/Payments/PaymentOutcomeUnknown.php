<?php

namespace App\Actions\Payments;

use RuntimeException;

final class PaymentOutcomeUnknown extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Payment outcome is unresolved.');
    }
}
