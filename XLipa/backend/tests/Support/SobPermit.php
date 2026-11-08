<?php

namespace Tests\Support;

use LogicException;
use Throwable;

/** Separate, domain-separated SOB protocol; never accepts a working environment. */
final class SobPermit
{
    public const DOMAIN = "xlipa-sob-prototype-permit-v1\0";

    private function __construct(public readonly array $data, public readonly string $digest) {}

    public static function verify(string $bytes, string $signature, string $public, array $expected, array $clock, array $status): self
    {
        SobFixture::guard();
        try {
            if (strlen($bytes) > 16384 || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                || ! sodium_crypto_sign_verify_detached($signature, self::DOMAIN.$bytes, $public)) {
                throw new LogicException;
            }
            $p = SobFixture::decode($bytes);
            SobFixture::keys($p, ['v', 'id', 'run', 'action', 'operator', 'host', 'environment', 'target', 'release', 'policy', 'executor', 'nbf', 'exp', 'max_ms', 'status_epoch', 'checkpoint']);
            SobFixture::keys($expected, ['action', 'operator', 'host', 'environment', 'target', 'release', 'policy', 'executor']);
            foreach ($expected as $field => $value) {
                if (SobFixture::canonical($p[$field]) !== SobFixture::canonical($value)) {
                    throw new LogicException;
                }
            }
            SobFixture::keys($p['target'], ['root', 'file', 'identity', 'hash']);
            $prefix = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/xlipa-sob-';
            if ($p['v'] !== 1 || $p['environment'] !== 'sob-synthetic-only' || ! in_array($p['action'], ['inspect', 'backup_simulation', 'restore_simulation', 'migration_simulation'], true)
                || ! str_starts_with($p['target']['root'], $prefix) || ! preg_match('/^[a-f0-9]{20}$/D', substr($p['target']['root'], strlen($prefix)))
                || ! in_array($p['target']['file'], ['source.sqlite', 'backup.sob'], true)
                || (($p['action'] === 'restore_simulation') !== ($p['target']['file'] === 'backup.sob'))
                || ! preg_match('/^sim-operator-[a-z0-9]+$/D', $p['operator']) || ! preg_match('/^sim-host-[a-z0-9]+$/D', $p['host'])) {
                throw new LogicException;
            }
            foreach (['id', 'run'] as $field) {
                if (! is_string($p[$field]) || ! preg_match('/^[a-f0-9]{32}$/D', $p[$field])) {
                    throw new LogicException;
                }
            }
            foreach ([$p['release'], $p['policy'], $p['executor'], $p['target']['identity'], $p['target']['hash']] as $digest) {
                if (! is_string($digest) || ! preg_match('/^[a-f0-9]{64}$/D', $digest)) {
                    throw new LogicException;
                }
            }
            foreach (['nbf', 'exp', 'max_ms', 'status_epoch', 'checkpoint'] as $field) {
                if (! is_int($p[$field]) || $p[$field] < 0) {
                    throw new LogicException;
                }
            }
            if (($clock['healthy'] ?? false) !== true || ! is_int($clock['utc'] ?? null) || ! is_int($clock['mono'] ?? null) || $clock['mono'] < 0
                || $p['exp'] <= $p['nbf'] || $p['exp'] - $p['nbf'] > 300000 || $clock['utc'] < $p['nbf'] || $clock['utc'] >= $p['exp']
                || $p['max_ms'] < 1 || $p['max_ms'] > 60000 || ($status['healthy'] ?? false) !== true || ($status['suspended'] ?? true) !== false
                || ($status['epoch'] ?? null) !== $p['status_epoch'] || ! is_int($status['at'] ?? null) || $status['at'] > $clock['utc'] || $clock['utc'] - $status['at'] > 60000
                || ! is_array($status['revoked'] ?? null) || in_array($p['id'], $status['revoked'], true)) {
                throw new LogicException;
            }

            return new self($p, hash('sha256', $bytes));
        } catch (Throwable) {
            throw new LogicException('SOB authorization rejected.');
        }
    }

    public function __serialize(): array
    {
        throw new LogicException('SOB capability serialization forbidden.');
    }

    public function __clone(): void
    {
        throw new LogicException('SOB capability cloning forbidden.');
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('SOB capability deserialization forbidden.');
    }
}
