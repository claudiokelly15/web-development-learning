<?php

namespace Tests\Support;

use App\Actions\Payments\PaymentAdmission;

final class FakePaymentAdmission extends PaymentAdmission
{
    public bool $allow = false;

    public int $actor = 0;

    public string $merchant = '';

    public int $time = 1794614400300;

    public function authorized(int $actor, string $merchant, string $operation): bool
    {
        return $this->allow && $actor === $this->actor && $merchant === $this->merchant && in_array($operation, ['create', 'read', 'query'], true);
    }

    public function eventsAuthorized(string $provider): bool
    {
        return $this->allow && $provider === 'fictional_mock';
    }

    public function milliseconds(): int
    {
        return $this->time;
    }
}
