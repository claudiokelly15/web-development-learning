<?php

namespace App\Actions\Merchants;

/**
 * Server custody contract only; deliberately unbound in the application container.
 *
 * Binding is server-derived: exact actor_id, merchant_id, session_id and store generation.
 * Expectations are the nine original BusinessNameCompletionContext::EXPECTATIONS, typed exactly.
 * Store the original validated name, UUID/epochs/versions, preparation/dispatch times and state.
 * Implementations must atomically compare state and issue one-use dispatch/resolution tokens.
 * Persist dispatching BEFORE returning private command input. Never replay or replace its UUID.
 *
 * prepared -> dispatching -> completed / unchanged / unresolved.
 * Lost dispatch acknowledgement is unresolved, including a crash before command invocation.
 * Resolution is explicit, independently authorized, and uses original context/current authority.
 * Confirmed may finish completed; absent/historical/held never authorize a second dispatch.
 * Expiry/retention failure denies access, preserves unresolved custody and proves no rollback.
 * A future durable encrypted adapter needs independent time/policy/retention and restore fences;
 * caller-supplied time/binding or a local generation label alone provides none of that authority.
 * Private dispatch/resolution arrays must never be sent to a browser, log, flash or analytics.
 * Tokens are internal custody capabilities, never browser context references or admission grants.
 * Result recording accepts only acknowledged, trusted internal command/resolver results.
 * No method invokes the command/resolver or authenticates browser/owner/store authority itself.
 *
 * @phpstan-type Binding array{actor_id:int,merchant_id:string,session_id:string,generation:string}
 * @phpstan-type PrivateInput array{token:string,expected:array,name:string}
 * @phpstan-type SafeStatus array{state:string,resolution:?string}
 */
interface BusinessNameCompletionRequestContext
{
    /** @param Binding $binding Preparation must retain canonical bytes unchanged. */
    public function prepare(#[\SensitiveParameter] array $binding, #[\SensitiveParameter] array $expected, #[\SensitiveParameter] string $name, int $preparedAt, int $expiresAt, int $retainUntil): string;

    /** @param Binding $binding @return PrivateInput */
    public function dispatch(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, int $now): array;

    /** @param Binding $binding @return SafeStatus */
    public function finish(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, #[\SensitiveParameter] string $token, #[\SensitiveParameter] array $result, int $now): array;

    /** @param Binding $binding @return SafeStatus */
    public function unresolved(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, #[\SensitiveParameter] string $token, int $now): array;

    /** @param Binding $binding @return PrivateInput Independent resolution authorization required. */
    public function beginResolution(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, int $now): array;

    /** @param Binding $binding @return SafeStatus Unknown/failed resolution stays unresolved. */
    public function finishResolution(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, #[\SensitiveParameter] string $token, #[\SensitiveParameter] array $result, int $now): array;

    /** @param Binding $binding @return SafeStatus Never returns name, epochs, tokens or UUID. */
    public function status(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, int $now): array;
}
