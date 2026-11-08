<?php

namespace Tests\Support;

use App\Actions\Merchants\SubmissionEvidenceEnvironment;
use LogicException;

/** Disposable test-only custody. Never bound by runtime code. */
final class FakeSubmissionEvidenceEnvironment extends SubmissionEvidenceEnvironment
{
    public bool $enabled = true;

    public bool $restore = true;

    public bool $retention = true;

    public int $time = 1794096000100;

    public array $keys;

    public function __construct()
    {
        $this->keys = ['evidence.v1' => random_bytes(32), 'binding.v1' => random_bytes(32), 'evidence.old' => random_bytes(32)];
    }

    public function authorized(): bool
    {
        return $this->enabled;
    }

    public function namespace(): string
    {
        return 'fixture';
    }

    public function restoreReady(): bool
    {
        return $this->restore;
    }

    public function milliseconds(): int
    {
        return $this->time;
    }

    public function retentionAllowed(string $policy, string $use, int $submittedAt): bool
    {
        return $this->retention && $policy === 'fixture.retention';
    }

    public function keyId(string $domain): string
    {
        return $domain.'.v1';
    }

    public function key(string $domain, string $id, string $use): string
    {
        if (! in_array($use, ['encrypt', 'decrypt'], true) || ($use === 'encrypt' && $id !== $this->keyId($domain)) || ! str_starts_with($id, $domain.'.')) {
            throw new LogicException('Fixture key denied.');
        }

        return $this->keys[$id] ?? throw new LogicException('Fixture key absent.');
    }
}
