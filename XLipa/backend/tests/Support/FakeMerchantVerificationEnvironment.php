<?php

namespace Tests\Support;

use App\Actions\Merchants\VerificationEnvironment;
use Closure;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Test-only injected boundary; never registered by application code. */
final class FakeMerchantVerificationEnvironment extends VerificationEnvironment
{
    public bool $enabled = true;

    public bool $budget = true;

    public bool $identity = true;

    public int $time = 1794096000000;

    public string $outcome = 'accepted';

    public int $sends = 0;

    public ?string $lastCode = null;

    public ?Closure $duringDelivery = null;

    public array $keys = [];

    public function __construct()
    {
        foreach (['nin', 'phone', 'otp', 'contact'] as $domain) {
            $this->keys[$domain] = random_bytes(32);
        }
    }

    public function authorized(): bool
    {
        return $this->enabled;
    }

    public function namespace(): string
    {
        return 'synthetic';
    }

    public function milliseconds(): int
    {
        return $this->time;
    }

    public function mode(): string
    {
        return 'simulated';
    }

    public function keyId(string $domain): string
    {
        return $domain.'.v1';
    }

    public function key(string $domain, string $id): string
    {
        return $id === $this->keyId($domain) ? ($this->keys[$domain] ?? throw new \LogicException('Fixture key unavailable.')) : throw new \LogicException('Fixture key unavailable.');
    }

    public function identityPolicy(): ?array
    {
        return $this->identity ? ['syntax' => 'fixture-only-v1', 'declaration' => 'fixture-declaration-v1', 'notice' => 'fixture-notice-v1', 'text' => 'Synthetic fixture declaration and privacy notice only.'] : null;
    }

    public function normalizeIdentity(#[\SensitiveParameter] string $value): string
    {
        if (! preg_match('/^FIXTURE-[A-Z0-9]{8}$/D', $value)) {
            throw new HttpException(422, 'Invalid fixture declaration.');
        }

        return $value;
    }

    public function reserveDeliveryBudget(string $targetFingerprint, string $requestId, string $ip): bool
    {
        return $this->budget;
    }

    public function deliver(#[\SensitiveParameter] string $phone, #[\SensitiveParameter] string $code, string $requestId): string
    {
        $this->sends++;
        $this->lastCode = $code;
        if ($this->duringDelivery) {
            ($this->duringDelivery)();
        }

        return $this->outcome;
    }

    public function __destruct()
    {
        foreach ($this->keys as &$key) {
            sodium_memzero($key);
        }
    }
}
