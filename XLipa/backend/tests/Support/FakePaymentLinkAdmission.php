<?php

namespace Tests\Support;

use App\Actions\Payments\PaymentLinkAdmission;

final class FakePaymentLinkAdmission extends PaymentLinkAdmission
{
    public bool $allow = false;

    public bool $customers = false;

    public int $actor = 0;

    public string $merchant = '';

    public int $time = 1794614400300;

    public function authorized(int $actor, string $merchant): bool
    {
        return $this->allow && $actor === $this->actor && $merchant === $this->merchant;
    }

    public function customerAuthorized(string $merchant, string $operation): bool
    {
        return $this->customers && $merchant === $this->merchant && in_array($operation, ['resolve', 'initiate', 'status'], true);
    }

    public function milliseconds(): int
    {
        return $this->time;
    }
}
