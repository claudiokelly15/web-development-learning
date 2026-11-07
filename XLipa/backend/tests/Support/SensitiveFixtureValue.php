<?php

namespace Tests\Support;

use JsonSerializable;
use LogicException;

final class SensitiveFixtureValue implements JsonSerializable
{
    public function __construct(private readonly string $value) {}

    public function forCryptoAssertion(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Sensitive serialization prohibited.');
    }

    public function __serialize(): array
    {
        throw new LogicException('Sensitive serialization prohibited.');
    }

    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
