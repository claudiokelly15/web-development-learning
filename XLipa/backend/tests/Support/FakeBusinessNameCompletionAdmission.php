<?php

namespace Tests\Support;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use Closure;
use Illuminate\Database\Connection;

/** Public fictional trust; never registered in application code. */
final class FakeBusinessNameCompletionAdmission implements BusinessNameCompletionAdmission
{
    public ?string $profile = WorkflowSchemaReadiness::BNC;

    public bool $allow = true;

    public bool $restore = true;

    public bool $retention = true;

    public bool $reconciliation = true;

    public int $time = 1794528000123;

    public string $policy = 'fixture.redacted';

    public array $connections = [];

    public ?Closure $inspect = null;

    public function expectedProfile(): ?string
    {
        return $this->profile;
    }

    public function authorized(int $actor, string $merchant, array $expected, bool $resolving): bool
    {
        return $this->allow;
    }

    public function milliseconds(): int
    {
        return $this->time;
    }

    public function recordPolicy(): string
    {
        return $this->policy;
    }

    public function retentionAllowed(string $policy, int $occurredAt): bool
    {
        return $this->retention;
    }

    public function authoritative(Connection $connection, int $actor, string $merchant, array $expected, bool $resolving): bool
    {
        $this->connections[] = $connection;
        if ($this->inspect) {
            ($this->inspect)($connection, $resolving);
        }

        return $this->restore;
    }

    public function reconciliationReady(int $actor, string $merchant): bool
    {
        return $this->reconciliation;
    }
}
