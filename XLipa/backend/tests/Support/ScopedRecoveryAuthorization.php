<?php

namespace Tests\Support;

use LogicException;

/** Signed simulated actions only; preserves the accepted memory-only permit contract. */
final class ScopedRecoveryAuthorization
{
    private function __construct(public readonly OfflinePermit $permit, public readonly array $scope) {}

    public static function verify(string $bytes, string $signature, string $publicKey, array $expected, array $scope, array $revoked, array $clock): self
    {
        $permit = OfflinePermit::verify($bytes, $signature, $publicKey, $expected['permit'], $revoked, $clock);
        if ($scope !== $expected['scope'] || array_keys($scope) !== ['operation', 'environment', 'root', 'source', 'destination', 'source_hash', 'provenance_hash']
            || ! in_array($scope['operation'], ['read_only_revalidate', 'backup', 'restore_rehearsal', 'working_migrate', 'runtime_activate'], true)
            || $scope['environment'] !== 'working-local-simulation'
            || $permit->data()['approval_ref'] !== self::reference($scope)) {
            throw new LogicException('Synthetic action authorization rejected.');
        }
        $prefix = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/';
        $root = str_replace('\\', '/', $scope['root']);
        if (! str_starts_with($root, $prefix) || ! preg_match('/^xlipa-recovery-[a-f0-9]{20}$/D', substr($root, strlen($prefix)))
            || ! preg_match('/^[a-f0-9]{64}$/D', $scope['source_hash'])
            || ! in_array($scope['source'], ['source.sqlite', 'backup.sqlite'], true)) {
            throw new LogicException('Synthetic target rejected before access.');
        }
        $destination = match ($scope['operation']) {
            'backup' => 'backup.sqlite', 'restore_rehearsal' => 'restore.sqlite', default => null,
        };
        if ($scope['destination'] !== $destination || ($scope['operation'] === 'restore_rehearsal' ? $scope['source'] !== 'backup.sqlite' || ! is_string($scope['provenance_hash']) || ! preg_match('/^[a-f0-9]{64}$/D', $scope['provenance_hash']) : $scope['source'] !== 'source.sqlite' || $scope['provenance_hash'] !== null)) {
            throw new LogicException('Synthetic action path rejected.');
        }

        return new self($permit, $scope);
    }

    public static function reference(array $scope): string
    {
        return 'simulate:'.$scope['operation'].':'.hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function __serialize(): array
    {
        throw new LogicException('No capability serialization.');
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('No capability deserialization.');
    }

    public function __clone(): void
    {
        throw new LogicException('No capability cloning.');
    }
}
