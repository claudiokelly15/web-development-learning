<?php

namespace App\Actions\Merchants;

use LogicException;

/** Independent final-decision/step-up/policy/restore authority. Unprovisioned by default. */
class ApplicationDecisionAdmission
{
    public function authorized(int $actor, string $merchant, string $application, array $expected, string $outcome, ?string $reason): bool
    {
        return false;
    }

    public function authorityVersion(int $actor): ?int
    {
        return null;
    }

    public function milliseconds(): int
    {
        throw new LogicException('Application decision admission is unavailable.');
    }
}
