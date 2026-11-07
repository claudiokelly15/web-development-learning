<?php

namespace Tests\Support;

use LogicException;

/** Synthetic authority only: this capability cannot authorize a working operation. */
final class OfflinePermit
{
    private function __construct(private readonly array $payload, private readonly string $digest) {}

    public static function verify(string $bytes, string $signature, string $publicKey, array $expected, array $revoked, array $clock): self
    {
        try {
            if (getenv('APP_ENV') !== 'testing' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || strlen($bytes) > 16384
                || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || ! preg_match('/^[a-f0-9]{128}$/D', $signature)
                || ! sodium_crypto_sign_verify_detached(hex2bin($signature), "xlipa-offline-permit-v1\0".$bytes, $publicKey)) {
                throw new LogicException;
            }
            $p = json_decode($bytes, true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($p) || array_keys($p) !== ['v', 'permit_id', 'run_id', 'approval_ref', 'action', 'operator', 'host', 'target', 'environment', 'nbf', 'exp', 'max_duration_ms', 'hashes']
                || json_encode($p, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) !== $bytes || $p['v'] !== 1) {
                throw new LogicException;
            }
            foreach (['permit_id', 'run_id'] as $field) {
                if (! is_string($p[$field]) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $p[$field])) {
                    throw new LogicException;
                }
            }
            foreach (['approval_ref', 'operator', 'host'] as $field) {
                if (! is_string($p[$field]) || ! preg_match('/^[a-zA-Z0-9._:-]{1,100}$/D', $p[$field])) {
                    throw new LogicException;
                }
            }
            if (! in_array($p['action'], ['offline_classify', 'offline_select_release'], true) || $p['environment'] !== 'offline-synthetic'
                || $p['target'] !== $expected['target'] || $p['action'] !== $expected['action'] || $p['operator'] !== $expected['operator']
                || $p['host'] !== $expected['host'] || $p['hashes'] !== $expected['hashes'] || in_array($p['permit_id'], $revoked, true)) {
                throw new LogicException;
            }
            if (array_keys($p['hashes']) !== ['release', 'artifact', 'contract']) {
                throw new LogicException;
            }
            foreach ($p['hashes'] as $hash) {
                if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                    throw new LogicException;
                }
            }
            foreach (['nbf', 'exp', 'max_duration_ms'] as $field) {
                if (! is_int($p[$field])) {
                    throw new LogicException;
                }
            }
            if (! self::healthyClock($clock) || $p['nbf'] < 0 || $p['exp'] <= $p['nbf'] || $p['exp'] - $p['nbf'] > 300000
                || $p['max_duration_ms'] < 1 || $p['max_duration_ms'] > 60000 || $clock['utc_ms'] < $p['nbf'] || $clock['utc_ms'] >= $p['exp']) {
                throw new LogicException;
            }
            self::fixtureTarget($p['target']);

            return new self($p, hash('sha256', $bytes));
        } catch (\Throwable) {
            throw new LogicException('Offline authorization rejected.');
        }
    }

    public static function healthyClock(array $clock): bool
    {
        return ($clock['healthy'] ?? null) === true && is_int($clock['utc_ms'] ?? null) && is_int($clock['mono_ms'] ?? null) && $clock['utc_ms'] >= 0 && $clock['mono_ms'] >= 0;
    }

    public static function fixtureTarget(array $target): void
    {
        if (array_keys($target) !== ['path', 'identity', 'class'] || $target['class'] !== 'synthetic-only'
            || ! is_string($target['identity']) || ! preg_match('/^fixture-[a-f0-9]{16}$/D', $target['identity']) || ! is_string($target['path'])) {
            throw new LogicException('Offline target rejected.');
        }
        if ($target['path'] !== ':memory:') {
            // This harness has no disk-target authority, even with a valid signature.
            throw new LogicException('Offline target rejected.');
        }
    }

    public function data(): array
    {
        return $this->payload;
    }

    public function digest(): string
    {
        return $this->digest;
    }

    public function __serialize(): array
    {
        throw new LogicException('Offline capability serialization forbidden.');
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('Offline capability deserialization forbidden.');
    }

    public function __clone(): void
    {
        throw new LogicException('Offline capability cloning forbidden.');
    }
}
