<?php

namespace Tests\Support;

use App\Actions\Merchants\BusinessNameCompletionHttpInput;
use App\Actions\Merchants\BusinessNameCompletionRequestContext;
use Closure;
use LogicException;
use RuntimeException;
use Throwable;

/** Single-process fictional custody only: no encryption, durability, real clock or restore trust. */
final class FakeBusinessNameCompletionRequestContext implements BusinessNameCompletionRequestContext
{
    private array $contexts = [];

    public function __construct(private readonly Closure $resolutionAuthority)
    {
        if (getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing')) {
            throw new LogicException('Fictional context requires isolated testing.');
        }
    }

    public function prepare(#[\SensitiveParameter] array $binding, #[\SensitiveParameter] array $expected, #[\SensitiveParameter] string $name, int $preparedAt, int $expiresAt, int $retainUntil): string
    {
        $this->binding($binding);
        $input = new BusinessNameCompletionHttpInput;
        $input->expectations($binding['merchant_id'], $expected);
        $this->check($input->name($name) === $name);
        $this->check($preparedAt >= 0 && $preparedAt < $expiresAt && $expiresAt <= $retainUntil && $retainUntil <= 253402300799999);
        foreach ($this->contexts as $context) {
            if ($context['binding']['merchant_id'] === $binding['merchant_id']) {
                $this->check(! in_array($context['state'], ['dispatching', 'unresolved'], true));
                $this->check($context['binding']['actor_id'] !== $binding['actor_id'] || $context['expected']['completion_request_id'] !== $expected['completion_request_id']);
            }
        }
        $reference = bin2hex(random_bytes(32));
        $this->contexts[$reference] = ['binding' => $binding, 'expected' => $expected, 'name' => $name,
            'prepared_at' => $preparedAt, 'expires_at' => $expiresAt, 'retain_until' => $retainUntil,
            'observed_at' => $preparedAt, 'dispatched_at' => null, 'state' => 'prepared',
            'dispatch_token' => null, 'resolution_token' => null, 'resolution' => null];

        return $reference;
    }

    public function dispatch(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, int $now): array
    {
        $context = $this->get($reference, $binding, $now);
        $this->check($context['state'] === 'prepared' && $now < $context['expires_at']);
        foreach ($this->contexts as $otherReference => $other) {
            if ($otherReference !== $reference && $other['binding']['merchant_id'] === $binding['merchant_id']) {
                $this->check(! in_array($other['state'], ['dispatching', 'unresolved'], true));
            }
        }
        $context['state'] = 'dispatching';
        $context['dispatched_at'] = $now;
        $context['dispatch_token'] = bin2hex(random_bytes(32));
        $this->contexts[$reference] = $context;

        return $this->privateInput($context, $context['dispatch_token']);
    }

    public function finish(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, #[\SensitiveParameter] string $token, #[\SensitiveParameter] array $result, int $now): array
    {
        $context = $this->get($reference, $binding, $now);
        $this->check($context['state'] === 'dispatching' && is_string($context['dispatch_token']) && hash_equals($context['dispatch_token'], $token));
        $this->result($result, $context, false);
        $context['state'] = $result['status'];
        $context['dispatch_token'] = null;
        $this->contexts[$reference] = $context;

        return $this->safeStatus($context);
    }

    public function unresolved(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, #[\SensitiveParameter] string $token, int $now): array
    {
        $context = $this->get($reference, $binding, $now, false);
        $this->check($context['state'] === 'dispatching' && is_string($context['dispatch_token']) && hash_equals($context['dispatch_token'], $token));
        $context['state'] = 'unresolved';
        $context['dispatch_token'] = null;
        $this->contexts[$reference] = $context;

        return $this->safeStatus($context);
    }

    public function beginResolution(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, int $now): array
    {
        $context = $this->get($reference, $binding, $now);
        $this->check(in_array($context['state'], ['dispatching', 'unresolved'], true) && $this->resolutionAllowed($binding, $reference));
        $context['state'] = 'unresolved';
        $context['dispatch_token'] = null;
        $context['resolution_token'] = bin2hex(random_bytes(32));
        $this->contexts[$reference] = $context;

        return $this->privateInput($context, $context['resolution_token']);
    }

    public function finishResolution(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, #[\SensitiveParameter] string $token, #[\SensitiveParameter] array $result, int $now): array
    {
        $context = $this->get($reference, $binding, $now);
        $this->check($context['state'] === 'unresolved' && is_string($context['resolution_token']) && hash_equals($context['resolution_token'], $token) && $this->resolutionAllowed($binding, $reference));
        $this->result($result, $context, true);
        $context['resolution'] = $result['status'];
        $context['state'] = $result['status'] === 'confirmed' ? 'completed' : 'unresolved';
        $context['resolution_token'] = null;
        $this->contexts[$reference] = $context;

        return $this->safeStatus($context);
    }

    public function status(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, int $now): array
    {
        return $this->safeStatus($this->get($reference, $binding, $now, false));
    }

    private function binding(#[\SensitiveParameter] array $binding): void
    {
        $this->check(count($binding) === 4 && ! array_diff(array_keys($binding), ['actor_id', 'merchant_id', 'session_id', 'generation']));
        $this->check(is_int($binding['actor_id']) && $binding['actor_id'] > 0 && is_string($binding['merchant_id']));
        foreach (['session_id', 'generation'] as $key) {
            $this->check(is_string($binding[$key]) && preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $binding[$key]) === 1);
        }
    }

    private function get(#[\SensitiveParameter] string $reference, #[\SensitiveParameter] array $binding, int $now, bool $withinRetention = true): array
    {
        $this->binding($binding);
        $context = $this->contexts[$reference] ?? null;
        $this->check($context !== null);
        foreach ($context['binding'] as $key => $value) {
            $this->check($binding[$key] === $value);
        }
        $this->check($now >= $context['observed_at'] && $now <= 253402300799999 && (! $withinRetention || $now < $context['retain_until']));
        $context['observed_at'] = $now;
        $this->contexts[$reference] = $context;

        return $context;
    }

    private function result(#[\SensitiveParameter] array $result, #[\SensitiveParameter] array $context, bool $resolving): void
    {
        $status = $result['status'] ?? null;
        $allowed = $resolving ? ['confirmed', 'absent', 'historical', 'held', 'unknown'] : ['completed', 'unchanged'];
        $this->check(in_array($status, $allowed, true));
        $keys = ['status'];
        if (in_array($status, ['completed', 'confirmed', 'historical'], true)) {
            $keys[] = 'completion_id';
            $this->check(is_string($result['completion_id'] ?? null) && preg_match('/\A[0-7][0-9A-HJKMNP-TV-Z]{25}\z/D', $result['completion_id']) === 1);
        }
        if (in_array($status, ['completed', 'confirmed'], true)) {
            $keys[] = 'resulting_profile_version';
            $prior = $context['expected']['merchant_profile_version'];
            $this->check($prior < PHP_INT_MAX && ($result['resulting_profile_version'] ?? null) === $prior + 1 && $context['expected']['draft_profile_version'] === $prior);
        }
        $this->check(count($result) === count($keys) && ! array_diff(array_keys($result), $keys));
    }

    private function privateInput(#[\SensitiveParameter] array $context, #[\SensitiveParameter] string $token): array
    {
        return ['token' => $token, 'expected' => $context['expected'], 'name' => $context['name']];
    }

    private function safeStatus(#[\SensitiveParameter] array $context): array
    {
        return ['state' => $context['state'] === 'dispatching' ? 'unresolved' : $context['state'], 'resolution' => $context['resolution']];
    }

    private function check(bool $condition): void
    {
        if (! $condition) {
            throw new RuntimeException('Private completion context is unavailable.');
        }
    }

    private function resolutionAllowed(#[\SensitiveParameter] array $binding, #[\SensitiveParameter] string $reference): bool
    {
        try {
            return ($this->resolutionAuthority)($binding, $reference) === true;
        } catch (Throwable) {
            return false;
        }
    }

    public function __debugInfo(): array
    {
        return ['custody' => '[REDACTED]', 'fictional' => true];
    }

    public function __serialize(): array
    {
        throw new LogicException('Private completion context serialization is prohibited.');
    }
}
