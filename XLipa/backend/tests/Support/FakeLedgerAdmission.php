<?php

namespace Tests\Support;

use App\Actions\Payments\LedgerAdmission;
use Closure;

final class FakeLedgerAdmission extends LedgerAdmission
{
    public bool $allow = false;

    public int $actor = 0;

    public string $merchant = '';

    public int $time = 1794614400400;

    public ?Closure $inspect = null;

    public function authorized(int $actor, string $merchant, string $operation): bool
    {
        if ($this->inspect !== null) {
            ($this->inspect)($operation);
        }

        return $this->allow && $actor === $this->actor && $merchant === $this->merchant && in_array($operation, ['post', 'read', 'reconcile'], true);
    }

    public function milliseconds(): int
    {
        return $this->time;
    }
}
