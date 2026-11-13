<?php

namespace App\Actions\Merchants;

use Illuminate\Database\Connection;

/** Independent server trust, never derived from request assertions or local workflow rows. */
interface BusinessNameCompletionAdmission
{
    public function expectedProfile(): ?string;

    /** Authorize the current owner and explicit command/resolution scope independently. */
    public function authorized(int $actor, string $merchant, array $expected, bool $resolving): bool;

    public function milliseconds(): int;

    public function recordPolicy(): string;

    public function retentionAllowed(string $policy, int $occurredAt): bool;

    /** Bind this connection to authoritative store lineage, restore/cutover and replay fences. */
    public function authoritative(Connection $connection, int $actor, string $merchant, array $expected, bool $resolving): bool;

    public function reconciliationReady(int $actor, string $merchant): bool;
}
