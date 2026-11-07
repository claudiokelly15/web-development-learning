<?php

namespace Tests\Support;

use Closure;
use LogicException;
use PDO;
use SQLite3;
use Throwable;

/** CLI-oriented fixture orchestration. There is deliberately no working execution mode. */
final class SyntheticRecoveryPackage
{
    private readonly OfflineRunRegister $register;

    public function __construct(private readonly string $publicKey, private readonly string $registerRoot, private readonly OfflineStateClassifier $classifier, private readonly Closure $clock, private readonly ?Closure $revocations = null)
    {
        $this->register = new OfflineRunRegister($registerRoot);
        if (! is_file($registerRoot.'/recovery-results.anchor') || is_link($registerRoot.'/recovery-results.anchor') || file_get_contents($registerRoot.'/recovery-results.anchor') !== 'synthetic-results-v1' || ! is_file($registerRoot.'/recovery-results.log') || is_link($registerRoot.'/recovery-results.log')) {
            throw new LogicException('Recovery result evidence unavailable.');
        }
    }

    public static function initializeFixtureResults(string $root): void
    {
        new OfflineRunRegister($root);
        foreach (['recovery-results.anchor' => 'synthetic-results-v1', 'recovery-results.log' => ''] as $name => $bytes) {
            if (file_exists($root.'/'.$name) || is_link($root.'/'.$name)) {
                throw new LogicException('Result evidence initialization refused.');
            }
            $file = fopen($root.'/'.$name, 'x+b');
            if ($file === false) {
                throw new LogicException('Result evidence initialization failed.');
            }
            try {
                if (fwrite($file, $bytes) !== strlen($bytes) || ! fflush($file) || ! fsync($file)) {
                    throw new LogicException('Result evidence initialization failed.');
                }
            } finally {
                fclose($file);
            }
        }
    }

    private function resultEvidence(array $receipt, string $digest, bool $append): bool
    {
        $path = $this->registerRoot.'/recovery-results.log';
        if (! is_file($path) || is_link($path) || ! is_file($this->registerRoot.'/recovery-results.anchor') || is_link($this->registerRoot.'/recovery-results.anchor') || file_get_contents($this->registerRoot.'/recovery-results.anchor') !== 'synthetic-results-v1') {
            throw new LogicException('Recovery result evidence unavailable.');
        }
        $file = fopen($path, 'r+b');
        if ($file === false) {
            throw new LogicException('Recovery result evidence unavailable.');
        }
        try {
            if (! flock($file, LOCK_EX)) {
                throw new LogicException('Result evidence lock failed.');
            }
            $bytes = stream_get_contents($file, 1048577);
            if (strlen($bytes) > 1048576 || ($bytes !== '' && ! str_ends_with($bytes, "\n"))) {
                throw new LogicException('Result evidence corrupt.');
            }
            $previous = str_repeat('0', 64);
            $seen = [];
            $found = false;
            foreach (array_filter(explode("\n", $bytes)) as $line) {
                $row = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                if (array_keys($row) !== ['permit', 'run', 'receipt', 'previous', 'checksum']) {
                    throw new LogicException('Result evidence corrupt.');
                }
                $checksum = $row['checksum'];
                unset($row['checksum']);
                if ($row['previous'] !== $previous || hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)) !== $checksum || isset($seen[$row['permit']])) {
                    throw new LogicException('Result evidence corrupt.');
                }
                $seen[$row['permit']] = true;
                $previous = $checksum;
                if ($row['permit'] === $receipt['permit_id'] && $row['run'] === $receipt['run_id'] && $row['receipt'] === $digest) {
                    $found = true;
                }
            }
            if (! $append) {
                return $found;
            }
            if (isset($seen[$receipt['permit_id']])) {
                throw new LogicException('Result evidence already sealed.');
            }
            $row = ['permit' => $receipt['permit_id'], 'run' => $receipt['run_id'], 'receipt' => $digest, 'previous' => $previous];
            $row['checksum'] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
            $record = json_encode($row, JSON_THROW_ON_ERROR)."\n";
            fseek($file, 0, SEEK_END);
            if (fwrite($file, $record) !== strlen($record) || ! fflush($file) || ! fsync($file)) {
                throw new LogicException('Result evidence incomplete.');
            }

