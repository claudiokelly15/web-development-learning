<?php

namespace Tests\Support;

use LogicException;

final class SyntheticKeys
{
    /** @var array<string, array<string, string>> */
    private array $keys;

    public function __construct()
    {
        if (getenv('XLIPA_SYNTHETIC_ONLY') !== '1') {
            throw new LogicException('Fixture keys are forbidden outside isolated tests.');
        }
        $this->keys = [];
        foreach (['nin', 'phone', 'otp', 'contact'] as $index => $domain) {
            $this->keys[$domain] = [$domain.'.v1' => str_repeat(chr(65 + $index), 32), $domain.'.v2' => str_repeat(chr(75 + $index), 32)];
        }
    }

    public function resolve(string $domain, string $id): string
    {
        return $this->keys[$domain][$id] ?? throw new LogicException('Sensitive value unavailable.');
    }

    public function forget(string $domain, string $id): void
    {
        unset($this->keys[$domain][$id]);
    }

    public function __debugInfo(): array
    {
        return ['material' => '[REDACTED FIXTURE]'];
    }

    public function __serialize(): array
    {
        throw new LogicException('Key serialization prohibited.');
    }
}
