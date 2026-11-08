<?php

namespace App\Actions\Merchants;

use LogicException;

/** Unprovisioned integration boundary. No keys, delivery or collection authority by default. */
class VerificationEnvironment
{
    public function authorized(): bool
    {
        return false;
    }

    public function namespace(): string
    {
        return 'xlipa';
    }

    public function milliseconds(): int
    {
        return (int) now()->getTimestampMs();
    }

    public function mode(): string
    {
        return 'live';
    }

    public function keyId(string $domain): string
    {
        throw new LogicException('Verification unavailable.');
    }

    public function key(string $domain, string $id): string
    {
        throw new LogicException('Verification unavailable.');
    }

    /** @return array{syntax:string,declaration:string,notice:string,text:string}|null */
    public function identityPolicy(): ?array
    {
        return null;
    }

    public function normalizeIdentity(#[\SensitiveParameter] string $value): string
    {
        throw new LogicException('Identity capture unavailable.');
    }

    /** Independently enforced target/IP/global quotas and spend/fencing; false means no delivery. */
    public function reserveDeliveryBudget(string $targetFingerprint, string $requestId, string $ip): bool
    {
        return false;
    }

    /** Must return accepted, rejected or unknown. Called only after durable claim, outside a transaction. */
    public function deliver(#[\SensitiveParameter] string $phone, #[\SensitiveParameter] string $code, string $requestId): string
    {
        throw new LogicException('Delivery unavailable.');
    }

    public function __debugInfo(): array
    {
        return ['material' => '[REDACTED]'];
    }

    public function __serialize(): array
    {
        throw new LogicException('Serialization prohibited.');
    }
}
