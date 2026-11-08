<?php

namespace Tests\Support;

use Closure;
use LogicException;
use SQLite3;

/** Synthetic boundary composition only. No migration or operational dispatcher. */
final class SobBoundary
{
    public function __construct(
        private readonly string $signingPublic,
        private readonly SobWitnessRegister $register,
        private readonly SobFence $fence,
        private readonly Closure $clock,
        private readonly Closure $status,
        private readonly string $releaseRoot,
        private readonly array $reviewed,
        private readonly string $encryptionPublic,
        private readonly Closure $recoveryKey,
    ) {
        SobFixture::guard();
    }

    public function run(string $bytes, string $signature, array $expected, int $fenceGeneration, array $requested = ['artifact.txt'], ?Closure $hook = null, int $capacity = 1048576, bool $ioFailure = false): array
    {
        $start = ($this->clock)();
        $permit = SobPermit::verify($bytes, $signature, $this->signingPublic, $expected, $start, ($this->status)());
        $this->fence->assert($fenceGeneration);
        $release = SobRelease::select($this->releaseRoot, $this->reviewed, $permit, $requested);
        $target = null;
        try {
            $guard = function () use ($bytes, $signature, $expected, $fenceGeneration, $start, $permit, $release): void {
                $now = ($this->clock)();
                SobPermit::verify($bytes, $signature, $this->signingPublic, $expected, $now, ($this->status)());
                if ($now['mono'] < $start['mono'] || $now['utc'] < $start['utc'] || $permit->data['max_ms'] <= $now['mono'] - $start['mono']) {
                    throw new LogicException('SOB operation duration rejected.');
                }
                $this->fence->assert($fenceGeneration);
                SobRelease::verifyHeld($this->releaseRoot, $this->reviewed, $release);
            };
            $this->register->claim($permit, $hook);
            $guard();
            SobFixture::phase($hook, 'before_target_access');
            $guard();
            $p = $permit->data;
            $root = SobFixture::root($p['target']['root']);
            $name = $p['target']['file'];
            if (($p['action'] === 'restore_simulation') !== ($name === 'backup.sob')) {
                throw new LogicException('SOB action/source mismatch.');
            }
            $target = new SobWindowsLease($root, [$name]);
            if ($target->digest() !== $p['target']['identity'] || hash('sha256', SobFixture::read($root, $name)) !== $p['target']['hash']) {
                throw new LogicException('SOB target identity/hash rejected.');
            }
            SobFixture::phase($hook, 'target_leased');
            $target->alive();
            $guard();
            if ($p['action'] === 'restore_simulation') {
                $input = SobFixture::read($root, $name);
                $envelope = SobFixture::decode($input);
                $origin = $envelope['provenance'] ?? [];
                if (! isset($origin['permit'], $origin['run']) || ! $this->register->completed($origin['permit'], $origin['run'], hash('sha256', $input))) {
                    throw new LogicException('SOB backup completion evidence missing.');
                }
                $opened = SobCustody::open($input, ($this->recoveryKey)());
                $payload = $opened['plaintext'];
                $provenance = ['v' => 1, 'permit' => $p['id'], 'run' => $p['run'], 'permit_digest' => $permit->digest, 'operation' => $p['action'], 'source' => $p['target']['hash'], 'origin' => hash('sha256', $input), 'release' => $p['release'], 'policy' => $p['policy'], 'executor' => $p['executor']];
            } else {
                $payload = $this->snapshot($root, $name);
                $provenance = ['v' => 1, 'permit' => $p['id'], 'run' => $p['run'], 'permit_digest' => $permit->digest, 'operation' => $p['action'], 'source' => $p['target']['hash'], 'origin' => null, 'release' => $p['release'], 'policy' => $p['policy'], 'executor' => $p['executor']];
            }
            if (in_array($p['action'], ['inspect', 'migration_simulation'], true)) {
                $guard();
                $target->alive();
                $result = ['synthetic_only' => true, 'execution' => $p['action'] === 'migration_simulation' ? 'disabled' : 'inspected', 'digest' => hash('sha256', $payload)];
                $this->register->complete($permit, hash('sha256', SobFixture::canonical($result)), $hook);

                return $result;
            }
            $output = $p['action'] === 'restore_simulation' ? 'restore.sob' : 'backup.sob';
            $file = @fopen($root.'/'.$output, 'x+b');
            if ($file === false) {
                throw new LogicException('SOB never overwrite output.');
            }
            try {
                SobFixture::phase($hook, 'output_reserved');
                $guard();
                $target->alive();
                $ciphertext = SobCustody::seal($payload, $provenance, $this->encryptionPublic, $hook);
                if (strlen($ciphertext) > $capacity) {
                    throw new LogicException('SOB simulated insufficient capacity.');
                }
                if ($ioFailure) {
                    SobFixture::write($file, substr($ciphertext, 0, 16));
                    throw new LogicException('SOB simulated I/O failure.');
                }
                $guard();
                SobFixture::write($file, $ciphertext);
                SobFixture::phase($hook, 'output_published');
                $guard();
                $target->alive();
                if (SobFixture::read($root, $output) !== $ciphertext || hash('sha256', SobFixture::read($root, $name)) !== $p['target']['hash']) {
                    throw new LogicException('SOB publication/source mismatch.');
                }
                $verified = SobCustody::open($ciphertext, ($this->recoveryKey)());
                if ($verified['plaintext'] !== $payload || SobFixture::canonical($verified['provenance']) !== SobFixture::canonical($provenance)) {
                    throw new LogicException('SOB custody roundtrip mismatch.');
                }
                $guard();
                $this->register->complete($permit, hash('sha256', $ciphertext), $hook);

                return ['synthetic_only' => true, 'execution' => 'encrypted_fixture_published', 'output' => $output, 'digest' => hash('sha256', $ciphertext)];
            } finally {
                fclose($file);
            }
        } finally {
            $target?->close();
            $release->close();
        }
    }

