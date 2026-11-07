<?php

namespace Tests\Feature;

use App\Actions\Merchants\CreateMerchant;
use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

class B32CActivationRehearsalTest extends TestCase
{
    private array $before;

    private array $objects;

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        foreach (['alpha', 'beta'] as $tenant) {
            $owner = User::factory()->create(['email' => $tenant.'-fixture@example.test']);
            $merchant = app(CreateMerchant::class)->execute($owner, 'Synthetic '.$tenant);
            $application = $merchant->applications()->sole();
            DB::table('merchant_applications')->where('id', $application->id)->update(['profile_snapshot' => Crypt::encryptString('{"synthetic":true}'), 'internal_review_notes' => Crypt::encryptString('Synthetic confidential fixture')]);
        }
        $actor = DB::table('users')->value('id');
        $merchant = DB::table('merchants')->value('id');
        $application = DB::table('merchant_applications')->where('merchant_id', $merchant)->value('id');
        $manifest = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true, 32, JSON_THROW_ON_ERROR);
        $original = ['merchant.created', 'membership.revoked', 'application.submitted', 'application.reviewed', 'platform_role.granted', 'platform_role.revoked', 'account.status_changed', 'merchant.status_changed'];
        foreach ($original as $event) {
            DB::table('audit_logs')->insert(['id' => strtolower((string) Str::ulid()), 'event_type' => $event, 'actor_user_id' => $actor, 'target_user_id' => null, 'merchant_id' => $merchant, 'application_id' => $application, 'request_id' => (string) Str::uuid(), 'metadata' => ' { "reason_code" : "synthetic_fixture" } ', 'occurred_at' => '2026-11-06 23:59:59.123']);
        }
        $this->assertSame($manifest['foundation_ledger'], array_map(fn (object $row): array => (array) $row, DB::select('SELECT migration,batch FROM migrations ORDER BY id')));
        $this->before = $this->oldRows();
        $this->objects = B32CRehearsal::schema();
    }

    private function oldRows(): array
    {
        $rows = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
            $rows[$table->name] = DB::select('SELECT * FROM "'.str_replace('"', '""', $table->name).'" ORDER BY rowid');
        }

        return $rows;
    }

    public function test_selected_migrator_preserves_old_rows_and_logs_one_real_batch(): void
    {
        B32CRehearsal::activate();
        foreach ($this->before as $table => $rows) {
            $actual = DB::select('SELECT * FROM "'.$table.'" ORDER BY rowid');
            if ($table === 'migrations') {
                $this->assertEquals($rows, array_slice($actual, 0, 9));
                $this->assertCount(10, $actual);
                $this->assertSame('2026_11_07_200000_activate_b32_security_contract', $actual[9]->migration);
                $this->assertSame(3, $actual[9]->batch);
            } else {
                $this->assertEquals($rows, $actual);
            }
        }
        $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
        foreach ($contract['tables'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame('ok', DB::selectOne('PRAGMA integrity_check')->integrity_check);
        B32CRehearsal::activate();
        $this->assertSame(10, DB::table('migrations')->count());
    }

    public function test_final_schema_equals_independently_run_accepted_migrations(): void
    {
        B32CRehearsal::activate();
        $activated = B32CRehearsal::schema();
        $order = DB::select("SELECT name FROM sqlite_master WHERE type='trigger' ORDER BY rowid");
        DB::purge('sqlite');
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--no-interaction' => true]));
        $this->assertEquals(B32CRehearsal::schema(), $activated);
        $this->assertEquals($order, DB::select("SELECT name FROM sqlite_master WHERE type='trigger' ORDER BY rowid"));
    }

    public function test_recorded_activation_with_missing_schema_is_quarantined_before_migrator_skip(): void
    {
        B32CRehearsal::activate();
        DB::statement('DROP TABLE verification_limit_state');
        try {
            B32CRehearsal::activate();
            $this->fail('Recorded-but-missing schema was silently skipped.');
        } catch (LogicException $error) {
            $this->assertSame('B32C quarantine: recorded activation schema mismatch.', $error->getMessage());
        }
        $this->assertSame(10, DB::table('migrations')->count());
    }

    public function test_all_sixteen_audit_events_and_raw_append_only_are_enforced(): void
    {
        B32CRehearsal::activate();
        foreach (AuditEvent::cases() as $event) {
            DB::table('audit_logs')->insert(['id' => (string) Str::ulid(), 'event_type' => $event->value, 'request_id' => (string) Str::uuid(), 'metadata' => '{}', 'occurred_at' => '2026-11-07 00:00:00.001']);
        }
        foreach (['UPDATE audit_logs SET metadata=metadata', 'DELETE FROM audit_logs'] as $sql) {
            try {
                DB::statement($sql);
                $this->fail('Audit mutation unexpectedly succeeded.');
            } catch (QueryException $error) {
                $this->assertStringContainsString('Audit history is append-only', $error->getMessage());
            }
        }
        try {
            DB::table('audit_logs')->insert(['id' => (string) Str::ulid(), 'event_type' => 'invented.event', 'request_id' => (string) Str::uuid(), 'occurred_at' => '2026-11-07']);
            $this->fail('Unknown event accepted.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('CHECK constraint failed', $error->getMessage());
        }
    }

    public function test_default_discovery_excludes_activation_and_unselected_call_refuses_before_db_access(): void
    {
        $files = app('migrator')->getMigrationFiles([database_path('migrations')]);
        $this->assertArrayNotHasKey('2026_11_07_200000_activate_b32_security_contract', $files);
        foreach (['100000_create_synthetic_verification_contract', '100001_extend_synthetic_audit_contract', '100002_add_synthetic_pending_audit_obligations'] as $name) {
            $this->assertArrayHasKey('2026_11_06_'.$name, $files);
        }
        $original = DB::getFacadeRoot();
        DB::swap(\Mockery::mock(\stdClass::class));
        try {
            $migration = require B32CRehearsal::artifact();
            try {
                $migration->up();
                $this->fail('Unselected artifact ran.');
            } catch (LogicException $error) {
                $this->assertStringContainsString('explicit isolated rehearsal selection', $error->getMessage());
            }
            config(['b32c.selected' => realpath(B32CRehearsal::artifact()), 'database.connections.sqlite.database' => database_path('database.sqlite')]);
            try {
                $migration->up();
                $this->fail('Working path accepted.');
            } catch (LogicException $error) {
                $this->assertStringContainsString('working activation is disabled', $error->getMessage());
            }
        } finally {
            DB::swap($original);
            config(['database.connections.sqlite.database' => ':memory:']);
        }
    }

    public static function failurePhases(): array
    {
        return array_map(fn (string $phase): array => [$phase], ['before_audit', 'after_audit_copy', 'after_audit_drop', 'after_audit_rename', 'after_audit_indexes', 'table:merchant_contact_versions', 'table:verification_limit_state', 'after_security_triggers', 'before_commit']);
    }

    #[DataProvider('failurePhases')]
    public function test_transaction_failures_restore_schema_rows_and_ledger(string $phase): void
    {
        config(['b32c.rehearsal_hook' => static function (string $actual) use ($phase): void {
            if ($actual === $phase) {
                throw new RuntimeException('injected '.$phase);
            }
        }]);
        try {
            B32CRehearsal::activate();
            $this->fail('Fault was not reached.');
        } catch (RuntimeException $error) {
            $this->assertSame('injected '.$phase, $error->getMessage());
        }
        $this->assertEquals($this->objects, B32CRehearsal::schema());
        $this->assertEquals($this->before, $this->oldRows());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        config(['b32c.rehearsal_hook' => null]);
        B32CRehearsal::activate();
        $this->assertSame(10, DB::table('migrations')->count());
    }

    public function test_actual_late_ddl_failure_rolls_back_without_ledger_entry(): void
    {
        config(['b32c.rehearsal_hook' => static function (string $phase): void {
            if ($phase === 'after_security_triggers') {
                DB::statement('CREATE TABLE audit_logs (id INTEGER)');
            }
        }]);
        try {
            B32CRehearsal::activate();
            $this->fail('DDL collision accepted.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('table audit_logs already exists', $error->getMessage());
        }
        $this->assertEquals($this->objects, B32CRehearsal::schema());
        $this->assertEquals($this->before, $this->oldRows());
    }

    public function test_schema_and_ledger_drift_are_quarantined_without_adoption(): void
    {
        DB::statement('CREATE INDEX unexpected_fixture_index ON users(account_status)');
        try {
            B32CRehearsal::activate();
            $this->fail('Drift adopted.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('fingerprint mismatch', $error->getMessage());
        }
        DB::statement('DROP INDEX unexpected_fixture_index');
        DB::table('migrations')->where('migration', '2026_11_06_000006_create_audit_logs')->delete();
        try {
            B32CRehearsal::activate();
            $this->fail('Broken fixture ledger adopted.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('ledger mismatch', $error->getMessage());
        }
        $this->assertFalse(Schema::hasTable('merchant_identity_records'));
    }

    public function test_working_down_is_disabled_without_deleting_history(): void
    {
        B32CRehearsal::activate();
        $before = $this->oldRows();
        $migration = require B32CRehearsal::artifact();
        try {
            $migration->down();
            $this->fail('Destructive down ran.');
        } catch (LogicException $error) {
            $this->assertSame('B32C downgrade disabled: reviewed forward recovery required.', $error->getMessage());
        }
        $this->assertEquals($before, $this->oldRows());
    }

    public function test_process_crash_after_ddl_commit_restarts_in_quarantine_without_fake_ledger(): void
    {
        $path = sys_get_temp_dir().'/xlipa-b32-synthetic-'.bin2hex(random_bytes(8)).'.sqlite';
        $copy = new PDO('sqlite:'.$path);
        foreach (DB::select("SELECT name,sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $table) {
            $copy->exec($table->sql);
            foreach (DB::select('SELECT * FROM "'.$table->name.'" ORDER BY rowid') as $row) {
                $values = (array) $row;
                $statement = $copy->prepare('INSERT INTO "'.$table->name.'" ('.implode(',', array_keys($values)).') VALUES ('.implode(',', array_fill(0, count($values), '?')).')');
                $statement->execute(array_values($values));
            }
        }
        foreach (DB::select("SELECT sql FROM sqlite_master WHERE type IN ('index','trigger') AND sql IS NOT NULL") as $object) {
            $copy->exec($object->sql);
        }
        $statement = $copy = null;
        $process = null;
        try {
            $process = proc_open([PHP_BINARY, base_path('tests/Support/b32c-crash-worker.php'), $path], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $deadline = microtime(true) + 15;
            while (! is_file($path.'.committed')) {
                if (microtime(true) > $deadline) {
                    throw new LogicException('B32C crash barrier not reached.');
                }
                usleep(10000);
            }
            proc_terminate($process);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $process = null;
            $copy = new PDO('sqlite:'.$path);
            $this->assertSame(21, (int) $copy->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn());
            $this->assertSame(9, (int) $copy->query('SELECT count(*) FROM migrations')->fetchColumn());
            $this->assertSame(0, (int) $copy->query("SELECT count(*) FROM migrations WHERE migration='2026_11_07_200000_activate_b32_security_contract'")->fetchColumn());
            $this->assertSame('ok', $copy->query('PRAGMA integrity_check')->fetchColumn());
            $this->assertSame([], $copy->query('PRAGMA foreign_key_check')->fetchAll());
            $copy = null;
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $path]);
            try {
                B32CRehearsal::activate();
                $this->fail('Restart silently adopted the ledger gap.');
            } catch (LogicException $error) {
                $this->assertStringContainsString('quarantine: unexpected or partially activated schema', $error->getMessage());
            }
            $this->assertSame(9, DB::table('migrations')->count());
            $this->assertCount(21, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            $copy = null;
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => ':memory:']);
            foreach (glob($path.'*') as $file) {
                if (is_file($file) && realpath(dirname($file)) === realpath(sys_get_temp_dir())) {
                    unlink($file);
                }
            }
        }
    }
}
