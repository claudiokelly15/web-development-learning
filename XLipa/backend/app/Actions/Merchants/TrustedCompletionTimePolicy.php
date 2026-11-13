<?php

namespace App\Actions\Merchants;

interface TrustedCompletionTimePolicy
{
    /** Trusted milliseconds, bounded and nonregressing; abnormal jumps/outages must deny. */
    public function milliseconds(): int;

    /** Independently approved policy version, not a client claim or deployment default. */
    public function recordPolicy(): string;

    /** Historical access requires original policy authority; a hold never implies deletion. */
    public function retentionAllowed(string $policy, int $occurredAt, bool $unresolved): bool;

    /**
     * Prepared work expires; dispatched uncertainty stays held, never automatically retried.
     * Resolution still requires independent current authority and payload retention permission.
     */
    public function contextAccess(int $preparedAt, int $expiresAt, int $retainUntil, bool $unresolved, bool $resolving): bool;
}
