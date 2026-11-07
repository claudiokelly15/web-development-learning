<?php

namespace Tests\Feature;

use App\Actions\Merchants\CreateMerchant;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SQLite3;
use Symfony\Component\Process\Process;
use Tests\Support\B32CRehearsal;
use Tests\Support\OfflineRunRegister;
use Tests\Support\OfflineStateClassifier;
use Tests\Support\ScopedRecoveryAuthorization;
use Tests\Support\SyntheticRecoveryPackage;
use Tests\TestCase;

class SyntheticRecoveryPackageTest extends TestCase
{
    private string $root;

    private string $registerRoot;

    private string $releaseRoot;

    private string $secret;

    private string $public;

    private string $manifest;

    private array $hashes;

    private OfflineStateClassifier $classifier;

    private array $clockState = ['utc_ms' => 1000, 'mono_ms' => 1000, 'healthy' => true];

    private int $accesses = 0;

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        foreach (['alpha', 'beta'] as $tenant) {
            $user = User::factory()->create(['email' => $tenant.'@fixture.test']);
            $merchant = app(CreateMerchant::class)->execute($user, 'Synthetic '.$tenant);
            DB::table('merchant_applications')->where('merchant_id', $merchant->id)->update(['profile_snapshot' => Crypt::encryptString('{"synthetic":true}'), 'internal_review_notes' => Crypt::encryptString('Private synthetic fixture')]);
        }
        foreach (['merchant.created', 'membership.revoked', 'application.submitted', 'application.reviewed', 'platform_role.granted', 'platform_role.revoked', 'account.status_changed', 'merchant.status_changed'] as $event) {
            DB::table('audit_logs')->insert(['id' => strtolower((string) Str::ulid()), 'event_type' => $event, 'request_id' => (string) Str::uuid(), 'metadata' => ' { "reason_code" : "fixture" } ', 'occurred_at' => '2026-11-07 01:02:03.456']);
        }
        $temp = str_replace('\\', '/', realpath(sys_get_temp_dir()));
        $this->root = $temp.'/xlipa-recovery-'.bin2hex(random_bytes(10));
        $this->registerRoot = $temp.'/xlipa-offline-guard-'.bin2hex(random_bytes(10));
        $this->releaseRoot = $temp.'/xlipa-offline-release-'.bin2hex(random_bytes(10));
        foreach ([$this->root, $this->registerRoot, $this->releaseRoot] as $root) {
            mkdir($root, 0700);
        }
        OfflineRunRegister::initializeFixture($this->registerRoot);
        SyntheticRecoveryPackage::initializeFixtureResults($this->registerRoot);
        mkdir($this->releaseRoot.'/activation');
        copy(B32CRehearsal::artifact(), $this->releaseRoot.'/activation/2026_11_07_200000_activate_b32_security_contract.php');
        copy(database_path('activation/b32c/security-contract.json'), $this->releaseRoot.'/activation/security-contract.json');
        $this->releaseManifest();
        $keys = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($keys);
        $this->public = sodium_crypto_sign_publickey($keys);
        sodium_memzero($keys);
        $f = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true);
        $c = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true);
        $final = $f['foundation_objects'];
        $final['table:audit_logs'] = hash('sha256', $c['audit']['sql']);
        foreach (['create_tables' => 'table', 'indexes' => 'index', 'triggers' => 'trigger'] as $group => $kind) {
            foreach ($c[$group] as $name => $sql) {
                $final[$kind.':'.$name] = hash('sha256', $sql);
            }
        }
        ksort($final);
        $this->classifier = new OfflineStateClassifier($f['foundation_objects'], $f['foundation_ledger'], $final, '2026_11_07_200000_activate_b32_security_contract');
        $this->exportFixture();
    }

    private function exportFixture(): void
    {
        // SQL construction from independently generated memory fixtures, not a real DB copy.
        $db = new SQLite3($this->root.'/source.sqlite', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $db->enableExceptions(true);
        $db->exec('PRAGMA foreign_keys=ON');
        $db->exec('BEGIN');
        $db->exec('PRAGMA defer_foreign_keys=ON');
        $objects = B32CRehearsal::schema();
        foreach (['table', 'index', 'trigger'] as $type) {
            foreach ($objects as $object) {
                if ($object->type === $type) {
                    $db->exec($object->sql);
                }
            }
        }
        foreach ($objects as $object) {
            if ($object->type !== 'table') {
                continue;
            }
            foreach (DB::select('SELECT * FROM "'.$object->name.'" ORDER BY rowid') as $row) {
                $row = (array) $row;
                $statement = $db->prepare('INSERT INTO "'.$object->name.'" ('.implode(',', array_map(static fn ($column) => '"'.$column.'"', array_keys($row))).') VALUES ('.implode(',', array_fill(0, count($row), '?')).')');
                foreach (array_values($row) as $index => $value) {
                    $statement->bindValue($index + 1, $value, $value === null ? SQLITE3_NULL : (is_int($value) ? SQLITE3_INTEGER : (is_float($value) ? SQLITE3_FLOAT : SQLITE3_TEXT)));
                }
                $result = $statement->execute();
                $result->finalize();
                $statement->close();
            }
        }
        $db->exec('COMMIT');
        $db->close();
    }

    private function releaseManifest(): void
    {
        $a = 'activation/2026_11_07_200000_activate_b32_security_contract.php';
        $c = 'activation/security-contract.json';
        $files = [$a => hash_file('sha256', $this->releaseRoot.'/'.$a), $c => hash_file('sha256', $this->releaseRoot.'/'.$c)];
        $this->manifest = json_encode(['version' => 1, 'mode' => 'offline-synthetic', 'artifact' => $a, 'contract' => $c, 'files' => $files], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->hashes = ['release' => hash('sha256', $this->manifest), 'artifact' => $files[$a], 'contract' => $files[$c]];
    }

    private function request(string $operation = 'backup'): array
    {
        $source = $operation === 'restore_rehearsal' ? 'backup.sqlite' : 'source.sqlite';
        $scope = ['operation' => $operation, 'environment' => 'working-local-simulation', 'root' => $this->root, 'source' => $source, 'destination' => match ($operation) {
            'backup' => 'backup.sqlite', 'restore_rehearsal' => 'restore.sqlite', default => null
        }, 'source_hash' => hash_file('sha256', $this->root.'/'.$source), 'provenance_hash' => $operation === 'restore_rehearsal' ? hash_file('sha256', $this->root.'/backup.sqlite.receipt.json') : null];
        $p = ['v' => 1, 'permit_id' => (string) Str::uuid(), 'run_id' => (string) Str::uuid(), 'approval_ref' => ScopedRecoveryAuthorization::reference($scope), 'action' => 'offline_classify', 'operator' => 'fixture-operator', 'host' => 'fixture-host', 'target' => ['path' => ':memory:', 'identity' => 'fixture-'.bin2hex(random_bytes(8)), 'class' => 'synthetic-only'], 'environment' => 'offline-synthetic', 'nbf' => 0, 'exp' => 20000, 'max_duration_ms' => 10000, 'hashes' => $this->hashes];
        $expected = ['permit' => array_intersect_key($p, array_flip(['action', 'operator', 'host', 'target', 'hashes'])), 'scope' => $scope];
        $bytes = json_encode($p, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = bin2hex(sodium_crypto_sign_detached("xlipa-offline-permit-v1\0".$bytes, $this->secret));

        return compact('bytes', 'signature', 'expected', 'scope');
    }

    private function runRequest(array $request, ?\Closure $hook = null, ?array $requested = null): array
    {
        $tool = new SyntheticRecoveryPackage($this->public, $this->registerRoot, $this->classifier, fn () => $this->clockState);

        return $tool->run($request['bytes'], $request['signature'], $request['expected'], $request['scope'], $this->releaseRoot, $this->manifest, $requested ?? ['activation/2026_11_07_200000_activate_b32_security_contract.php'], function (string $phase) use ($hook): void {
            if ($phase === 'before_access') {
                $this->accesses++;
            } if ($hook !== null) {
                $hook($phase);
            }
        });
    }

    private function rejects(callable $operation, string $contains): void
    {
        try {
            $operation();
            $this->fail('Unsafe request allowed');
        } catch (\Exception $e) {
            $this->assertStringContainsString($contains, $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->secret)) {
            sodium_memzero($this->secret);
        }
        foreach ([$this->root, $this->registerRoot, $this->releaseRoot] as $root) {
            if (realpath(dirname($root)) !== realpath(sys_get_temp_dir()) || ! preg_match('/^xlipa-(recovery|offline-guard|offline-release)-[a-f0-9]+$/D', basename($root))) {
                throw new LogicException('Unsafe cleanup');
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
                if ($item->isDir() && ! $item->isLink()) {
                    rmdir($item->getPathname());
                } else {
                    unlink($item->getPathname());
                }
            }
            rmdir($root);
        }
        parent::tearDown();
    }

    public function test_consistent_api_backup_and_independently_authorized_restore_preserve_bytes_and_provenance(): void
    {
        $before = hash_file('sha256', $this->root.'/source.sqlite');
        $backupRequest = $this->request();
        $backup = $this->runRequest($backupRequest);
        $this->assertSame('foundation', $backup['receipt']['state']);
        $this->assertSame($before, hash_file('sha256', $this->root.'/source.sqlite'));
        $backupHash = hash_file('sha256', $this->root.'/backup.sqlite');
        $restore = $this->runRequest($this->request('restore_rehearsal'));
        $this->assertSame($backupHash, hash_file('sha256', $this->root.'/backup.sqlite'));
        $this->assertSame($backup['receipt']['logical_digest'], $restore['receipt']['logical_digest']);
        $this->assertSame($before, $restore['receipt']['origin_file_hash']);
        $this->assertSame($backup['receipt_hash'], $restore['receipt']['source_receipt_hash']);
        $this->assertNotSame($backup['receipt']['permit_id'], $restore['receipt']['permit_id']);
        $db = new SQLite3($this->root.'/restore.sqlite', SQLITE3_OPEN_READONLY);
        $this->assertSame(2, $db->querySingle('SELECT count(*) FROM users'));
        $this->assertSame(9, $db->querySingle('SELECT count(*) FROM migrations'));
        $this->assertSame('ok', $db->querySingle('PRAGMA integrity_check'));
        $db->close();
        foreach (['source.sqlite', 'backup.sqlite', 'restore.sqlite'] as $name) {
            foreach (['-wal', '-shm', '-journal'] as $suffix) {
                $this->assertFileDoesNotExist($this->root.'/'.$name.$suffix);
            }
        }
        $this->rejects(fn () => $this->runRequest($backupRequest), 'consumed');
    }

    public static function plannedActions(): array
    {
        return [['read_only_revalidate'], ['working_migrate'], ['runtime_activate']];
    }

    #[DataProvider('plannedActions')]
    public function test_scoped_actions_are_classification_or_disabled_metadata_only(string $action): void
    {
        $before = hash_file('sha256', $this->root.'/source.sqlite');
        $result = $this->runRequest($this->request($action));
        $this->assertTrue($result['simulation_only']);
        $this->assertSame($action === 'read_only_revalidate' ? 'classified' : 'disabled', $result['execution']);
        $this->assertSame($before, hash_file('sha256', $this->root.'/source.sqlite'));
        $this->assertSame(['source.sqlite'], array_values(array_diff(scandir($this->root), ['.', '..'])));
    }

    public static function invalidAuthorizations(): array
    {
        return array_map(fn ($x) => [$x], ['missing', 'signature', 'expired', 'operator', 'target', 'scope_action', 'scope_target', 'environment', 'working_path', 'release_hash', 'revoked_state']);
    }

    #[DataProvider('invalidAuthorizations')]
    public function test_invalid_authority_never_reaches_target_access(string $case): void
    {
        $r = $this->request();
        match ($case) {
            'missing' => $r['bytes'] = '', 'signature' => $r['signature'] = str_repeat('0', 128),
            'expired' => $this->clockState['utc_ms'] = 20000,
            'operator' => $r['expected']['permit']['operator'] = 'other',
            'target' => $r['expected']['permit']['target']['identity'] = 'fixture-0000000000000000',
            'scope_action' => $r['scope']['operation'] = 'restore_rehearsal',
            'scope_target' => $r['scope']['source'] = 'other.sqlite',
            'environment' => $r['scope']['environment'] = 'testing',
            'working_path' => $r['scope']['root'] = 'C:/not-a-synthetic-root',
            'release_hash' => $r['expected']['permit']['hashes']['release'] = str_repeat('0', 64),
            'revoked_state' => $this->clockState['healthy'] = false,
        };
        $this->rejects(fn () => $this->runRequest($r), 'reject');
        $this->assertSame(0, $this->accesses);
        $this->assertSame('', file_get_contents($this->registerRoot.'/claims.log'));
        $this->assertFileDoesNotExist($this->root.'/backup.sqlite');
    }

    public static function badSelections(): array
    {
        return array_map(fn ($x) => [$x], ['unfiltered', 'bypass', 'extra', 'synthetic_leak', 'modified', 'repinned_unreviewed']);
    }

    #[DataProvider('badSelections')]
    public function test_release_selection_rejects_before_target_access(string $case): void
    {
        $requested = ['activation/2026_11_07_200000_activate_b32_security_contract.php'];
        if ($case === 'unfiltered') {
            $requested = [];
        }
        if ($case === 'bypass') {
            $requested = [B32CRehearsal::artifact()];
        }
        if ($case === 'extra') {
            file_put_contents($this->releaseRoot.'/extra.php', '<?php');
        }
        if ($case === 'synthetic_leak') {
            file_put_contents($this->releaseRoot.'/activation/2026_11_06_100000_create_synthetic_verification_contract.php', '<?php');
        }
        if (in_array($case, ['modified', 'repinned_unreviewed'], true)) {
            file_put_contents($this->releaseRoot.'/activation/2026_11_07_200000_activate_b32_security_contract.php', '<?php // tampered');
        }
        if ($case === 'repinned_unreviewed') {
            $this->releaseManifest();
        }
        $this->rejects(fn () => $this->runRequest($this->request(), null, $requested), $case === 'repinned_unreviewed' ? 'Unreviewed' : 'reject');
        $this->assertSame(0, $this->accesses);
        $this->assertSame('', file_get_contents($this->registerRoot.'/claims.log'));
    }

    public function test_schema_and_ledger_drift_quarantine_without_backup(): void
    {
        $db = new SQLite3($this->root.'/source.sqlite');
        $db->exec('CREATE INDEX unexpected ON migrations(batch)');
        $db->close();
        $this->rejects(fn () => $this->runRequest($this->request()), 'quarantine');
        $this->assertFileDoesNotExist($this->root.'/backup.sqlite');
        $db = new SQLite3($this->root.'/source.sqlite');
        $db->exec('DROP INDEX unexpected');
        $db->exec('DELETE FROM migrations WHERE id=9');
        $db->close();
        $this->rejects(fn () => $this->runRequest($this->request()), 'quarantine');
    }

    public function test_correct_final_state_backups_and_ddl_ledger_gap_quarantines(): void
    {
        B32CRehearsal::activate();
        unlink($this->root.'/source.sqlite');
        $this->exportFixture();
        $backup = $this->runRequest($this->request());
        $this->assertSame('completed', $backup['receipt']['state']);
        $db = new SQLite3($this->root.'/source.sqlite');
        $db->exec("DELETE FROM migrations WHERE migration='2026_11_07_200000_activate_b32_security_contract'");
        $db->close();
        $this->rejects(fn () => $this->runRequest($this->request('working_migrate')), 'schema_without_ledger');
        $db = new SQLite3($this->root.'/source.sqlite', SQLITE3_OPEN_READONLY);
        $this->assertSame(9, $db->querySingle('SELECT count(*) FROM migrations'));
        $db->close();
    }

    public function test_ledger_without_schema_and_corrupted_register_quarantine(): void
    {
        $db = new SQLite3($this->root.'/source.sqlite');
        $db->exec("INSERT INTO migrations(migration,batch) VALUES('2026_11_07_200000_activate_b32_security_contract',3)");
        $db->close();
        $this->rejects(fn () => $this->runRequest($this->request()), 'ledger_schema_mismatch');
        file_put_contents($this->registerRoot.'/claims.log', 'torn');
        $this->accesses = 0;
        $this->rejects(fn () => $this->runRequest($this->request()), 'corrupt');
        $this->assertSame(0, $this->accesses);
        unlink($this->registerRoot.'/claims.log');
        $this->rejects(fn () => $this->runRequest($this->request()), 'unavailable');
    }

    public static function interruptionCases(): array
    {
        $a = [];
        foreach (['backup', 'restore_rehearsal'] as $operation) {
            foreach (['destination_reserved', 'snapshot_copied', 'before_receipt', 'receipt_published'] as $phase) {
                $a[] = [$operation, $phase];
            }
        }

        return $a;
    }

    #[DataProvider('interruptionCases')]
    public function test_interrupted_actions_stay_consumed_and_destinations_cannot_be_overwritten(string $operation, string $phase): void
    {
        if ($operation === 'restore_rehearsal') {
            $this->runRequest($this->request());
        }
        $r = $this->request($operation);
        $this->rejects(fn () => $this->runRequest($r, static function ($point) use ($phase): void {
            if ($point === $phase) {
                throw new RuntimeException('synthetic interruption');
            }
        }), 'synthetic interruption');
        $this->assertFileExists($this->root.'/'.$r['scope']['destination']);
        $this->rejects(fn () => $this->runRequest($r), 'consumed');
        $this->rejects(fn () => $this->runRequest($this->request($operation)), 'never overwrite');
        if ($operation === 'backup' && $phase === 'receipt_published') {
            $this->rejects(fn () => $this->runRequest($this->request('restore_rehearsal')), 'receipt/content mismatch');
        }
    }

    public function test_existing_destinations_and_corrupt_backup_are_never_repaired(): void
    {
        file_put_contents($this->root.'/backup.sqlite', 'sentinel');
        $this->rejects(fn () => $this->runRequest($this->request()), 'never overwrite');
        $this->assertSame('sentinel', file_get_contents($this->root.'/backup.sqlite'));
        unlink($this->root.'/backup.sqlite');
        $this->runRequest($this->request());
        file_put_contents($this->root.'/restore.sqlite', 'restore-sentinel');
        $this->rejects(fn () => $this->runRequest($this->request('restore_rehearsal')), 'never overwrite');
        $this->assertSame('restore-sentinel', file_get_contents($this->root.'/restore.sqlite'));
        unlink($this->root.'/restore.sqlite');
        file_put_contents($this->root.'/backup.sqlite', 'corrupted');
        $this->rejects(fn () => $this->runRequest($this->request('restore_rehearsal')), 'header');
        $this->assertFileDoesNotExist($this->root.'/restore.sqlite');
    }

    public function test_backup_provenance_mismatch_and_source_change_are_denied(): void
    {
        $this->runRequest($this->request());
        $r = $this->request('restore_rehearsal');
        file_put_contents($this->root.'/backup.sqlite.receipt.json', '{}');
        $this->rejects(fn () => $this->runRequest($r), 'provenance');
        $r = $this->request();
        $db = new SQLite3($this->root.'/source.sqlite');
        $db->exec("UPDATE audit_logs SET metadata='changed synthetic row'");
        $db->close();
        $this->rejects(fn () => $this->runRequest($r), 'identity/hash mismatch');
    }

    public function test_locked_source_fails_without_backup_and_does_not_refund_permit(): void
    {
        $r = $this->request();
        $writer = new SQLite3($this->root.'/source.sqlite');
        $writer->exec('BEGIN EXCLUSIVE');
        try {
            $this->rejects(fn () => $this->runRequest($r), 'quarantine');
        } finally {
            $writer->exec('ROLLBACK');
            $writer->close();
        }
        $this->assertFileDoesNotExist($this->root.'/backup.sqlite');
        $this->rejects(fn () => $this->runRequest($r), 'consumed');
    }

    public function test_revocation_and_expiry_after_claim_hold_incomplete_run(): void
    {
        $r = $this->request();
        $this->rejects(fn () => $this->runRequest($r, function ($phase): void {
            if ($phase === 'receipt_published') {
                $this->clockState['utc_ms'] = 20000;
            }
        }), 'rejected');
        $this->clockState['utc_ms'] = 1000;
        $this->rejects(fn () => $this->runRequest($this->request('restore_rehearsal')), 'receipt/content mismatch');
    }

    public function test_actual_environment_substitution_and_midclaim_revocation_deny_before_access(): void
    {
        $r = $this->request();
        putenv('APP_ENV=production');
        try {
            $this->rejects(fn () => $this->runRequest($r), 'rejected');
        } finally {
            putenv('APP_ENV=testing');
        }
        $this->assertSame(0, $this->accesses);
        $id = json_decode($r['bytes'], true)['permit_id'];
        $checks = 0;
        $tool = new SyntheticRecoveryPackage($this->public, $this->registerRoot, $this->classifier, fn () => $this->clockState, static function () use (&$checks, $id): array {
            return ++$checks > 1 ? [$id] : [];
        });
        $this->rejects(fn () => $tool->run($r['bytes'], $r['signature'], $r['expected'], $r['scope'], $this->releaseRoot, $this->manifest, ['activation/2026_11_07_200000_activate_b32_security_contract.php'], function ($phase): void {
            if ($phase === 'before_access') {
                $this->accesses++;
            }
        }), 'rejected');
        $this->assertSame(0, $this->accesses);
        $this->rejects(fn () => $this->runRequest($r), 'consumed');
    }

    public function test_missing_source_and_signed_nonfixture_scope_never_create_database(): void
    {
        $r = $this->request();
        unlink($this->root.'/source.sqlite');
        $this->rejects(fn () => $this->runRequest($r), 'identity/hash mismatch');
        $this->assertFileDoesNotExist($this->root.'/source.sqlite');
        $scope = $r['scope'];
        $scope['root'] = 'C:/unapproved-path';
        $p = json_decode($r['bytes'], true);
        $p['approval_ref'] = ScopedRecoveryAuthorization::reference($scope);
        $r['bytes'] = json_encode($p, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $r['signature'] = bin2hex(sodium_crypto_sign_detached("xlipa-offline-permit-v1\0".$r['bytes'], $this->secret));
        $r['scope'] = $scope;
        $r['expected']['scope'] = $scope;
        $this->accesses = 0;
        $this->rejects(fn () => $this->runRequest($r), 'before access');
        $this->assertSame(0, $this->accesses);
    }

    public function test_foreign_key_corruption_and_sidecars_stop_without_backup(): void
    {
        file_put_contents($this->root.'/source.sqlite-wal', 'ambiguous fixture sidecar');
        $this->rejects(fn () => $this->runRequest($this->request()), 'sidecar');
        unlink($this->root.'/source.sqlite-wal');
        $db = new SQLite3($this->root.'/source.sqlite');
        $db->exec('PRAGMA foreign_keys=OFF');
        $db->exec('UPDATE merchant_applications SET merchant_id=\'missing-fixture-merchant\' WHERE id=(SELECT id FROM merchant_applications ORDER BY id LIMIT 1)');
        $db->close();
        $this->rejects(fn () => $this->runRequest($this->request()), 'quarantine');
        $this->assertFileDoesNotExist($this->root.'/backup.sqlite');
    }

    public function test_even_repinned_receipt_with_wrong_logical_content_is_not_restorable(): void
    {
        $this->runRequest($this->request());
        $path = $this->root.'/backup.sqlite.receipt.json';
        $receipt = json_decode(file_get_contents($path), true);
        $receipt['logical_digest'] = str_repeat('0', 64);
        file_put_contents($path, json_encode($receipt));
        $this->rejects(fn () => $this->runRequest($this->request('restore_rehearsal')), 'receipt/content mismatch');
        $this->assertFileDoesNotExist($this->root.'/restore.sqlite');
    }

    public function test_changed_origin_receipt_and_lost_result_evidence_cannot_rewrite_provenance(): void
    {
        $this->runRequest($this->request());
        $path = $this->root.'/backup.sqlite.receipt.json';
        $original = file_get_contents($path);
        $receipt = json_decode($original, true);
        $receipt['origin_file_hash'] = str_repeat('0', 64);
        file_put_contents($path, json_encode($receipt));
        $this->rejects(fn () => $this->runRequest($this->request('restore_rehearsal')), 'receipt/content mismatch');
        file_put_contents($path, $original);
        unlink($this->registerRoot.'/recovery-results.log');
        $this->accesses = 0;
        $this->rejects(fn () => $this->runRequest($this->request('restore_rehearsal')), 'unavailable');
        $this->assertSame(0, $this->accesses);
        $this->rejects(fn () => SyntheticRecoveryPackage::initializeFixtureResults($this->registerRoot), 'refused');
    }

    public static function killBoundaries(): array
    {
        return [['backup', 'destination_reserved'], ['backup', 'snapshot_copied'], ['backup', 'receipt_published'], ['restore_rehearsal', 'destination_reserved'], ['restore_rehearsal', 'snapshot_copied'], ['restore_rehearsal', 'receipt_published']];
    }

    #[DataProvider('killBoundaries')]
    public function test_process_kill_leaves_consumed_claim_and_no_automatic_recovery(string $operation, string $phase): void
    {
        if ($operation === 'restore_rehearsal') {
            $this->runRequest($this->request());
        }
        $r = $this->request($operation);
        $input = json_encode(['public' => bin2hex($this->public), 'register' => $this->registerRoot, 'request' => $r, 'release' => $this->releaseRoot, 'manifest' => $this->manifest, 'phase' => $phase], JSON_THROW_ON_ERROR);
        $process = new Process([PHP_BINARY, base_path('tests/Support/recovery-crash-worker.php')], base_path(), null, $input, 10);
        $process->start();
        try {
            $deadline = microtime(true) + 8;
            while (! str_contains($process->getOutput(), 'READY') && $process->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
        } finally {
            $process->stop(0);
        }
        $this->assertFalse($process->isRunning());
        $this->rejects(fn () => $this->runRequest($r), 'consumed');
        $this->rejects(fn () => $this->runRequest($this->request($operation)), 'never overwrite');
        if ($operation === 'backup' && $phase === 'receipt_published') {
            $this->rejects(fn () => $this->runRequest($this->request('restore_rehearsal')), 'receipt/content mismatch');
        }
    }
}
