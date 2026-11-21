<?php

namespace App\Actions\Payments;

/** Provider-neutral recovery capability; implementations define their own authenticated evidence codec. */
interface ReconciledPayoutProvider extends PayoutProvider
{
    public function reconciliationEvidence(string $reference, int $time): array;
}
