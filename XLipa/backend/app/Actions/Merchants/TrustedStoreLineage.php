<?php

namespace App\Actions\Merchants;

use Illuminate\Database\Connection;

interface TrustedStoreLineage
{
    /**
     * Independently bind the actual connection to current authoritative store/generation,
     * restore/cutover fencing epoch and admitted worker; exclude old writers.
     * Reopening a PDO or matching rows/schema cannot supply this authority.
     * Revalidate after reconnect and at every command/resolution admission boundary.
     */
    public function authoritative(Connection $connection, #[\SensitiveParameter] array $descriptor): bool;

    /** Independent scoped replay fence; resolution is never permission to repeat a command. */
    public function requestAllowed(int $actor, string $merchant, #[\SensitiveParameter] string $requestId, bool $resolving): bool;
}
