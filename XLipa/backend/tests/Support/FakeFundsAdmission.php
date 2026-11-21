<?php

namespace Tests\Support;

use App\Actions\Payments\FundsAdmission;

final class FakeFundsAdmission extends FundsAdmission
{
    public bool $allow = false;

    public int $actor = 0;

    public string $merchant = '';

    public int $time = 1794614400500;

    public int $chief = 0;

    public function authorized(int $actor, string $merchant, string $operation): bool
    {
        return $this->allow && $merchant === $this->merchant && (($actor === $this->chief && $this->chief > 0 && $operation === 'admin_read') || ($actor === $this->actor && in_array($operation, ['settle', 'read', 'withdraw', 'dispatch', 'event', 'reconcile'], true)));
    }

    public function destination(int $actor, string $merchant, string $reference): ?array
    {
        return $this->authorized($actor, $merchant, 'withdraw') && $reference === 'fictional_destination_1' ? ['reference' => $reference, 'mask' => 'Fictional destination **** 0001'] : null;
    }

    public function milliseconds(): int
    {
        return $this->time;
    }
}
