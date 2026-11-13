<?php

namespace App\Actions\Merchants;

use RuntimeException;

final class BusinessNameCompletionOutcomeUnknown extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Business name completion outcome is unknown. Explicit resolution requires a fresh authoritative connection.');
    }
}
