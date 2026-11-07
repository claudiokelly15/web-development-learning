<?php

namespace Tests\Feature;

use App\Actions\Merchants\CreateMerchant;
use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;
use Tests\Support\SyntheticAuditReconciler;
use Tests\Support\SyntheticCipher;
use Tests\Support\SyntheticCoordinator;
use Tests\Support\SyntheticKeys;
use Tests\Support\SyntheticVerification;
use Tests\TestCase;
use Throwable;

class B32SyntheticContractTest extends TestCase
{
    private SyntheticVerification $service;

    private SyntheticKeys $keys;

    private PDO $pdo;

    private array $binding;

    private string $application;

    private const START = 1793923200000;

    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->pdo = DB::connection()->getPdo();
        $this->keys = new SyntheticKeys;
        $this->service = new SyntheticVerification($this->pdo, $this->keys);
        $owner = User::factory()->create(['email' => 'synthetic-owner@example.test']);
        $merchant = app(CreateMerchant::class)->execute($owner, 'Synthetic fixture business');
        $this->application = $merchant->applications()->sole()->id;
        $this->binding = ['merchant' => $merchant->id, 'actor' => $owner->id, 'contact' => (string) Str::ulid(), 'purpose' => 'application_phone'];
        $this->contact($this->binding['contact']);
        $this->service->insert('merchant_onboarding_contexts', ['merchant_id' => $merchant->id, 'current_contact_id' => $this->binding['contact'], 'contact_actor_user_id' => $owner->id, 'contact_purpose' => 'application_phone', 'updated_at' => self::START]);
    }

    private function contact(string $id, int $version = 1, string $target = 'SYNTH-PHONE-A'): void
    {
        $binding = ['namespace' => 'synthetic', 'merchant' => $this->binding['merchant'], 'record' => $id, 'purpose' => 'application_phone', 'version' => $version, 'actor' => $this->binding['actor']];
        $this->service->insert('merchant_contact_versions', ['id' => $id, 'merchant_id' => $binding['merchant'], 'actor_user_id' => $binding['actor'], 'purpose' => 'application_phone', 'version' => $version,
            'phone_ciphertext' => (new SyntheticCipher($this->keys))->encrypt('phone', $target, $binding, 'phone.v1'), 'phone_key_ref' => 'phone.v1', 'envelope_version' => 1,
            'target_fingerprint' => hash_hmac('sha256', SyntheticCipher::lp('xlipa-contact-fp-v1', $target), $this->keys->resolve('contact', 'contact.v1')), 'fingerprint_key_ref' => 'contact.v1', 'created_at' => self::START]);
    }

    private function rejected(callable $operation, string $reason, string $class = PDOException::class): void
    {
        try {
            $operation();
        } catch (Throwable $error) {
            $this->assertInstanceOf($class, $error);
            $this->assertStringContainsString($reason, $error->getMessage());
            if ($error instanceof PDOException && $class === PDOException::class) {
                $this->assertSame('23000', $error->errorInfo[0]);
                $this->assertSame(19, (int) $error->errorInfo[1]);
            }

            return;
        }
        $this->fail('Invalid synthetic operation was accepted.');
    }

    private function active(int $now = self::START, string $outcome = 'accepted'): string
    {
        $id = $this->service->issue($this->binding, $now, (string) Str::uuid());
        $this->assertNotNull($id);
        $this->service->deliver($this->binding, $id, $now, $outcome);

        return $id;
    }

    private function proof(int $now = self::START): string
    {
        $id = $this->active($now);
        $proof = $this->service->verify($this->binding, $id, '000123', $now + 1);
        $this->assertNotNull($proof);

        return $proof;
    }

    private function pendingAudit(int $now = self::START): array
    {
        $id = $this->active($now);
        for ($i = 0; $i < 4; $i++) {
            $this->service->verify($this->binding, $id, '999999', $now + 1);
        }
        DB::unprepared("CREATE TRIGGER option_a_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'option A audit outage'); END");
        $this->assertNull($this->service->verify($this->binding, $id, '999999', $now + 2));
        $row = (array) DB::table('verification_limit_state')->first();
        $this->assertNotNull($row['pending_audit_event_id']);

        return $row;
    }

    private function expectedPendingAudit(array $row): array
    {
        return ['id' => $row['pending_audit_event_id'], 'event_type' => 'otp.changed', 'actor_user_id' => $row['actor_user_id'], 'merchant_id' => $row['merchant_id'], 'target_user_id' => null, 'application_id' => null,
            'request_id' => $row['pending_audit_request_id'], 'metadata' => json_encode(['reason_code' => $row['pending_audit_reason_code']], JSON_THROW_ON_ERROR), 'occurred_at' => $row['pending_audit_occurred_at']];
    }

    public function test_option_a_reconciliation_preserves_exact_payload_and_is_idempotent(): void
    {
        $row = $this->pendingAudit();
        $this->rejected(fn () => (new SyntheticAuditReconciler($this->pdo))->reconcile($row, $row['pending_audit_event_id']), 'option A audit outage');
        DB::statement('DROP TRIGGER option_a_audit_outage');
        $reconciler = new SyntheticAuditReconciler($this->pdo);
        $this->assertTrue($reconciler->reconcile($row, $row['pending_audit_event_id']));
        $actual = (array) DB::table('audit_logs')->where('id', $row['pending_audit_event_id'])->first();
        foreach ($this->expectedPendingAudit($row) as $field => $value) {
            $this->assertSame($value, $actual[$field]);
        }
        $this->assertSame($row['pending_audit_occurred_at'], AuditLog::findOrFail($row['pending_audit_event_id'])->occurred_at->format('Y-m-d H:i:s.v'));
        $this->assertFalse($reconciler->reconcile($row, $row['pending_audit_event_id']));
        $this->assertSame(1, DB::table('audit_logs')->where('id', $row['pending_audit_event_id'])->count());
        $this->assertNull(DB::table('verification_limit_state')->value('pending_audit_event_id'));
        foreach (range(1, 10) as $i) {
            $field = sprintf('failure_%02d_at', $i);
            $this->assertSame($row[$field], DB::table('verification_limit_state')->value($field));
        }
    }

    public function test_option_a_pending_gates_resend_and_proof_consumption_without_ttl(): void
    {
        $proof = $this->proof();
        $row = $this->pendingAudit(self::START + 60000);
        $before = DB::table('verification_limit_state')->get()->toJson();
        $comparisons = $this->service->codeComparisons();
        $this->assertFalse($this->service->consume($this->binding, $proof, $this->application, self::START + 60003));
        $this->assertNull($this->service->issue($this->binding, self::START + 3600000, (string) Str::uuid()));
        $this->assertNull($this->service->verify($this->binding, $row['pending_audit_challenge_id'], '000123', self::START + 3600000));
        $this->assertSame($comparisons, $this->service->codeComparisons());
        $this->assertSame($before, DB::table('verification_limit_state')->get()->toJson());
        $this->assertSame('available', DB::table('contact_verification_proofs')->where('id', $proof)->value('state'));
    }

    public function test_option_a_tenth_failure_can_gate_a_still_active_challenge(): void
    {
        for ($challenge = 0; $challenge < 3; $challenge++) {
            $now = self::START + $challenge * 60000;
            $id = $this->active($now);
            if ($challenge === 2) {
                DB::unprepared("CREATE TRIGGER option_a_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'option A audit outage'); END");
            }
            for ($i = 0; $i < ($challenge === 2 ? 4 : 3); $i++) {
                $this->service->verify($this->binding, $id, '999999', $now + 1);
            }
        }
        $this->assertSame('active', DB::table('phone_otp_challenges')->where('id', $id)->value('state'));
        $this->assertSame(4, DB::table('phone_otp_challenges')->where('id', $id)->value('failed_attempts'));
        $this->assertNotNull(DB::table('verification_limit_state')->value('failure_10_at'));
        $before = $this->service->codeComparisons();
        $this->assertNull($this->service->verify($this->binding, $id, '000123', self::START + 120002));
        $this->assertSame($before, $this->service->codeComparisons());
    }

    public function test_option_a_raw_pending_guards_and_append_only_audit(): void
    {
        $row = $this->pendingAudit();
        $where = DB::table('verification_limit_state')->where('scope_key', $row['scope_key']);
        $this->rejected(fn () => (clone $where)->update(['pending_audit_event_id' => (string) Str::ulid(), 'state_version' => $row['state_version'] + 1]), 'R06 pending obligation immutable');
        $this->rejected(fn () => (clone $where)->update(['failure_01_at' => null, 'state_version' => $row['state_version'] + 1]), 'R06 pending obligation immutable');
        $clear = array_fill_keys(['pending_audit_event_id', 'pending_audit_challenge_id', 'pending_audit_reason_code', 'pending_audit_request_id', 'pending_audit_occurred_at'], null);
        $this->rejected(fn () => (clone $where)->update([...$clear, 'state_version' => $row['state_version'] + 1]), 'R06 exact audit required');
        (clone $where)->update(['last_observed_at' => self::START + 100, 'updated_at' => self::START + 100, 'state_version' => $row['state_version'] + 1]);
        $this->assertSame($row['pending_audit_occurred_at'], (clone $where)->value('pending_audit_occurred_at'));
        DB::statement('DROP TRIGGER option_a_audit_outage');
        (new SyntheticAuditReconciler($this->pdo))->reconcile($row, $row['pending_audit_event_id']);
        $this->rejected(fn () => DB::table('audit_logs')->where('id', $row['pending_audit_event_id'])->delete(), 'Audit history is append-only');
        $this->rejected(fn () => DB::table('audit_logs')->where('id', $row['pending_audit_event_id'])->update(['metadata' => '{}']), 'Audit history is append-only');
    }

    public function test_option_a_exact_existing_event_is_matched_without_overwrite(): void
    {
        $row = $this->pendingAudit();
        DB::statement('DROP TRIGGER option_a_audit_outage');
        $event = $this->expectedPendingAudit($row);
        $this->service->insert('audit_logs', $event);
        $this->assertTrue((new SyntheticAuditReconciler($this->pdo))->reconcile($row, $row['pending_audit_event_id']));
        $this->assertSame(1, DB::table('audit_logs')->where('id', $event['id'])->count());
    }

    public function test_option_a_partial_tuples_and_illegal_initial_pending_are_rejected(): void
    {
        $row = $this->pendingAudit();
        $fields = ['pending_audit_event_id', 'pending_audit_challenge_id', 'pending_audit_reason_code', 'pending_audit_request_id', 'pending_audit_occurred_at'];
        for ($mask = 1; $mask < 31; $mask++) {
            $data = ['state_version' => $row['state_version'] + 1];
            foreach ($fields as $bit => $field) {
                $data[$field] = ($mask & (1 << $bit)) ? $row[$field] : null;
            }
            $this->rejected(fn () => DB::table('verification_limit_state')->where('scope_key', $row['scope_key'])->update($data), $data['pending_audit_event_id'] === null ? 'R06 exact audit required' : 'R06 pending obligation immutable');
        }
        $copy = $row;
        $copy['scope_key'] = str_repeat('a', 64);
        $this->rejected(fn () => $this->service->insert('verification_limit_state', $copy), 'R06 obligation requires counted update');
        $this->assertSame($row, (array) DB::table('verification_limit_state')->first());
    }

    private function inSyntheticSnapshot(callable $test): void
    {
        $path = sys_get_temp_dir().'/xlipa-b32-synthetic-'.bin2hex(random_bytes(8)).'.sqlite';
        $pdo = $this->syntheticSnapshot($path);
        try {
            $test($pdo);
        } finally {
            $pdo = null;
            unlink($path);
        }
    }

    public function test_option_a_every_payload_mismatch_holds_the_original_obligation(): void
    {
        $row = $this->pendingAudit();
        DB::statement('DROP TRIGGER option_a_audit_outage');
        foreach (['event_type' => 'otp.requested', 'actor_user_id' => null, 'merchant_id' => null, 'target_user_id' => $row['actor_user_id'], 'application_id' => $this->application, 'request_id' => (string) Str::uuid(), 'metadata' => '{}', 'occurred_at' => '2026-11-06 00:00:00.003'] as $field => $value) {
            $this->inSyntheticSnapshot(function (PDO $pdo) use ($row, $field, $value): void {
                $coordinator = new SyntheticCoordinator($pdo);
                $event = array_replace($this->expectedPendingAudit($row), [$field => $value]);
                $statement = $pdo->prepare('INSERT INTO audit_logs ('.implode(',', array_keys($event)).') VALUES ('.implode(',', array_fill(0, count($event), '?')).')');
                $statement->execute(array_values($event));
                $this->rejected(fn () => (new SyntheticAuditReconciler($pdo))->reconcile($row, $row['pending_audit_event_id']), 'R06 audit payload conflict.', LogicException::class);
                $this->assertSame($row['pending_audit_event_id'], $pdo->query('SELECT pending_audit_event_id FROM verification_limit_state')->fetchColumn());
                $this->assertSame(1, (int) $pdo->query("SELECT count(*) FROM audit_logs WHERE id='".$row['pending_audit_event_id']."'")->fetchColumn());
            });
        }
        $this->assertFalse((new SyntheticAuditReconciler($this->pdo))->reconcile($row, strtolower($row['pending_audit_event_id'])));
    }

    public function test_option_a_discharge_failure_rolls_back_only_the_audit_transaction(): void
    {
        $row = $this->pendingAudit();
        DB::statement('DROP TRIGGER option_a_audit_outage');
        $reconciler = new SyntheticAuditReconciler($this->pdo, static function (): void {
            throw new LogicException('synthetic after-insert failure');
        });
        $this->rejected(fn () => $reconciler->reconcile($row, $row['pending_audit_event_id']), 'synthetic after-insert failure', LogicException::class);
        $this->assertSame(0, DB::table('audit_logs')->where('id', $row['pending_audit_event_id'])->count());
        $this->assertSame($row, (array) DB::table('verification_limit_state')->first());
        $this->assertSame(5, DB::table('phone_otp_challenges')->value('failed_attempts'));
        $this->assertTrue((new SyntheticAuditReconciler($this->pdo))->reconcile($row, $row['pending_audit_event_id']));
    }

    public function test_option_a_timestamp_sql_php_equivalence_and_bounds(): void
    {
        foreach ([0, 999, 1000, 1709164799999, 1709164800000, 1793923200123, 253402300799999] as $time) {
            $sql = DB::selectOne("SELECT strftime('%Y-%m-%d %H:%M:%S',CAST(?/1000 AS INTEGER),'unixepoch') || printf('.%03d',? % 1000) AS value", [$time, $time])->value;
            $this->assertSame(SyntheticAuditReconciler::occurredAt($time), $sql);
        }
        foreach ([-1, 253402300800000] as $time) {
            $this->rejected(fn () => SyntheticAuditReconciler::occurredAt($time), 'R06 clock outside representable UTC range.', LogicException::class);
        }
    }

    public function test_option_a_coordinator_never_retries_an_evaluated_operation(): void
    {
        $calls = 0;
        $error = new PDOException('synthetic post-evaluation busy');
        $error->errorInfo = ['HY000', 5, 'synthetic busy'];
        try {
            (new SyntheticCoordinator($this->pdo))->run(function () use (&$calls, $error): void {
                $calls++;
                throw $error;
            });
            $this->fail('Expected the original post-evaluation error.');
        } catch (PDOException $caught) {
            $this->assertSame($error, $caught);
        }
        $this->assertSame(1, $calls);
    }

    public function test_option_a_restored_snapshot_uses_explicit_fixture_hold_and_pending_survives_restart(): void
    {
        $row = $this->pendingAudit();
        $this->inSyntheticSnapshot(function (PDO $pdo) use ($row): void {
            $restored = new SyntheticVerification($pdo, new SyntheticKeys);
            $this->assertNull($restored->verify($this->binding, $row['pending_audit_challenge_id'], '000123', self::START + 3600000));
            $this->assertSame(0, $restored->codeComparisons());
            $hold = new SyntheticVerification($pdo, new SyntheticKeys, null, false);
            $this->rejected(fn () => $hold->issue($this->binding, self::START + 3600000, (string) Str::uuid()), 'Synthetic storage or restore hold.', LogicException::class);
            $this->assertSame($row['pending_audit_event_id'], $pdo->query('SELECT pending_audit_event_id FROM verification_limit_state')->fetchColumn());
        });
    }

    public function test_option_a_migration_preserves_scope_rows_and_refuses_pending_downgrade(): void
    {
        $id = $this->active();
        $this->service->verify($this->binding, $id, '999999', self::START + 1);
        $migration = require database_path('migrations/2026_11_06_100002_add_synthetic_pending_audit_obligations.php');
        $old = (array) DB::table('verification_limit_state')->first();
        $migration->down();
        $without = (array) DB::table('verification_limit_state')->first();
        foreach ($without as $field => $value) {
            $this->assertSame($old[$field], $value);
        }
        $migration->up();
        $this->assertSame($old, (array) DB::table('verification_limit_state')->first());
        for ($i = 0; $i < 4; $i++) {
            $this->service->verify($this->binding, $id, '999999', self::START + 2);
        }
        // Healthy reconciliation clears pending; an audit outage holds the next event.
        $this->assertNull(DB::table('verification_limit_state')->value('pending_audit_event_id'));
        $next = $this->active(self::START + 60000);
        DB::unprepared("CREATE TRIGGER option_a_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'option A audit outage'); END");
        for ($i = 0; $i < 5; $i++) {
            $this->service->verify($this->binding, $next, '999999', self::START + 60001);
        }
        $before = DB::table('verification_limit_state')->get()->toJson();
        $this->rejected(fn () => $migration->down(), 'R06 pending obligations prevent downgrade.', LogicException::class);
        $this->assertSame($before, DB::table('verification_limit_state')->get()->toJson());
    }

    public function test_option_a_two_reconcilers_materialize_one_event(): void
    {
        $row = $this->pendingAudit();
        DB::unprepared('DROP TRIGGER option_a_audit_outage');
        $payload = ['operation' => 'reconcile', 'scope' => $row, 'event' => $row['pending_audit_event_id']];
        [$results, , , $scope, $auditCount] = $this->concurrent([$payload, $payload]);
        $this->assertCount(1, array_filter($results));
        $this->assertNull($scope['pending_audit_event_id']);
        $this->assertSame(DB::table('audit_logs')->count() + 1, (int) $auditCount);
    }

    public function test_option_a_concurrent_tenth_and_eleventh_guesses_preserve_ten_slots(): void
    {
        $first = $this->active();
        for ($i = 0; $i < 5; $i++) {
            $this->service->verify($this->binding, $first, '999999', self::START + 1);
        }
        $second = $this->active(self::START + 60000);
        for ($i = 0; $i < 4; $i++) {
            $this->service->verify($this->binding, $second, '999999', self::START + 60001);
        }
        DB::unprepared("CREATE TRIGGER option_a_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'option A audit outage'); END");
        $payload = ['operation' => 'guess', 'challenge' => $second, 'code' => '999999', 'now' => self::START + 60002];
        [$results,$attempts,$proofs,$row] = $this->concurrent([$payload, $payload]);
        $this->assertSame([null, null], $results);
        $this->assertSame(5, (int) $attempts);
        $this->assertSame(0, (int) $proofs);
        $this->assertSame('aggregate_exhausted', $row['pending_audit_reason_code']);
        for ($i = 1; $i <= 10; $i++) {
            $this->assertNotNull($row[sprintf('failure_%02d_at', $i)]);
        }
        $this->assertSame(self::START + 60002, (int) $row['failure_10_at']);
    }

    public function test_option_a_pending_is_tenant_bound_and_does_not_block_another_merchant(): void
    {
        $pending = $this->pendingAudit();
        DB::unprepared('DROP TRIGGER option_a_audit_outage');
        $owner = User::factory()->create(['email' => 'other-option-a@example.test']);
        $merchant = app(CreateMerchant::class)->execute($owner, 'Other synthetic tenant');
        $this->binding = ['merchant' => $merchant->id, 'actor' => $owner->id, 'contact' => (string) Str::ulid(), 'purpose' => 'application_phone'];
        $this->contact($this->binding['contact']);
        $this->service->insert('merchant_onboarding_contexts', ['merchant_id' => $merchant->id, 'current_contact_id' => $this->binding['contact'], 'contact_actor_user_id' => $owner->id, 'contact_purpose' => 'application_phone', 'updated_at' => self::START]);
        $id = $this->active();
        $other = (array) DB::table('verification_limit_state')->where('merchant_id', $merchant->id)->first();
        $this->assertFalse((new SyntheticAuditReconciler($this->pdo))->reconcile($other, $pending['pending_audit_event_id']));
        $mutations = array_intersect_key($pending, array_flip(['pending_audit_event_id', 'pending_audit_challenge_id', 'pending_audit_request_id', 'pending_audit_occurred_at']));
        $mutations += ['pending_audit_reason_code' => 'aggregate_exhausted', 'last_observed_at' => self::START + 2, 'updated_at' => self::START + 2, 'state_version' => 2];
        for ($i = 1; $i <= 10; $i++) {
            $mutations[sprintf('failure_%02d_at', $i)] = self::START + 2;
        }
        $this->rejected(fn () => DB::table('verification_limit_state')->where('merchant_id', $merchant->id)->update($mutations), 'R06 pending lineage or threshold mismatch', QueryException::class);
        $this->assertNotNull($this->service->verify($this->binding, $id, '000123', self::START + 3));
        $this->assertSame($pending, (array) DB::table('verification_limit_state')->where('merchant_id', $pending['merchant_id'])->first());
    }

    public function test_option_a_late_migration_failure_rolls_back_populated_rebuild(): void
    {
        $id = $this->active();
        $this->service->verify($this->binding, $id, '999999', self::START + 1);
        $migration = require database_path('migrations/2026_11_06_100002_add_synthetic_pending_audit_obligations.php');
        $migration->down();
        DB::statement('CREATE INDEX r06_pending_scan ON audit_logs(occurred_at)');
        $schema = DB::select("SELECT type,name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name");
        $rows = DB::table('verification_limit_state')->get()->toJson();
        $this->rejected(fn () => $migration->up(), 'index r06_pending_scan already exists', QueryException::class);
        $this->assertEquals($schema, DB::select("SELECT type,name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name"));
        $this->assertSame($rows, DB::table('verification_limit_state')->get()->toJson());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        DB::statement('DROP INDEX r06_pending_scan');
        $migration->up();
        $this->assertTrue(Schema::hasColumn('verification_limit_state', 'pending_audit_event_id'));
    }

    public function test_option_a_busy_writer_denies_before_comparison_and_storage_failure_rolls_back(): void
    {
        $id = $this->active();
        $this->inSyntheticSnapshot(function (PDO $pdo) use ($id): void {
            $path = $pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file'];
            $blocker = new PDO('sqlite:'.$path);
            $blocker->exec('BEGIN IMMEDIATE');
            $service = new SyntheticVerification($pdo, new SyntheticKeys);
            try {
                $service->verify($this->binding, $id, '000123', self::START + 1);
                $this->fail('Expected writer acquisition failure.');
            } catch (PDOException $error) {
                $this->assertSame(5, (int) $error->errorInfo[1]);
            } finally {
                $blocker->exec('ROLLBACK');
            }
            $this->assertSame(0, $service->codeComparisons());
        });
        DB::unprepared("CREATE TRIGGER option_a_accounting_outage BEFORE UPDATE ON verification_limit_state BEGIN SELECT RAISE(ABORT,'accounting unavailable'); END");
        $this->rejected(fn () => $this->service->verify($this->binding, $id, '999999', self::START + 1), 'accounting unavailable');
        $this->assertSame(0, DB::table('phone_otp_challenges')->where('id', $id)->value('failed_attempts'));
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
        $this->assertSame(1, $this->service->codeComparisons());
    }

    public function test_option_a_process_crashes_preserve_atomic_count_and_reconciliation(): void
    {
        $id = $this->active();
        for ($i = 0; $i < 4; $i++) {
            $this->service->verify($this->binding, $id, '999999', self::START + 1);
        }
        foreach (['crash_before_count', 'crash_after_count', 'crash_after_insert', 'crash_after_reconcile'] as $operation) {
            if (str_contains($operation, 'insert') || $operation === 'crash_after_reconcile') {
                if (DB::table('verification_limit_state')->value('pending_audit_event_id') === null) {
                    DB::unprepared("CREATE TRIGGER option_a_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'option A audit outage'); END");
                    $this->service->verify($this->binding, $id, '999999', self::START + 2);
                    DB::unprepared('DROP TRIGGER option_a_audit_outage');
                }
            }
            $row = (array) DB::table('verification_limit_state')->first();
            $path = sys_get_temp_dir().'/xlipa-b32-synthetic-'.bin2hex(random_bytes(8)).'.sqlite';
            $pdo = $this->syntheticSnapshot($path);
            $process = null;
            try {
                $payload = ['operation' => $operation, 'path' => $path, 'worker' => 0, 'binding' => $this->binding, 'challenge' => $id, 'now' => self::START + 2, 'scope' => $row, 'event' => $row['pending_audit_event_id']];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/concurrent-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                file_put_contents($path.'.go', 'go');
                $deadline = microtime(true) + 15;
                while (! is_file($path.'.phase')) {
                    if (microtime(true) > $deadline) {
                        throw new LogicException('Crash fixture barrier timeout.');
                    }
                    usleep(10000);
                }
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $process = null;
                $pdo = null;
                $pdo = new PDO('sqlite:'.$path);
                new SyntheticCoordinator($pdo);
                $actual = $pdo->query('SELECT * FROM verification_limit_state')->fetch(PDO::FETCH_ASSOC);
                $attempts = (int) $pdo->query('SELECT failed_attempts FROM phone_otp_challenges')->fetchColumn();
                $this->assertSame($operation === 'crash_before_count' ? 4 : 5, $attempts);
                if ($operation === 'crash_before_count' || $operation === 'crash_after_reconcile') {
                    $this->assertNull($actual['pending_audit_event_id']);
                } else {
                    $this->assertNotNull($actual['pending_audit_event_id']);
                    $check = $pdo->prepare('SELECT count(*) FROM audit_logs WHERE id=?');
                    $check->execute([$actual['pending_audit_event_id']]);
                    $this->assertSame(0, (int) $check->fetchColumn());
                    $this->assertTrue((new SyntheticAuditReconciler($pdo))->reconcile($actual, $actual['pending_audit_event_id']));
                }
                $this->assertSame([], $pdo->query('PRAGMA foreign_key_check')->fetchAll());
            } finally {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
                $check = null;
                $pdo = null;
                foreach (glob($path.'*') as $file) {
                    if (is_file($file) && realpath(dirname($file)) === realpath(sys_get_temp_dir())) {
                        unlink($file);
                    }
                }
            }
        }
    }

    public function test_seven_tables_and_all_foreign_keys_are_enforced(): void
    {
        foreach (['merchant_identity_records', 'merchant_contact_versions', 'merchant_onboarding_contexts', 'phone_otp_challenges', 'otp_delivery_intents', 'contact_verification_proofs', 'verification_limit_state'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertCount(21, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
        $this->assertSame(1, (int) $this->pdo->query('PRAGMA foreign_keys')->fetchColumn());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame('ok', $this->pdo->query('PRAGMA integrity_check')->fetchColumn());
    }

    public function test_integrity_revocation_cannot_create_or_rewrite_consumption_history(): void
    {
        $proof = $this->proof();
        $before = (array) DB::table('contact_verification_proofs')->where('id', $proof)->first();
        $this->rejected(fn () => DB::table('contact_verification_proofs')->where('id', $proof)->update(['state' => 'revoked', 'revoked_at' => self::START + 3, 'reason_code' => 'contact_changed', 'consumed_at' => self::START + 2, 'consumed_application_id' => $this->application, 'state_version' => 2]), 'Immutable data or invalid transition');
        $this->assertSame($before, (array) DB::table('contact_verification_proofs')->where('id', $proof)->first());
        $this->assertTrue($this->service->consume($this->binding, $proof, $this->application, self::START + 2));
        $this->rejected(fn () => DB::table('contact_verification_proofs')->where('id', $proof)->update(['state' => 'revoked', 'revoked_at' => self::START + 4, 'reason_code' => 'contact_changed', 'consumed_at' => self::START + 3, 'state_version' => 3]), 'Immutable data or invalid transition');
        DB::table('contact_verification_proofs')->where('id', $proof)->update(['state' => 'revoked', 'revoked_at' => self::START + 4, 'reason_code' => 'contact_changed', 'state_version' => 3]);
        $row = DB::table('contact_verification_proofs')->where('id', $proof)->first();
        $this->assertSame(self::START + 2, $row->consumed_at);
        $this->assertSame($this->application, $row->consumed_application_id);
    }

    public function test_integrity_unconsumed_revocation_preserves_null_history_and_is_idempotent(): void
    {
        $proof = $this->proof();
        $this->service->revoke($this->binding, self::START + 2, 'contact_changed');
        $rows = DB::table('contact_verification_proofs')->get()->toJson();
        $audit = DB::table('audit_logs')->get()->toJson();
        $this->service->revoke($this->binding, self::START + 3, 'contact_changed');
        $this->service->revoke(array_replace($this->binding, ['contact' => (string) Str::ulid()]), self::START + 3, 'contact_changed');
        $this->assertSame($rows, DB::table('contact_verification_proofs')->get()->toJson());
        $this->assertSame($audit, DB::table('audit_logs')->get()->toJson());
        $this->assertNull(DB::table('contact_verification_proofs')->where('id', $proof)->value('consumed_at'));
        $this->assertNull(DB::table('contact_verification_proofs')->where('id', $proof)->value('consumed_application_id'));
    }

    public function test_integrity_consumed_revocation_keeps_history_and_active_only_revocation_is_audited_once(): void
    {
        $proof = $this->proof();
        $this->service->consume($this->binding, $proof, $this->application, self::START + 2);
        $this->service->revoke($this->binding, self::START + 3, 'security_incident');
        $before = DB::table('audit_logs')->count();
        $this->service->revoke($this->binding, self::START + 4, 'security_incident');
        $this->assertSame($before, DB::table('audit_logs')->count());
        $this->assertSame(self::START + 2, DB::table('contact_verification_proofs')->value('consumed_at'));
        $id = $this->active(self::START + 60000);
        $this->service->revoke($this->binding, self::START + 60001, 'security_incident');
        $audit = DB::table('audit_logs')->count();
        $this->service->revoke($this->binding, self::START + 60002, 'security_incident');
        $this->assertSame($audit, DB::table('audit_logs')->count());
        $this->assertSame('revoked', DB::table('phone_otp_challenges')->where('id', $id)->value('state'));
    }

    public function test_integrity_consumption_chronology_has_inclusive_issue_and_exclusive_expiry_bounds(): void
    {
        $id = $this->service->issue($this->binding, self::START, (string) Str::uuid());
        $this->service->deliver($this->binding, $id, self::START + 10);
        foreach ([self::START + 9, self::START + 300000, self::START + 300001] as $time) {
            $this->rejected(fn () => DB::table('phone_otp_challenges')->where('id', $id)->update(['state' => 'consumed', 'active_slot' => null, 'terminal_at' => $time, 'state_version' => 4]), 'CHECK constraint failed: otp_terminal_chronology');
            $this->assertSame('active', DB::table('phone_otp_challenges')->where('id', $id)->value('state'));
        }
        DB::table('phone_otp_challenges')->where('id', $id)->update(['state' => 'consumed', 'active_slot' => null, 'terminal_at' => self::START + 10, 'state_version' => 4]);
        $this->assertSame(self::START + 10, DB::table('phone_otp_challenges')->where('id', $id)->value('terminal_at'));
    }

    public function test_integrity_exhaustion_expiry_and_revocation_chronology(): void
    {
        $id = $this->service->issue($this->binding, self::START, (string) Str::uuid());
        $this->service->deliver($this->binding, $id, self::START + 10);
        for ($i = 0; $i < 4; $i++) {
            $this->service->verify($this->binding, $id, '999999', self::START + 11);
        }
        foreach ([self::START + 9, self::START + 300000] as $time) {
            $this->rejected(fn () => DB::table('phone_otp_challenges')->where('id', $id)->update(['state' => 'exhausted', 'failed_attempts' => 5, 'active_slot' => null, 'terminal_at' => $time, 'state_version' => 8]), 'CHECK constraint failed: otp_terminal_chronology');
        }
        $this->rejected(fn () => DB::table('phone_otp_challenges')->where('id', $id)->update(['state' => 'expired', 'active_slot' => null, 'terminal_at' => self::START + 299999, 'state_version' => 8]), 'CHECK constraint failed: otp_terminal_chronology');
        $this->rejected(fn () => DB::table('phone_otp_challenges')->where('id', $id)->update(['state' => 'revoked', 'active_slot' => null, 'terminal_at' => self::START + 9, 'state_version' => 8]), 'CHECK constraint failed: otp_terminal_chronology');
        DB::table('phone_otp_challenges')->where('id', $id)->update(['state' => 'expired', 'active_slot' => null, 'terminal_at' => self::START + 300000, 'state_version' => 8]);
        $this->assertSame('expired', DB::table('phone_otp_challenges')->where('id', $id)->value('state'));
    }

    public function test_integrity_proof_revocation_cannot_predate_consumption(): void
    {
        $proof = $this->proof();
        $this->service->consume($this->binding, $proof, $this->application, self::START + 10);
        $this->rejected(fn () => DB::table('contact_verification_proofs')->where('id', $proof)->update(['state' => 'revoked', 'revoked_at' => self::START + 9, 'reason_code' => 'security_incident', 'state_version' => 3]), 'CHECK constraint failed: proof_revocation_chronology');
        DB::table('contact_verification_proofs')->where('id', $proof)->update(['state' => 'revoked', 'revoked_at' => self::START + 10, 'reason_code' => 'security_incident', 'state_version' => 3]);
        $this->assertSame(self::START + 10, DB::table('contact_verification_proofs')->where('id', $proof)->value('revoked_at'));
    }

    public function test_integrity_context_tuple_checks_are_independent_of_version_or_fixture_failure(): void
    {
        $valid = ['current_contact_id' => $this->binding['contact'], 'contact_actor_user_id' => $this->binding['actor'], 'contact_purpose' => 'application_phone'];
        $fields = array_keys($valid);
        for ($mask = 1; $mask < 7; $mask++) {
            $data = ['context_version' => 2];
            foreach ($fields as $bit => $field) {
                $data[$field] = ($mask & (1 << $bit)) ? $valid[$field] : null;
            }
            $this->rejected(fn () => DB::table('merchant_onboarding_contexts')->where('merchant_id', $this->binding['merchant'])->update($data), 'CHECK constraint failed: (current_contact_id IS NULL');
            $this->assertSame(1, DB::table('merchant_onboarding_contexts')->value('context_version'));
        }
        $this->rejected(fn () => DB::table('merchant_onboarding_contexts')->where('merchant_id', $this->binding['merchant'])->update(['contact_actor_user_id' => 999999, 'context_version' => 2]), 'FOREIGN KEY constraint failed');
        DB::table('merchant_onboarding_contexts')->where('merchant_id', $this->binding['merchant'])->update(['context_version' => 2]);
        DB::table('merchant_onboarding_contexts')->where('merchant_id', $this->binding['merchant'])->update(['current_contact_id' => null, 'contact_actor_user_id' => null, 'contact_purpose' => null, 'context_version' => 3]);
        $this->assertSame(3, DB::table('merchant_onboarding_contexts')->value('context_version'));
    }

    public function test_integrity_migration_guards_refuse_working_config_without_database_facade_access(): void
    {
        $original = DB::getFacadeRoot();
        $settings = config('database.connections.sqlite');
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        DB::swap(\Mockery::mock(\stdClass::class));
        try {
            foreach (['100000_create_synthetic_verification_contract', '100001_extend_synthetic_audit_contract', '100002_add_synthetic_pending_audit_obligations'] as $file) {
                $migration = require database_path('migrations/2026_11_06_'.$file.'.php');
                foreach (['up', 'down'] as $operation) {
                    $message = match (substr($file, 0, 6)) {
                        '100000' => 'B3.2-B requires isolated synthetic SQLite.',
                        '100001' => 'Audit reconstruction is approved only in disposable memory SQLite.',
                        default => 'R06 migration requires disposable memory SQLite.',
                    };
                    $this->rejected(fn () => $migration->$operation(), $message, LogicException::class);
                }
            }
        } finally {
            DB::swap($original);
            config(['database.connections.sqlite' => $settings]);
        }
    }

    public function test_integrity_audit_outage_preserves_fifth_failure_and_gates_before_comparison(): void
    {
        $id = $this->active();
        for ($i = 0; $i < 4; $i++) {
            $this->service->verify($this->binding, $id, '999999', self::START + 1);
        }
        DB::unprepared("CREATE TRIGGER integrity_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'synthetic exhaustion audit outage'); END");
        $this->assertNull($this->service->verify($this->binding, $id, '999999', self::START + 2));
        $before = DB::table('verification_limit_state')->get()->toJson();
        $comparisons = $this->service->codeComparisons();
        for ($retry = 0; $retry < 2; $retry++) {
            $this->assertNull($this->service->verify($this->binding, $id, $retry ? '000123' : '999999', self::START + 3));
            $this->assertSame(5, DB::table('phone_otp_challenges')->where('id', $id)->value('failed_attempts'));
            $this->assertSame($before, DB::table('verification_limit_state')->get()->toJson());
            $this->assertSame(0, DB::table('contact_verification_proofs')->count());
            $this->assertSame($comparisons, $this->service->codeComparisons());
        }
        $this->assertNotNull(DB::table('verification_limit_state')->value('pending_audit_event_id'));
    }

    public function test_integrity_audit_outage_preserves_tenth_aggregate_failure_without_proof(): void
    {
        $first = $this->active();
        for ($i = 0; $i < 5; $i++) {
            $this->service->verify($this->binding, $first, '999999', self::START + 1);
        }
        $second = $this->active(self::START + 60000);
        for ($i = 0; $i < 4; $i++) {
            $this->service->verify($this->binding, $second, '999999', self::START + 60001);
        }
        $this->assertNotNull(DB::table('verification_limit_state')->value('failure_09_at'));
        $this->assertNull(DB::table('verification_limit_state')->value('failure_10_at'));
        DB::unprepared("CREATE TRIGGER integrity_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'synthetic exhaustion audit outage'); END");
        $this->assertNull($this->service->verify($this->binding, $second, '999999', self::START + 60002));
        $before = DB::table('verification_limit_state')->get()->toJson();
        $this->assertSame(5, DB::table('phone_otp_challenges')->where('id', $second)->value('failed_attempts'));
        $this->assertNotNull(DB::table('verification_limit_state')->value('failure_10_at'));
        $this->assertSame('aggregate_exhausted', DB::table('verification_limit_state')->value('pending_audit_reason_code'));
        $this->assertNull($this->service->issue($this->binding, self::START + 1800001, (string) Str::uuid()));
        $this->assertSame($before, DB::table('verification_limit_state')->get()->toJson());
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
    }

    public function test_lowercase_parent_ids_are_preserved_through_proof_application_fk(): void
    {
        $merchant = $this->binding['merchant'];
        $application = $this->application;
        $audit = DB::table('audit_logs')->where('event_type', 'merchant.created')->first();
        $this->assertSame(strtolower($merchant), $merchant);
        $this->assertNotSame(strtoupper($merchant), $merchant);
        $this->assertSame(strtolower($application), $application);
        $proof = $this->proof();
        $this->assertTrue($this->service->consume($this->binding, $proof, $application, self::START + 2));
        $this->assertSame($application, DB::table('contact_verification_proofs')->where('id', $proof)->value('consumed_application_id'));
        foreach (['merchant_contact_versions', 'merchant_onboarding_contexts', 'phone_otp_challenges', 'otp_delivery_intents', 'contact_verification_proofs', 'verification_limit_state'] as $table) {
            $this->assertSame($merchant, DB::table($table)->value('merchant_id'));
        }
        $this->assertSame($merchant, DB::table('merchants')->value('id'));
        $this->assertSame($application, DB::table('merchant_applications')->value('id'));
        $this->assertEquals($audit, DB::table('audit_logs')->where('id', $audit->id)->first());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_case_only_aad_equivalence_never_bypasses_binary_database_identity(): void
    {
        $contact = (array) DB::table('merchant_contact_versions')->first();
        $changed = $this->binding['merchant'];
        for ($i = 0; $i < strlen($changed); $i++) {
            if ($changed[$i] >= 'a' && $changed[$i] <= 'z') {
                $changed[$i] = strtoupper($changed[$i]);
                break;
            }
        }
        $this->assertNotSame($this->binding['merchant'], $changed);
        $cipherBinding = ['namespace' => 'synthetic', 'merchant' => $changed, 'record' => strtolower($contact['id']), 'purpose' => 'application_phone', 'version' => 1, 'actor' => $this->binding['actor']];
        $this->assertSame('SYNTH-PHONE-A', (new SyntheticCipher($this->keys))->decrypt('phone', $contact['phone_ciphertext'], $cipherBinding, 'phone.v1')->forCryptoAssertion());
        $altered = array_replace($this->binding, ['merchant' => $changed]);
        $this->rejected(fn () => $this->service->issue($altered, self::START, (string) Str::uuid()), 'Synthetic authority unavailable.', LogicException::class);
        $copy = $contact;
        $copy['id'] = (string) Str::ulid();
        $copy['merchant_id'] = $changed;
        $copy['version'] = 2;
        $this->rejected(fn () => $this->service->insert('merchant_contact_versions', $copy), 'FOREIGN KEY constraint failed');
        $proof = $this->proof();
        $this->rejected(fn () => $this->service->consume($this->binding, $proof, strtoupper($this->application), self::START + 2), 'FOREIGN KEY constraint failed');
        $this->assertSame('available', DB::table('contact_verification_proofs')->where('id', $proof)->value('state'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_second_real_synthetic_merchant_cannot_adopt_foreign_contact_or_ciphertext(): void
    {
        $owner = User::findOrFail($this->binding['actor']);
        $other = app(CreateMerchant::class)->execute($owner, 'Second synthetic tenant');
        $this->rejected(fn () => $this->service->insert('merchant_onboarding_contexts', ['merchant_id' => $other->id, 'current_contact_id' => $this->binding['contact'], 'contact_actor_user_id' => $owner->id, 'contact_purpose' => 'application_phone', 'updated_at' => self::START]), 'FOREIGN KEY constraint failed');
        $contact = (array) DB::table('merchant_contact_versions')->first();
        $binding = ['namespace' => 'synthetic', 'merchant' => $other->id, 'record' => $contact['id'], 'purpose' => 'application_phone', 'version' => 1, 'actor' => $owner->id];
        $this->rejected(fn () => (new SyntheticCipher($this->keys))->decrypt('phone', $contact['phone_ciphertext'], $binding, 'phone.v1'), 'Sensitive value unavailable.', LogicException::class);
        $proof = $this->proof();
        $foreignApplication = $other->applications()->sole()->id;
        $this->rejected(fn () => $this->service->consume($this->binding, $proof, $foreignApplication, self::START + 2), 'FOREIGN KEY constraint failed');
        $this->assertSame('available', DB::table('contact_verification_proofs')->where('id', $proof)->value('state'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_raw_sql_cannot_bypass_contact_immutability_checks_or_tenant_constraints(): void
    {
        $id = $this->binding['contact'];
        foreach (['purpose' => 'login_mfa', 'target_fingerprint' => str_repeat('a', 64), 'actor_user_id' => 999999] as $field => $value) {
            $this->rejected(fn () => DB::table('merchant_contact_versions')->where('id', $id)->update([$field => $value, 'row_version' => 2]), 'Immutable data or invalid transition');
        }
        $this->rejected(fn () => DB::table('merchant_contact_versions')->where('id', $id)->update(['row_version' => 1]), 'Immutable data or invalid transition');
        $this->rejected(fn () => DB::table('merchant_contact_versions')->where('id', $id)->delete(), 'Evidence deletion prohibited');
        $this->rejected(fn () => $this->contact((string) Str::ulid(), 2), 'UNIQUE constraint failed: merchant_contact_versions.merchant_id, merchant_contact_versions.actor_user_id, merchant_contact_versions.purpose');
        DB::table('merchant_contact_versions')->where('id', $id)->update(['state' => 'retired', 'retired_at' => self::START, 'row_version' => 2]);
        $valid = (array) DB::table('merchant_contact_versions')->where('id', $id)->first();
        $valid = array_replace($valid, ['id' => (string) Str::ulid(), 'version' => 2, 'state' => 'current', 'retired_at' => null, 'row_version' => 1]);
        foreach (['version' => 0, 'purpose' => 'login_mfa', 'target_fingerprint' => 'bad', 'phone_key_ref' => 'BAD KEY', 'row_version' => 0, 'retired_at' => self::START, 'phone_ciphertext' => ''] as $field => $value) {
            $this->rejected(fn () => $this->service->insert('merchant_contact_versions', array_replace($valid, [$field => $value])), 'CHECK constraint failed');
            $this->assertFalse(DB::table('merchant_contact_versions')->where('id', $valid['id'])->exists());
        }
        $this->rejected(fn () => $this->service->insert('merchant_contact_versions', array_replace($valid, ['version' => 1.5])), "CHECK constraint failed: version IS NULL OR (typeof(version)='integer' AND version >= 1)");
        $this->rejected(fn () => $this->service->insert('merchant_contact_versions', array_replace($valid, ['actor_user_id' => 999999])), 'FOREIGN KEY constraint failed');
        $this->rejected(fn () => $this->service->insert('merchant_contact_versions', array_replace($valid, ['version' => 1])), 'UNIQUE constraint failed: merchant_contact_versions.merchant_id, merchant_contact_versions.actor_user_id, merchant_contact_versions.purpose, merchant_contact_versions.version');
        $this->service->insert('merchant_contact_versions', $valid);
        $this->assertSame('current', DB::table('merchant_contact_versions')->where('id', $valid['id'])->value('state'));
    }

    public function test_identity_envelope_rotation_preserves_logical_content_and_rejects_resurrection(): void
    {
        $id = (string) Str::ulid();
        $binding = ['namespace' => 'synthetic', 'merchant' => $this->binding['merchant'], 'record' => $id, 'purpose' => 'identity_declaration', 'version' => 1, 'actor' => $this->binding['actor']];
        $cipher = new SyntheticCipher($this->keys);
        $ct = $cipher->encrypt('nin', 'SYNTHETIC-NIN-A', $binding, 'nin.v1');
        $this->service->insert('merchant_identity_records', ['id' => $id, 'merchant_id' => $binding['merchant'], 'version' => 1, 'subject_claim' => 'unknown', 'nin_ciphertext' => $ct, 'encryption_key_ref' => 'nin.v1', 'envelope_version' => 1, 'declaration_version' => 'synthetic.v1', 'notice_version' => 'synthetic.v1', 'declared_by_user_id' => $binding['actor'], 'declared_at' => self::START]);
        $rotated = $cipher->rotate('nin', $ct, $binding, 'nin.v1', 1, 'nin.v2');
        $this->rejected(fn () => DB::table('merchant_identity_records')->where('id', $id)->update(['nin_ciphertext' => $rotated, 'row_version' => 2]), 'Immutable data or invalid transition');
        DB::table('merchant_identity_records')->where('id', $id)->update(['nin_ciphertext' => $rotated, 'encryption_key_ref' => 'nin.v2', 'envelope_revision' => 2, 'row_version' => 2]);
        $this->assertSame('SYNTHETIC-NIN-A', $cipher->decrypt('nin', $rotated, $binding, 'nin.v2', 2)->forCryptoAssertion());
        DB::table('merchant_identity_records')->where('id', $id)->update(['state' => 'superseded', 'superseded_at' => self::START, 'row_version' => 3]);
        $this->rejected(fn () => DB::table('merchant_identity_records')->where('id', $id)->update(['state' => 'current', 'superseded_at' => null, 'row_version' => 4]), 'Immutable data or invalid transition');
        $this->assertSame(1, DB::table('merchant_identity_records')->value('version'));
    }

    public function test_five_guesses_exhaust_challenge_and_cannot_be_replayed(): void
    {
        $id = $this->active();
        for ($i = 0; $i < 5; $i++) {
            $this->assertNull($this->service->verify($this->binding, $id, 'wrong', self::START + 1));
        }
        $this->assertSame('exhausted', DB::table('phone_otp_challenges')->value('state'));
        $this->assertNull($this->service->verify($this->binding, $id, '000123', self::START + 2));
        $this->assertSame(5, DB::table('phone_otp_challenges')->value('failed_attempts'));
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
        $this->rejected(fn () => DB::table('phone_otp_challenges')->where('id', $id)->update(['state' => 'active', 'active_slot' => 1, 'terminal_at' => null, 'state_version' => 9]), 'Immutable data or invalid transition');
    }

    public function test_ten_rolling_failures_block_across_resends_and_recover_at_exact_boundary(): void
    {
        foreach ([self::START, self::START + 60000] as $time) {
            $id = $this->active($time);
            for ($i = 0; $i < 5; $i++) {
                $this->assertNull($this->service->verify($this->binding, $id, '999999', $time));
            }
        }
        $this->assertNull($this->service->issue($this->binding, self::START + 1799999, (string) Str::uuid()));
        $this->assertNotNull($this->service->issue($this->binding, self::START + 1800000, (string) Str::uuid()));
        $row = (array) DB::table('verification_limit_state')->first();
        $this->assertSame(self::START + 60000, $row['failure_01_at']);
        $this->assertNull($row['failure_06_at']);
    }

    public function test_resend_cooldown_idempotency_and_old_code_revocation(): void
    {
        $request = (string) Str::uuid();
        $first = $this->service->issue($this->binding, self::START, $request);
        $this->assertSame($first, $this->service->issue($this->binding, self::START + 1, $request));
        $this->assertNull($this->service->issue($this->binding, self::START + 59999, (string) Str::uuid()));
        $second = $this->service->issue($this->binding, self::START + 60000, (string) Str::uuid());
        $this->assertNotNull($second);
        $this->assertSame('revoked', DB::table('phone_otp_challenges')->where('id', $first)->value('state'));
        $this->assertNull($this->service->verify($this->binding, $first, '000123', self::START + 60001));
        $this->assertSame(2, DB::table('otp_delivery_intents')->count());
    }

    public function test_code_expiry_boundary_and_wrong_binding_do_not_create_proof(): void
    {
        $id = $this->active();
        foreach (['merchant' => (string) Str::ulid(), 'actor' => 999999, 'contact' => (string) Str::ulid(), 'purpose' => 'financial_authorization'] as $key => $value) {
            $this->rejected(fn () => $this->service->verify(array_replace($this->binding, [$key => $value]), $id, '000123', self::START + 1), 'Synthetic authority unavailable.', LogicException::class);
        }
        $this->assertNull($this->service->verify($this->binding, $id, '000123', self::START + 300000));
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
    }

    public function test_fake_delivery_failure_unknown_and_duplicate_callbacks_cannot_prove_ownership(): void
    {
        foreach (['rejected', 'unknown'] as $index => $outcome) {
            $now = self::START + $index * 60000;
            $id = $this->active($now, $outcome);
            $this->service->deliver($this->binding, $id, $now + 1, 'accepted');
            $this->assertNull($this->service->verify($this->binding, $id, '000123', $now + 2));
            $this->assertSame($outcome, DB::table('otp_delivery_intents')->where('challenge_id', $id)->value('state'));
        }
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
    }

    public function test_proof_is_single_use_and_history_survives_revocation(): void
    {
        $proof = $this->proof();
        $this->assertFalse($this->service->eligibleForLive($proof));
        $this->assertTrue($this->service->consume($this->binding, $proof, $this->application, self::START + 2));
        $this->assertFalse($this->service->consume($this->binding, $proof, $this->application, self::START + 3));
        $this->service->revoke($this->binding, self::START + 4, 'contact_changed');
        $row = DB::table('contact_verification_proofs')->where('id', $proof)->first();
        $this->assertSame('revoked', $row->state);
        $this->assertSame($this->application, $row->consumed_application_id);
        $this->assertSame(self::START + 1, $row->verified_at);
        $this->assertSame('draft', DB::table('merchant_applications')->value('status'));
        $this->rejected(fn () => DB::table('contact_verification_proofs')->where('id', $proof)->update(['state' => 'available', 'revoked_at' => null, 'reason_code' => null, 'state_version' => 4]), 'Immutable data or invalid transition');
    }

    public function test_proof_expiry_and_authority_revocation_are_fail_closed(): void
    {
        $proof = $this->proof();
        $this->assertFalse($this->service->consume($this->binding, $proof, $this->application, self::START + 1800001));
        DB::table('merchant_memberships')->where('user_id', $this->binding['actor'])->update(['status' => 'revoked']);
        $this->rejected(fn () => $this->service->consume($this->binding, $proof, $this->application, self::START + 2), 'Synthetic authority unavailable.', LogicException::class);
        $this->service->revoke($this->binding, self::START + 1800002, 'authority_lost');
        $this->assertSame('authority_lost', DB::table('contact_verification_proofs')->value('reason_code'));
    }

    public function test_changed_phone_blocks_proof_without_erasing_historical_binding(): void
    {
        $proof = $this->proof();
        DB::table('merchant_contact_versions')->where('id', $this->binding['contact'])->update(['state' => 'retired', 'retired_at' => self::START + 2, 'row_version' => 2]);
        $next = (string) Str::ulid();
        $this->contact($next, 2, 'SYNTH-PHONE-B');
        DB::table('merchant_onboarding_contexts')->where('merchant_id', $this->binding['merchant'])->update(['current_contact_id' => $next, 'context_version' => 2, 'updated_at' => self::START + 2]);
        $this->rejected(fn () => $this->service->consume($this->binding, $proof, $this->application, self::START + 3), 'Synthetic authority unavailable.', LogicException::class);
        $this->service->revoke($this->binding, self::START + 3, 'contact_changed');
        $this->assertSame($this->binding['contact'], DB::table('contact_verification_proofs')->value('contact_id'));
    }

    public function test_audit_failure_rolls_back_successful_code_consumption(): void
    {
        $id = $this->active();
        DB::unprepared("CREATE TRIGGER synthetic_reject_audit BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.verified' BEGIN SELECT RAISE(ABORT,'injected failure'); END");
        $this->rejected(fn () => $this->service->verify($this->binding, $id, '000123', self::START + 1), 'injected failure');
        $this->assertSame('active', DB::table('phone_otp_challenges')->value('state'));
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
    }

    public function test_audit_enum_round_trip_redaction_and_unknown_event_rejection(): void
    {
        foreach (AuditEvent::cases() as $event) {
            $entry = AuditLog::forceCreate(['event_type' => $event, 'merchant_id' => $this->binding['merchant'], 'actor_user_id' => $this->binding['actor'], 'request_id' => (string) Str::uuid(), 'occurred_at' => now(), 'metadata' => []]);
            $this->assertSame($event, $entry->fresh()->event_type);
        }
        $this->rejected(fn () => AuditLog::forceCreate(['event_type' => AuditEvent::OtpChanged, 'request_id' => (string) Str::uuid(), 'occurred_at' => now(), 'metadata' => ['phone' => 'SYNTH-PHONE-A']]), 'Audit metadata must use the bounded allowlist.', InvalidArgumentException::class);
        $this->rejected(fn () => DB::table('audit_logs')->insert(['id' => (string) Str::ulid(), 'event_type' => 'invented.event', 'request_id' => (string) Str::uuid(), 'occurred_at' => now()]), 'CHECK constraint failed');
        $dump = json_encode(DB::table('audit_logs')->get(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('SYNTH-PHONE', $dump);
        $this->assertStringNotContainsString('000123', $dump);
    }

    public function test_migration_down_reapply_preserves_old_records_and_audit_bytes(): void
    {
        $audit = require database_path('migrations/2026_11_06_100001_extend_synthetic_audit_contract.php');
        $schema = require database_path('migrations/2026_11_06_100000_create_synthetic_verification_contract.php');
        $extension = require database_path('migrations/2026_11_06_100002_add_synthetic_pending_audit_obligations.php');
        $rows = DB::table('audit_logs')->get()->toJson();
        $users = DB::table('users')->get()->toJson();
        $indexes = DB::select("SELECT name,sql FROM sqlite_master WHERE tbl_name='audit_logs' AND type='index' ORDER BY name");
        $extension->down();
        $audit->down();
        $schema->down();
        $this->assertSame($rows, DB::table('audit_logs')->get()->toJson());
        $this->assertSame($users, DB::table('users')->get()->toJson());
        $schema->up();
        $audit->up();
        $extension->up();
        $this->assertEquals($indexes, DB::select("SELECT name,sql FROM sqlite_master WHERE tbl_name='audit_logs' AND type='index' ORDER BY name"));
        $this->assertSame($rows, DB::table('audit_logs')->get()->toJson());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_audit_downgrade_refuses_new_history_and_reconstruction_failure_rolls_back(): void
    {
        $migration = require database_path('migrations/2026_11_06_100001_extend_synthetic_audit_contract.php');
        $this->active();
        $before = DB::table('audit_logs')->get()->toJson();
        $this->rejected(fn () => $migration->down(), 'New audit history prevents downgrade.', LogicException::class);
        $this->assertSame($before, DB::table('audit_logs')->get()->toJson());
        DB::statement('CREATE TABLE audit_logs_b32_rebuild (id INTEGER)');
        $this->rejected(fn () => $migration->up(), 'table audit_logs_b32_rebuild already exists', QueryException::class);
        $this->assertSame($before, DB::table('audit_logs')->get()->toJson());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_coordinator_rejects_non_fixture_file_before_schema_operations(): void
    {
        $path = sys_get_temp_dir().'/not-an-xlipa-fixture-'.bin2hex(random_bytes(6)).'.sqlite';
        try {
            $pdo = new PDO('sqlite:'.$path);
            $this->rejected(fn () => new SyntheticCoordinator($pdo), 'Working databases are forbidden.', LogicException::class);
            $pdo = null;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function syntheticSnapshot(string $path): PDO
    {
        $target = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = DB::select("SELECT name,sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($tables as $table) {
            $target->exec($table->sql);
            foreach ($this->pdo->query('SELECT * FROM "'.$table->name.'"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $statement = $target->prepare('INSERT INTO "'.$table->name.'" ('.implode(',', array_keys($row)).') VALUES ('.implode(',', array_fill(0, count($row), '?')).')');
                $statement->execute(array_values($row));
            }
        }
        foreach (DB::select("SELECT sql FROM sqlite_master WHERE type IN ('index','trigger') AND sql IS NOT NULL") as $object) {
            $target->exec($object->sql);
        }

        return $target;
    }

    private function concurrent(array $payloads): array
    {
        $path = sys_get_temp_dir().'/xlipa-b32-synthetic-'.bin2hex(random_bytes(8)).'.sqlite';
        $pdo = $this->syntheticSnapshot($path);
        $workers = [];
        try {
            foreach ($payloads as $index => $payload) {
                $payload += ['path' => $path, 'worker' => $index, 'binding' => $this->binding];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/concurrent-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 15;
            while (! is_file($path.'.ready0') || ! is_file($path.'.ready1')) {
                if (microtime(true) > $deadline) {
                    throw new LogicException('Concurrent fixture failed to start.');
                }
                usleep(10000);
            }
            file_put_contents($path.'.go', 'go');
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $errors);
                $results[] = json_decode($output, true, 16, JSON_THROW_ON_ERROR)['result'];
            }
            $this->assertSame([], $pdo->query('PRAGMA foreign_key_check')->fetchAll());

            return [$results, $pdo->query('SELECT failed_attempts FROM phone_otp_challenges ORDER BY created_at DESC LIMIT 1')->fetchColumn(), $pdo->query('SELECT count(*) FROM contact_verification_proofs')->fetchColumn(), $pdo->query('SELECT * FROM verification_limit_state ORDER BY updated_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC), $pdo->query('SELECT count(*) FROM audit_logs')->fetchColumn()];
        } finally {
            $pdo = null;
            foreach (glob($path.'*') as $file) {
                if (is_file($file) && str_starts_with($file, $path) && realpath(dirname($file)) === realpath(sys_get_temp_dir())) {
                    unlink($file);
                }
            }
        }
    }

    public function test_two_concurrent_correct_guesses_have_one_winner(): void
    {
        $id = $this->active();
        $payload = ['operation' => 'guess', 'challenge' => $id, 'code' => '000123', 'now' => self::START + 1];
        [$results, , $proofs] = $this->concurrent([$payload, $payload]);
        $this->assertCount(1, array_filter($results));
        $this->assertSame(1, (int) $proofs);
    }

    public function test_concurrent_wrong_guesses_cannot_exceed_five(): void
    {
        $id = $this->active();
        for ($i = 0; $i < 4; $i++) {
            $this->service->verify($this->binding, $id, '999999', self::START + 1);
        }
        $payload = ['operation' => 'guess', 'challenge' => $id, 'code' => '999999', 'now' => self::START + 2];
        [, $attempts] = $this->concurrent([$payload, $payload]);
        $this->assertSame(5, (int) $attempts);
    }

    public function test_concurrent_resends_and_proof_consumption_have_one_winner(): void
    {
        $proof = $this->proof();
        $consume = ['operation' => 'consume', 'proof' => $proof, 'application' => $this->application, 'now' => self::START + 2];
        [$results] = $this->concurrent([$consume, $consume]);
        $this->assertCount(1, array_filter($results));
        $resend = ['operation' => 'resend', 'request' => (string) Str::uuid(), 'now' => self::START + 60000];
        [$results] = $this->concurrent([$resend, array_replace($resend, ['request' => (string) Str::uuid()])]);
        $this->assertCount(1, array_filter($results));
    }

    public function test_synthetic_backup_restore_and_restart_preserve_ciphertext_and_limits(): void
    {
        $id = $this->active();
        $this->service->verify($this->binding, $id, '999999', self::START + 1);
        $path = sys_get_temp_dir().'/xlipa-b32-synthetic-'.bin2hex(random_bytes(8)).'.sqlite';
        $pdo = $this->syntheticSnapshot($path);
        $pdo = null;
        try {
            $pdo = new PDO('sqlite:'.$path);
            $service = new SyntheticVerification($pdo, new SyntheticKeys);
            $this->assertNull($service->issue($this->binding, self::START + 59999, (string) Str::uuid()));
            $this->assertNull($service->verify($this->binding, $id, '999999', self::START + 2));
            $this->assertSame(2, (int) $pdo->query('SELECT failed_attempts FROM phone_otp_challenges')->fetchColumn());
            $this->assertSame(self::START + 1, (int) $pdo->query('SELECT failure_01_at FROM verification_limit_state')->fetchColumn());
        } finally {
            $service = $pdo = null;
            unlink($path);
        }
    }
}
