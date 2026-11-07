<?php

namespace Tests\Support;

use Closure;
use LogicException;
use PDO;
use Throwable;

/** Offline inspection harness only. Has no activation, backup or restore operation. */
final class OfflineSecurityGuard
{
    public function __construct(private readonly string $publicKey, private readonly OfflineRunRegister $register, private readonly OfflineStateClassifier $classifier, private readonly Closure $clock, private readonly ?Closure $revocations = null) {}

    public function inspect(string $bytes, string $signature, array $expected, array $revoked, string $root, string $manifest, array $requested, Closure $access): string
    {
        $start = ($this->clock)();
        $initial = $this->revocations === null ? [] : ($this->revocations)();
        if (! is_array($initial)) {
            throw new LogicException('Offline revocation state unavailable.');
        }
        $permit = OfflinePermit::verify($bytes, $signature, $this->publicKey, $expected, array_merge($revoked, $initial), $start);
        OfflineReleaseSelector::select($permit, $root, $manifest, $requested);
        $this->register->claim($permit);
        $terminal = 'unknown';
        try {
            $p = $permit->data();
            $now = ($this->clock)();
            if (! OfflinePermit::healthyClock($now) || $now['utc_ms'] < $start['utc_ms'] || $now['utc_ms'] >= $p['exp'] || $now['mono_ms'] < $start['mono_ms'] || $p['max_duration_ms'] <= $now['mono_ms'] - $start['mono_ms']) {
                throw new LogicException('Offline authorization clock rejected.');
            }
            $fresh = $this->revocations === null ? [] : ($this->revocations)();
            if (! is_array($fresh)) {
                throw new LogicException('Offline revocation state unavailable.');
            }
            OfflinePermit::verify($bytes, $signature, $this->publicKey, $expected, array_merge($revoked, $fresh), $now);
            OfflinePermit::fixtureTarget($p['target']);
            OfflineReleaseSelector::select($permit, $root, $manifest, $requested);
            if ($p['action'] === 'offline_select_release') {
                $end = ($this->clock)();
                if (! OfflinePermit::healthyClock($end) || $end['utc_ms'] < $now['utc_ms'] || $end['utc_ms'] >= $p['exp'] || $end['mono_ms'] < $now['mono_ms'] || $p['max_duration_ms'] <= $end['mono_ms'] - $start['mono_ms']) {
                    throw new LogicException('Offline authorization clock rejected.');
                }
                $fresh = $this->revocations === null ? [] : ($this->revocations)();
                if (! is_array($fresh)) {
                    throw new LogicException('Offline revocation state unavailable.');
                }
                OfflinePermit::verify($bytes, $signature, $this->publicKey, $expected, array_merge($revoked, $fresh), $end);
                $terminal = 'completed';

                return 'selected_offline_only';
            }
            // The injected fixture adapter is trusted test infrastructure, never a working opener.
            $result = $access($p['target']);
            if (! is_array($result) || array_keys($result) !== ['pdo', 'identity'] || ! $result['pdo'] instanceof PDO || $result['identity'] !== $p['target']['identity']) {
                throw new LogicException('Offline database identity mismatch.');
            }
            $pdo = $result['pdo'];
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                throw new LogicException('Offline database driver mismatch.');
            }
            $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($databases as $database) {
                if ($database['name'] === 'temp' && $database['file'] === '') {
                    continue;
                }
                $path = $p['target']['path'];
                if ($database['name'] !== 'main' || ($path === ':memory:' ? $database['file'] !== '' : realpath($database['file']) !== realpath($path))) {
                    throw new LogicException('Offline database identity mismatch.');
                }
            }
            if ((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
                throw new LogicException('Offline foreign keys required.');
            }
            $pdo->exec('PRAGMA query_only=ON');
            $state = $this->classifier->classify($pdo);
            $end = ($this->clock)();
            if (! OfflinePermit::healthyClock($end) || $end['utc_ms'] < $now['utc_ms'] || $end['utc_ms'] >= $p['exp'] || $end['mono_ms'] < $now['mono_ms'] || $p['max_duration_ms'] <= $end['mono_ms'] - $start['mono_ms']) {
                throw new LogicException('Offline authorization clock rejected.');
            }
            $fresh = $this->revocations === null ? [] : ($this->revocations)();
            if (! is_array($fresh)) {
                throw new LogicException('Offline revocation state unavailable.');
            }
            OfflinePermit::verify($bytes, $signature, $this->publicKey, $expected, array_merge($revoked, $fresh), $end);
            $terminal = str_starts_with($state, 'quarantine:') ? 'quarantined' : 'completed';

            return $state;
        } catch (Throwable $error) {
            $terminal = 'failed';
            throw $error;
        } finally {
            $this->register->finish($permit, $terminal);
        }
    }
}