            return true;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function completedReceipt(array $receipt, string $receiptDigest): bool
    {
        if (! $this->resultEvidence($receipt, $receiptDigest, false)) {
            return false;
        }
        // The accepted register validates the chain before this run's claim. Recheck
        // it under a shared lock when consuming backup evidence after a restart.
        $path = $this->registerRoot.'/claims.log';
        if (! is_file($path) || is_link($path)) {
            return false;
        }
        $file = fopen($path, 'rb');
        if ($file === false) {
            return false;
        }
        try {
            if (! flock($file, LOCK_SH)) {
                return false;
            }
            $bytes = stream_get_contents($file, 1048577);
            if (strlen($bytes) > 1048576 || ! str_ends_with($bytes, "\n")) {
                return false;
            }
            $previous = str_repeat('0', 64);
            $found = null;
            foreach (array_filter(explode("\n", $bytes)) as $line) {
                $entry = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                $checksum = $entry['checksum'];
                unset($entry['checksum']);
                if ($entry['previous'] !== $previous || hash('sha256', json_encode($entry, JSON_THROW_ON_ERROR)) !== $checksum) {
                    return false;
                }
                $previous = $checksum;
                if ($entry['permit'] === $receipt['permit_id'] && $entry['run'] === $receipt['run_id'] && $entry['digest'] === $receipt['permit_digest']) {
                    $found = $entry['state'];
                }
            }

            return $found === 'completed';
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    public function run(string $bytes, string $signature, array $expected, array $scope, string $releaseRoot, string $manifest, array $requested, ?Closure $hook = null): array
    {
        if (PHP_SAPI !== 'cli') {
            throw new LogicException('Offline CLI only.');
        }
        $start = ($this->clock)();
        $authorization = $this->verify($bytes, $signature, $expected, $scope, $start);
        $this->release($authorization, $releaseRoot, $manifest, $requested);
        $this->register->claim($authorization->permit);
        $terminal = 'unknown';
        try {
            $this->fresh($authorization, $start, $bytes, $signature, $expected);
            $this->release($authorization, $releaseRoot, $manifest, $requested);
            $p = $authorization->permit->data();
            $this->resultEvidence(['permit_id' => $p['permit_id'], 'run_id' => $p['run_id']], str_repeat('0', 64), false);
            $this->phase($hook, 'before_access');
            $guard = fn () => $this->fresh($authorization, $start, $bytes, $signature, $expected);
            $guard();
            $result = $this->operate($authorization, $hook, $guard);
            $this->release($authorization, $releaseRoot, $manifest, $requested);
            $this->fresh($authorization, $start, $bytes, $signature, $expected);
            if (isset($result['receipt'])) {
                $this->resultEvidence($result['receipt'], $result['receipt_hash'], true);
            }
            $terminal = 'completed';

            return $result;
        } catch (Throwable $error) {
            $terminal = 'quarantined';
            throw $error;
        } finally {
            $this->register->finish($authorization->permit, $terminal);
        }
    }

    private function verify(string $bytes, string $signature, array $expected, array $scope, array $clock): ScopedRecoveryAuthorization
    {
        $revoked = $this->revocations === null ? [] : ($this->revocations)();
        if (! is_array($revoked)) {
            throw new LogicException('Revocation authority unavailable.');
        }

        return ScopedRecoveryAuthorization::verify($bytes, $signature, $this->publicKey, $expected, $scope, $revoked, $clock);
    }

    private function fresh(ScopedRecoveryAuthorization $authorization, array $start, string $bytes, string $signature, array $expected): void
    {
        $now = ($this->clock)();
        $p = $authorization->permit->data();
        if (! OfflinePermit::healthyClock($now) || $now['utc_ms'] < $start['utc_ms'] || $now['mono_ms'] < $start['mono_ms'] || $p['max_duration_ms'] <= $now['mono_ms'] - $start['mono_ms']) {
            throw new LogicException('Authorization clock rejected.');
        }
        $this->verify($bytes, $signature, $expected, $authorization->scope, $now);
    }

    private function release(ScopedRecoveryAuthorization $authorization, string $root, string $manifest, array $requested): void
    {
        $selection = OfflineReleaseSelector::select($authorization->permit, $root, $manifest, $requested);
        if ($selection['artifact_hash'] !== 'be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97'
            || $selection['contract_hash'] !== '09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0') {
            throw new LogicException('Unreviewed activation revision.');
        }
    }

    private function phase(?Closure $hook, string $phase): void
    {
        if ($hook !== null) {
            $hook($phase);
        }
    }

    private function root(array $scope): string
    {
        $root = $scope['root'];
        if (is_link($root) || ! is_dir($root) || realpath(dirname($root)) !== realpath(sys_get_temp_dir())
            || str_replace('\\', '/', realpath($root)) !== str_replace('\\', '/', $root)) {
            throw new LogicException('Synthetic root identity rejected.');
        }

        return $root;
    }

    private function source(string $path): SQLite3
    {
        if (is_link($path) || ! is_file($path)) {
            throw new LogicException('Existing synthetic source required.');
        }
        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            if (file_exists($path.$suffix)) {
                throw new LogicException('Ambiguous source sidecar state.');
            }
        }
        $header = file_get_contents($path, false, null, 0, 20);
        if (strlen($header) !== 20 || substr($header, 0, 16) !== "SQLite format 3\0" || ord($header[18]) !== 1 || ord($header[19]) !== 1) {
            throw new LogicException('Unsupported source header.');
        }
        $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(0);
        $db->exec('PRAGMA query_only=ON');
        $db->exec('PRAGMA temp_store=MEMORY');
        $db->exec('PRAGMA foreign_keys=ON');

        return $db;
    }

    private function state(string $path): string
    {
        if (! defined('PDO::SQLITE_ATTR_OPEN_FLAGS')) {
            throw new LogicException('Read-only PDO capability unavailable.');
        }
        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 0, PDO::SQLITE_ATTR_OPEN_FLAGS => SQLITE3_OPEN_READONLY]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA query_only=ON');
        $state = $this->classifier->classify($pdo);
        $pdo = null;
        if (! in_array($state, ['foundation', 'completed'], true)) {
            throw new LogicException('Recovery quarantine: '.$state);
        }

        return $state;
    }

