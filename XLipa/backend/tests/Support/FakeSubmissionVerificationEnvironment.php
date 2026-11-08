<?php

namespace Tests\Support;

use App\Actions\Merchants\VerificationEnvironment;
use LogicException;

/** Live-shaped fixture crypto only. Delivery stays inherited-denied. */
final class FakeSubmissionVerificationEnvironment extends VerificationEnvironment
{
    public array $keys;

    public int $time = 1794096000100;

    public string $transport = 'live';

    public function __construct()
    {
        foreach (['nin', 'phone', 'otp', 'contact'] as $domain) {
            $this->keys[$domain] = random_bytes(32);
        }
    }

    public function authorized(): bool
    {
        return true;
    }

    public function mode(): string
    {
        return $this->transport;
    }

    public function namespace(): string
    {
        return 'fixture';
    }

    public function milliseconds(): int
    {
        return $this->time;
    }

    public function keyId(string $domain): string
    {
        return $domain.'.v1';
    }

    public function key(string $domain, string $id): string
    {
        return $id === $this->keyId($domain) ? $this->keys[$domain] : throw new LogicException('Fixture key unavailable.');
    }

    public function identityPolicy(): ?array
    {
        return ['syntax' => 'fixture.syntax', 'declaration' => 'fixture.declaration', 'notice' => 'fixture.notice', 'text' => 'Fictional policy only.'];
    }
}
