<?php

namespace App\Actions\Merchants;

use Illuminate\Database\Connection;
use LogicException;

final class DeniedBusinessNameCompletionAdmission implements BusinessNameCompletionAdmission
{
    public function expectedProfile(): ?string
    {
        return null;
    }

    public function authorized(int $actor, string $merchant, array $expected, bool $resolving): bool
    {
        return false;
    }

    public function milliseconds(): int
    {
        throw new LogicException('Completion time unavailable.');
    }

    public function recordPolicy(): string
    {
        throw new LogicException('Completion policy unavailable.');
    }

    public function retentionAllowed(string $policy, int $occurredAt): bool
    {
        return false;
    }

    public function authoritative(Connection $connection, int $actor, string $merchant, array $expected, bool $resolving): bool
    {
        return false;
    }

    public function reconciliationReady(int $actor, string $merchant): bool
    {
        return false;
    }
}
