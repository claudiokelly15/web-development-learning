<?php

namespace App\Actions\Merchants;

/** Trusted local admission contracts, never request flags or database provenance labels alone. */
interface SubmissionAdmission
{
    public function authorized(int $actor, string $merchant, array $expectations): bool;

    /** Must establish an authoritative current database, not a stale backup or replica. */
    public function restoreReady(): bool;

    public function milliseconds(): int;

    public function retentionPolicy(): string;

    /** Includes source-record retention/holds; a policy name alone is insufficient. */
    public function retentionAllowed(string $policy, int $submittedAt, ?int $identityDueAt): bool;

    /** Authenticate issuer/adapter custody against this exact lineage and policy at event time. */
    public function trusted(array $proof, array $challenge, array $intent, array $identity, int $submittedAt): bool;
}
