<?php

namespace Tests\Support;

use LogicException;

/** Locked append-only fixture journal outside SQLite; no automatic claim release. */
final class OfflineRunRegister
{
    public function __construct(private readonly string $root)
    {
        self::root($root);
        if (! is_file($root.'/register.anchor') || is_link($root.'/register.anchor') || file_get_contents($root.'/register.anchor') !== 'offline-register-v1'
            || ! is_file($root.'/claims.log') || is_link($root.'/claims.log')) {
            throw new LogicException('Offline register unavailable.');
        }
    }

    private static function root(string $root): void
    {
        $prefix = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/';
        $path = str_replace('\\', '/', $root);
        if (getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! str_starts_with($path, $prefix)
            || ! preg_match('/^xlipa-offline-guard-[a-f0-9]+$/D', substr($path, strlen($prefix))) || is_link($root)
            || realpath(dirname($root)) !== realpath(sys_get_temp_dir()) || ! is_dir($root)) {
            throw new LogicException('Offline register unavailable.');
        }
    }

    /** Explicit fixture bootstrap; never invoked by the authorization path/restart. */
    public static function initializeFixture(string $root): void
    {
        self::root($root);
        if (array_values(array_diff(scandir($root), ['.', '..'])) !== []) {
            throw new LogicException('Offline register initialization refused.');
        }
        foreach (['register.anchor' => 'offline-register-v1', 'claims.log' => ''] as $name => $bytes) {
            $file = fopen($root.'/'.$name, 'x+b');
            if ($file === false) {
                throw new LogicException('Offline register initialization refused.');
            }
            try {
                if (fwrite($file, $bytes) !== strlen($bytes) || ! fflush($file) || ! fsync($file)) {
                    throw new LogicException('Offline register initialization failed.');
                }
            } finally {
                fclose($file);
            }
        }
    }

    private function append(OfflinePermit $permit, string $state): void
    {
        $path = $this->root.'/claims.log';
        if (is_link($path) || ! is_file($path)) {
            throw new LogicException('Offline register unavailable.');
        }
        $file = fopen($path, 'r+b');
        if ($file === false) {
            throw new LogicException('Offline register unavailable.');
        }
        try {
            if (! flock($file, LOCK_EX)) {
                throw new LogicException('Offline register unavailable.');
            }
            $contents = stream_get_contents($file, 1048577);
            if (strlen($contents) > 1048576 || ($contents !== '' && ! str_ends_with($contents, "\n"))) {
                throw new LogicException('Offline register corrupt.');
            }
            $states = [];
            $previous = str_repeat('0', 64);
            foreach (array_filter(explode("\n", $contents), fn (string $line): bool => $line !== '') as $line) {
                $entry = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                if (! is_array($entry) || array_keys($entry) !== ['permit', 'run', 'digest', 'state', 'previous', 'checksum']) {
                    throw new LogicException('Offline register corrupt.');
                }
                $checksum = $entry['checksum'];
                unset($entry['checksum']);
                if ($entry['previous'] !== $previous || ! hash_equals(hash('sha256', json_encode($entry, JSON_THROW_ON_ERROR)), $checksum)) {
                    throw new LogicException('Offline register corrupt.');
                }
                $previous = $checksum;
                if (! in_array($entry['state'], ['claimed', 'completed', 'failed', 'unknown', 'quarantined'], true)
                    || (isset($states[$entry['permit']]) && ($states[$entry['permit']]['state'] !== 'claimed' || $entry['state'] === 'claimed' || $states[$entry['permit']]['run'] !== $entry['run'] || $states[$entry['permit']]['digest'] !== $entry['digest']))) {
                    throw new LogicException('Offline register corrupt.');
                }
                if (! isset($states[$entry['permit']]) && $entry['state'] !== 'claimed') {
                    throw new LogicException('Offline register corrupt.');
                }
                $states[$entry['permit']] = $entry;
            }
            $p = $permit->data();
            if ($state === 'claimed') {
                foreach ($states as $entry) {
                    if ($entry['permit'] === $p['permit_id'] || $entry['run'] === $p['run_id']) {
                        throw new LogicException('Offline permit already consumed.');
                    }
                }
            } elseif (! isset($states[$p['permit_id']]) || $states[$p['permit_id']]['state'] !== 'claimed' || $states[$p['permit_id']]['digest'] !== $permit->digest()) {
                throw new LogicException('Offline claim transition rejected.');
            }
            $entry = ['permit' => $p['permit_id'], 'run' => $p['run_id'], 'digest' => $permit->digest(), 'state' => $state, 'previous' => $previous];
            $entry['checksum'] = hash('sha256', json_encode($entry, JSON_THROW_ON_ERROR));
            $bytes = json_encode($entry, JSON_THROW_ON_ERROR)."\n";
            fseek($file, 0, SEEK_END);
            for ($offset = 0; $offset < strlen($bytes);) {
                $written = fwrite($file, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new LogicException('Offline register write failed.');
                }$offset += $written;
            }
            if (! fflush($file) || ! fsync($file)) {
                throw new LogicException('Offline claim durability unavailable.');
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    public function claim(OfflinePermit $permit): void
    {
        $this->append($permit, 'claimed');
    }

    public function finish(OfflinePermit $permit, string $state): void
    {
        if (! in_array($state, ['completed', 'failed', 'unknown', 'quarantined'], true)) {
            throw new LogicException('Offline claim transition rejected.');
        }
        $this->append($permit, $state);
    }
}