    private function rows(SQLite3 $db, string $sql): array
    {
        $result = $db->query($sql);
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        $result->finalize();

        return $rows;
    }

    private function snapshot(SQLite3 $db): string
    {
        if ($this->rows($db, 'PRAGMA integrity_check') !== [['integrity_check' => 'ok']] || $this->rows($db, 'PRAGMA foreign_key_check') !== []) {
            throw new LogicException('Recovery integrity quarantine.');
        }
        $objects = $this->rows($db, "SELECT type,name,tbl_name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name");
        $digests = [];
        foreach ($this->rows($db, "SELECT name FROM sqlite_master WHERE type='table' AND (name NOT LIKE 'sqlite_%' OR name='sqlite_sequence') ORDER BY name") as $table) {
            $name = $table['name'];
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
                throw new LogicException('Unsupported fixture identifier.');
            }
            $columns = $this->rows($db, 'PRAGMA table_info("'.$name.'")');
            $pk = array_values(array_filter($columns, static fn (array $column): bool => $column['pk'] > 0));
            usort($pk, static fn (array $a, array $b): int => $a['pk'] <=> $b['pk']);
            $order = $name === 'sqlite_sequence' ? 'name' : implode(',', array_map(static fn (array $column): string => '"'.$column['name'].'"', $pk));
            if ($order === '') {
                throw new LogicException('Fixture table has no deterministic key.');
            }
            $digests[$name] = hash('sha256', serialize($this->rows($db, 'SELECT * FROM "'.$name.'" ORDER BY '.$order)));
        }

