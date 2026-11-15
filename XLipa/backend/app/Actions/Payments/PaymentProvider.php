<?php

namespace App\Actions\Payments;

interface PaymentProvider
{
    public function id(): string;

    public function capabilities(): array;

    public function create(array $payment): array;

    public function query(array $payment): array;

    public function verifyEvent(string $body, string $signature, int $now): array;
}
