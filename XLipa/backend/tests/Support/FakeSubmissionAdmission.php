<?php

namespace Tests\Support;

use App\Actions\Merchants\SubmissionAdmission;

/** Fictional issuance registry only; never imported or bound in application code. */
final class FakeSubmissionAdmission implements SubmissionAdmission
{
    public bool $enabled = true;

    public bool $restore = true;

    public bool $issuer = true;

    public bool $retention = true;

    public int $time = 1794096000100;

    public array $issued = [];

    public function authorized(int $actor, string $merchant, array $expectations): bool
    {
        return $this->enabled;
    }

    public function restoreReady(): bool
    {
        return $this->restore;
    }

    public function milliseconds(): int
    {
        return $this->time;
    }

    public function retentionPolicy(): string
    {
        return 'fixture.retention';
    }

    public function retentionAllowed(string $policy, int $submittedAt, ?int $identityDueAt): bool
    {
        return $this->retention && $policy === 'fixture.retention' && ($identityDueAt === null || $identityDueAt > $submittedAt);
    }

    public function trusted(array $proof, array $challenge, array $intent, array $identity, int $submittedAt): bool
    {
        return $this->issuer && $this->issued === [$proof['id'], $proof['challenge_id'], $proof['merchant_id'], $proof['actor_user_id'], $proof['contact_id'], $proof['verified_at'], $proof['expires_at'], $intent['id'], $intent['request_id'], $intent['completed_at'], $challenge['id'], $challenge['terminal_at'], $identity['id'], $identity['syntax_policy_version']];
    }
}
