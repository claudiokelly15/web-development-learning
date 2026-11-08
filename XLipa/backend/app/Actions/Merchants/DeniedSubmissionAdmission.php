<?php

namespace App\Actions\Merchants;

use LogicException;

final class DeniedSubmissionAdmission implements SubmissionAdmission
{
    public function authorized(int $actor, string $merchant, array $expectations): bool
    {
        return false;
    }

    public function restoreReady(): bool
    {
        return false;
    }

    public function milliseconds(): int
    {
        throw new LogicException('Submission time unavailable.');
    }

    public function retentionPolicy(): string
    {
        throw new LogicException('Submission retention unavailable.');
    }

    public function retentionAllowed(string $policy, int $submittedAt, ?int $identityDueAt): bool
    {
        return false;
    }

    public function trusted(array $proof, array $challenge, array $intent, array $identity, int $submittedAt): bool
    {
        return false;
    }
}
