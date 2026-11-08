<?php

namespace App\Actions\Merchants;

use LogicException;

/** Trusted custody/retention/time integration. No keys, authority or recovery by default. */
class SubmissionEvidenceEnvironment
{
    public function authorized(): bool
    {
        return false;
    }

    public function namespace(): string
    {
        return 'xlipa';
    }

    public function restoreReady(): bool
    {
        return false;
    }

    public function milliseconds(): int
    {
        throw new LogicException('Evidence time unavailable.');
    }

    public function retentionAllowed(string $policy, string $use, int $submittedAt): bool
    {
        return false;
    }

    public function keyId(string $domain): string
    {
        throw new LogicException('Evidence keys unavailable.');
    }

    /** Custody must separately authorize encryption, historical decryption and retired binding-key recovery. */
    public function key(string $domain, string $id, string $use): string
    {
        throw new LogicException('Evidence keys unavailable.');
    }

    public function __debugInfo(): array
    {
        return ['material' => '[REDACTED]'];
    }

    public function __serialize(): array
    {
        throw new LogicException('Evidence environment serialization denied.');
    }
}