        return hash('sha256', serialize([$objects, $digests]));
    }

    private function operate(ScopedRecoveryAuthorization $authorization, ?Closure $hook, Closure $guard): array
    {
        $scope = $authorization->scope;
        $root = $this->root($scope);
        $sourcePath = $root.'/'.$scope['source'];
        if (! is_file($sourcePath) || is_link($sourcePath) || ! hash_equals($scope['source_hash'], hash_file('sha256', $sourcePath))) {
            throw new LogicException('Synthetic source identity/hash mismatch.');
        }
        $source = $this->source($sourcePath);
        $destination = null;
        $reservation = null;
        $begun = false;
        try {
            $state = $this->state($sourcePath);
            $source->exec('BEGIN');
            $begun = true;
            $logical = $this->snapshot($source);
            if ($scope['destination'] === null) {
                if (hash_file('sha256', $sourcePath) !== $scope['source_hash']) {
                    throw new LogicException('Source changed during classification.');
                }

                return ['simulation_only' => true, 'operation' => $scope['operation'], 'state' => $state, 'execution' => $scope['operation'] === 'read_only_revalidate' ? 'classified' : 'disabled'];
            }
            $origin = $scope['source_hash'];
            if ($scope['operation'] === 'restore_rehearsal') {
                $receiptPath = $sourcePath.'.receipt.json';
                if (! is_file($receiptPath) || is_link($receiptPath) || filesize($receiptPath) > 16384 || hash_file('sha256', $receiptPath) !== $scope['provenance_hash']) {
                    throw new LogicException('Backup provenance mismatch.');
                }
                $receiptBytes = file_get_contents($receiptPath);
                $receipt = json_decode($receiptBytes, true, 8, JSON_THROW_ON_ERROR);
                if (($receipt['operation'] ?? null) !== 'backup' || ($receipt['output_file_hash'] ?? null) !== $scope['source_hash'] || ($receipt['logical_digest'] ?? null) !== $logical || ($receipt['state'] ?? null) !== $state || ! $this->completedReceipt($receipt, hash('sha256', $receiptBytes))) {
                    throw new LogicException('Backup receipt/content mismatch.');
                }
                $origin = $receipt['origin_file_hash'];
            }
            $destinationPath = $root.'/'.$scope['destination'];
            if (file_exists($destinationPath) || is_link($destinationPath) || file_exists($destinationPath.'.receipt.json')) {
                throw new LogicException('Destination exists: never overwrite.');
            }
            foreach (['-wal', '-shm', '-journal'] as $suffix) {
                if (file_exists($destinationPath.$suffix)) {
                    throw new LogicException('Destination sidecar ambiguity.');
                }
            }
            $guard();
            $reservation = fopen($destinationPath, 'x+b');
            if ($reservation === false) {
                throw new LogicException('Destination reservation failed.');
            }
            $this->phase($hook, 'destination_reserved');
            $guard();
            $destination = new SQLite3($destinationPath, SQLITE3_OPEN_READWRITE);
            $destination->enableExceptions(true);
            $destination->busyTimeout(0);
            if (! method_exists($source, 'backup') || ! $source->backup($destination, 'main', 'main')) {
                throw new LogicException('SQLite backup API did not complete.');
            }
            $destination->close();
            $destination = null;
            if (! fflush($reservation) || ! fsync($reservation)) {
                throw new LogicException('Destination flush failed.');
            }
            fclose($reservation);
            $reservation = null;
            $this->phase($hook, 'snapshot_copied');
            $copy = $this->source($destinationPath);
            try {
                if ($this->snapshot($copy) !== $logical || $this->state($destinationPath) !== $state) {
                    throw new LogicException('Copied snapshot mismatch.');
                }
            } finally {
                $copy->close();
            }
            if (hash_file('sha256', $sourcePath) !== $scope['source_hash']) {
                throw new LogicException('Source changed during run.');
            }
            $this->phase($hook, 'before_receipt');
            $guard();
            $p = $authorization->permit->data();
            $receipt = ['v' => 1, 'operation' => $scope['operation'], 'permit_id' => $p['permit_id'], 'run_id' => $p['run_id'], 'permit_digest' => $authorization->permit->digest(), 'origin_file_hash' => $origin, 'source_file_hash' => $scope['source_hash'], 'output_file_hash' => hash_file('sha256', $destinationPath), 'logical_digest' => $logical, 'state' => $state, 'source_receipt_hash' => $scope['provenance_hash']];
            $receiptBytes = json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $file = fopen($destinationPath.'.receipt.json', 'x+b');
            if ($file === false) {
                throw new LogicException('Receipt already exists or unavailable.');
            }
            try {
                if (fwrite($file, $receiptBytes) !== strlen($receiptBytes) || ! fflush($file) || ! fsync($file)) {
                    throw new LogicException('Receipt incomplete.');
                }
            } finally {
                fclose($file);
            }
            $this->phase($hook, 'receipt_published');

            return ['simulation_only' => true, 'receipt' => $receipt, 'receipt_hash' => hash('sha256', $receiptBytes)];
        } finally {
            if ($destination !== null) {
                $destination->close();
            }
            if (is_resource($reservation)) {
                fclose($reservation);
            }
            if ($begun) {
                $source->exec('ROLLBACK');
            }
            $source->close();
        }
    }
}
