<?php

namespace App\Actions\Merchants;

use RuntimeException;

final class SubmissionOutcomeUnknown extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Submission outcome not confirmed. Resolve the same request on a fresh authoritative connection.');
    }
}
