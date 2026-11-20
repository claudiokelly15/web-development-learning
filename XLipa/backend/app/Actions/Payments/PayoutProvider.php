<?php

namespace App\Actions\Payments;

interface PayoutProvider
{
    public function id(): string;

    public function create(array $withdrawal): array;
}