    private function snapshot(string $root, string $name): string
    {
        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            if (file_exists($root.'/'.$name.$suffix)) {
                throw new LogicException('SOB unexpected SQLite journal state.');
            }
        }
        $header = substr(SobFixture::read($root, $name), 0, 20);
        if (strlen($header) !== 20 || substr($header, 0, 16) !== "SQLite format 3\0" || ord($header[18]) !== 1 || ord($header[19]) !== 1) {
            throw new LogicException('SOB unexpected SQLite header.');
        }
        $db = new SQLite3($root.'/'.$name, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(0);
        try {
            $db->exec('PRAGMA query_only=ON');
            $db->exec('PRAGMA temp_store=MEMORY');
            $db->exec('PRAGMA foreign_keys=ON');
            $authorizer = new OfflineInspectorAuthorizer;
            $db->setAuthorizer($authorizer);
            $db->exec('BEGIN');
            if ($db->querySingle('PRAGMA integrity_check') !== 'ok' || $db->querySingle('PRAGMA foreign_key_check') !== null) {
                throw new LogicException('SOB SQLite integrity rejected.');
            }
            $schema = $db->querySingle("SELECT sql FROM sqlite_master WHERE name='fixture_rows'");
            $ledger = $db->querySingle('SELECT state FROM fixture_ledger');
            if ($schema !== 'CREATE TABLE fixture_rows (id INTEGER PRIMARY KEY, payload TEXT NOT NULL)' || $ledger !== 'foundation') {
                throw new LogicException('SOB unknown migration completion state.');
            }
            $objects = $db->querySingle("SELECT count(*) FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'");
            if ($objects !== 2) {
                throw new LogicException('SOB synthetic schema drift.');
            }
            $rows = [];
            $query = $db->query('SELECT id,payload FROM fixture_rows ORDER BY id');
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
            $query->finalize();
            $db->exec('ROLLBACK');

            return SobFixture::canonical(['schema' => $schema, 'ledger' => $ledger, 'rows' => $rows]);
        } finally {
            $db->close();
        }
    }
}
