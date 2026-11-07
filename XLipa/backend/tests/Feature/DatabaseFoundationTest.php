<?php

namespace Tests\Feature;

use App\Actions\Merchants\CreateMerchant;
use App\Actions\Merchants\RevokeMembership;
use App\Enums\MembershipStatus;
use App\Enums\MerchantRole;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\MerchantApplication;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new LogicException('Refusing schema tests outside disposable in-memory SQLite.');
        }
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => array_map(fn (string $file): string => 'database/migrations/'.basename($file), glob(database_path('migrations/0001_*.php'))), '--no-interaction' => true])->assertExitCode(0);
        $this->migrateFoundation();
    }

    private function migrateFoundation(): void
    {
        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => array_map(fn (string $file): string => 'database/migrations/'.basename($file), glob(database_path('migrations/2026_11_06_00000*.php'))), '--no-interaction' => true])->assertExitCode(0);
    }

    private function rejectedWrite(callable $write): void
    {
        try {
            $write();
            $this->fail('The database accepted an invalid write.');
        } catch (QueryException $error) {
            $this->assertSame('23000', $error->errorInfo[0]);
        }
    }

    public function test_migrations_add_and_remove_only_the_foundation_and_preserve_identity(): void
    {
        $user = User::factory()->create();
        $identity = DB::table('users')->where('id', $user->id)->first();
        app(CreateMerchant::class)->execute($user, 'Disposable rollback fixture');
        $this->assertCount(14, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
        $this->artisan('migrate:rollback', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('merchants'));
        $this->assertFalse(Schema::hasColumn('users', 'auth_version'));
        $this->assertCount(9, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
        $this->assertSame($identity->password, DB::table('users')->where('id', $user->id)->value('password'));
        $this->assertSame($identity->email, DB::table('users')->where('id', $user->id)->value('email'));
        $this->migrateFoundation();
        $this->assertSame('active', DB::table('users')->where('id', $user->id)->value('account_status'));
        $this->assertSame(1, DB::table('users')->where('id', $user->id)->value('auth_version'));
        $this->assertSame(0, Merchant::count());
    }

    public function test_atomic_creation_establishes_owner_draft_and_audit(): void
    {
        $owner = User::factory()->create();
        $merchant = app(CreateMerchant::class)->execute($owner, 'Independent Shop');
        $this->assertTrue(Str::isUlid($merchant->id));
        $membership = $merchant->memberships()->sole();
        $this->assertSame($owner->id, $membership->user->id);
        $this->assertSame(MerchantRole::Owner, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame(1, $merchant->applications()->sole()->current_slot);
        $this->assertTrue(Str::isUlid($merchant->applications()->sole()->id));
        $this->assertTrue(Str::isUlid(AuditLog::sole()->id));
        $this->assertSame($merchant->id, AuditLog::sole()->merchant->id);
        $this->assertSame($owner->id, AuditLog::sole()->actor->id);
    }

    public function test_audit_failure_rolls_back_entire_merchant_creation(): void
    {
        $owner = User::factory()->create();
        DB::unprepared("CREATE TRIGGER reject_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'test audit failure'); END");
        $this->rejectedWrite(fn () => app(CreateMerchant::class)->execute($owner, 'Rollback Shop'));
        $this->assertSame(0, Merchant::count());
        $this->assertSame(0, MerchantMembership::count());
        $this->assertSame(0, MerchantApplication::count());
        $this->assertSame(0, AuditLog::count());
        $this->assertSame(1, User::count());
    }

    public function test_membership_is_many_to_many_and_platform_roles_do_not_grant_tenant_access(): void
    {
        $owner = User::factory()->create();
        $a = app(CreateMerchant::class)->execute($owner, 'Business A');
        $b = Merchant::factory()->create();
        MerchantMembership::factory()->create(['merchant_id' => $b->id, 'user_id' => $owner->id, 'role' => 'manager']);
        $admin = PlatformRoleAssignment::factory()->create()->user;
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $owner->merchants()->pluck('merchants.id')->all());
        $this->assertSame(2, Merchant::forMember($owner)->count());
        $this->assertSame(0, Merchant::forMember($admin)->count());
        $this->assertSame('admin', $admin->platformRoleAssignment->role->value);
        DB::table('merchant_memberships')->where('merchant_id', $b->id)->where('user_id', $owner->id)->update(['status' => 'revoked']);
        $this->assertSame([$a->id], Merchant::forMember($owner)->pluck('id')->all());
        DB::table('users')->where('id', $owner->id)->update(['account_status' => 'suspended']);
        $this->assertSame(0, Merchant::forMember($owner)->count());
    }

    public function test_cross_merchant_mutation_and_last_owner_revocation_are_denied(): void
    {
        $owner = User::factory()->create();
        $a = app(CreateMerchant::class)->execute($owner, 'Business A');
        $b = Merchant::factory()->create();
        $revoke = app(RevokeMembership::class);
        try {
            $revoke->execute($owner, $a, $a->memberships()->sole());
            $this->fail('Last owner was revoked.');
        } catch (LogicException) {
            $this->assertSame('active', $a->memberships()->sole()->status->value);
        }
        try {
            $revoke->execute($owner, $a, $b->memberships()->sole());
            $this->fail('Mismatched tenant was accepted.');
        } catch (ModelNotFoundException) {
            $this->assertSame('active', $b->memberships()->sole()->status->value);
        }
        try {
            $revoke->execute($owner, $b, $b->memberships()->sole());
            $this->fail('Cross-tenant actor was accepted.');
        } catch (AuthorizationException) {
            $this->assertSame(0, AuditLog::where('event_type', 'membership.revoked')->count());
        }
    }

    public function test_revoking_one_of_two_owners_is_audited_and_last_remaining_owner_is_retained(): void
    {
        $merchant = Merchant::factory()->create();
        $first = $merchant->creator;
        $second = User::factory()->create();
        $other = MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $second->id, 'role' => 'owner']);
        app(RevokeMembership::class)->execute($first, $merchant, $other);
        $this->assertSame(MembershipStatus::Revoked, $other->fresh()->status);
        app(RevokeMembership::class)->execute($first, $merchant, $other);
        $this->assertSame(1, AuditLog::where('event_type', 'membership.revoked')->count());
        $this->assertSame(1, $merchant->memberships()->where('status', 'active')->where('role', 'owner')->count());
    }

    public function test_foreign_keys_restrict_identity_and_merchant_deletion(): void
    {
        $merchant = Merchant::factory()->create();
        $this->rejectedWrite(fn () => DB::table('users')->where('id', $merchant->created_by_user_id)->delete());
        $this->rejectedWrite(fn () => DB::table('merchants')->where('id', $merchant->id)->delete());
        $this->rejectedWrite(fn () => DB::table('merchant_memberships')->insert(['merchant_id' => $merchant->id, 'user_id' => 9999999, 'role' => 'staff', 'status' => 'active']));
    }

    public function test_unique_membership_and_platform_assignment_constraints(): void
    {
        $merchant = Merchant::factory()->create();
        $this->rejectedWrite(fn () => MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $merchant->created_by_user_id]));
        $assignment = PlatformRoleAssignment::factory()->create();
        $this->rejectedWrite(fn () => PlatformRoleAssignment::factory()->create(['user_id' => $assignment->user_id]));
        $this->rejectedWrite(fn () => DB::table('platform_role_assignments')->insert(['user_id' => 999999, 'role' => 'admin', 'granted_at' => now()]));
    }

    public function test_all_actor_and_tenant_foreign_keys_are_enforced(): void
    {
        $merchant = Merchant::factory()->create();
        $application = $merchant->applications()->sole();
        $membership = $merchant->memberships()->sole();
        $assignment = PlatformRoleAssignment::factory()->create();
        $event = AuditLog::factory()->create();
        $missingMerchant = (string) Str::ulid();
        $this->rejectedWrite(fn () => DB::table('merchants')->where('id', $merchant->id)->update(['created_by_user_id' => 999999]));
        $this->rejectedWrite(fn () => DB::table('merchant_memberships')->where('id', $membership->id)->update(['merchant_id' => $missingMerchant]));
        $this->rejectedWrite(fn () => DB::table('merchant_memberships')->where('id', $membership->id)->update(['created_by_user_id' => 999999]));
        $this->rejectedWrite(fn () => DB::table('platform_role_assignments')->where('user_id', $assignment->user_id)->update(['granted_by_user_id' => 999999]));
        foreach (['submitted_by_user_id', 'reviewed_by_user_id'] as $column) {
            $this->rejectedWrite(fn () => DB::table('merchant_applications')->where('id', $application->id)->update([$column => 999999]));
        }
        $this->rejectedWrite(fn () => DB::table('merchant_applications')->where('id', $application->id)->update(['merchant_id' => $missingMerchant]));
        foreach (['actor_user_id', 'target_user_id'] as $column) {
            $this->rejectedWrite(fn () => DB::table('audit_logs')->where('id', $event->id)->update([$column => 999999]));
        }
        $this->rejectedWrite(fn () => DB::table('audit_logs')->where('id', $event->id)->update(['merchant_id' => $missingMerchant]));
        $this->rejectedWrite(fn () => DB::table('audit_logs')->where('id', $event->id)->update(['merchant_id' => $merchant->id, 'application_id' => (string) Str::ulid()]));
    }

    public function test_database_rejects_invalid_role_and_status_values(): void
    {
        $merchant = Merchant::factory()->create();
        $owner = $merchant->creator;
        $this->rejectedWrite(fn () => DB::table('users')->where('id', $owner->id)->update(['account_status' => 'admin']));
        $this->rejectedWrite(fn () => DB::table('merchants')->where('id', $merchant->id)->update(['operational_status' => 'paid']));
        $this->rejectedWrite(fn () => DB::table('merchants')->where('id', $merchant->id)->update(['verification_status' => 'enabled']));
        $this->rejectedWrite(fn () => DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['role' => 'chief_admin']));
        $this->rejectedWrite(fn () => DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['status' => 'approved']));
        $this->rejectedWrite(fn () => DB::table('platform_role_assignments')->insert(['user_id' => $owner->id, 'role' => 'owner', 'granted_at' => now()]));
        $this->rejectedWrite(fn () => DB::table('merchant_applications')->where('merchant_id', $merchant->id)->update(['status' => 'successful']));
    }

    public function test_application_current_slot_attempt_uniqueness_and_history_replacement(): void
    {
        $merchant = Merchant::factory()->create();
        $first = $merchant->applications()->sole();
        $this->rejectedWrite(fn () => MerchantApplication::factory()->create(['merchant_id' => $merchant->id, 'attempt_number' => 2, 'current_slot' => 1]));
        $this->rejectedWrite(fn () => MerchantApplication::factory()->create(['merchant_id' => $merchant->id, 'attempt_number' => 1]));
        $this->rejectedWrite(fn () => DB::table('merchant_applications')->where('id', $first->id)->update(['current_slot' => 0]));
        DB::transaction(function () use ($first, $merchant): void {
            $this->assertSame(1, DB::table('merchant_applications')->where('id', $first->id)->where('current_slot', 1)->update(['current_slot' => null]));
            MerchantApplication::factory()->create(['merchant_id' => $merchant->id, 'attempt_number' => 2, 'current_slot' => 1]);
        });
        MerchantApplication::factory()->create(['merchant_id' => $merchant->id, 'attempt_number' => 3]);
        $this->assertSame(3, $merchant->applications()->count());
        $this->assertSame(1, $merchant->applications()->where('current_slot', 1)->count());
        $this->assertNull($first->fresh()->current_slot);
        $this->assertSame(0, DB::table('merchant_applications')->where('id', $first->id)->where('current_slot', 1)->update(['current_slot' => null]));
    }

    public function test_failed_replacement_transaction_preserves_current_application(): void
    {
        $merchant = Merchant::factory()->create();
        $first = $merchant->applications()->sole();
        $this->rejectedWrite(fn () => DB::transaction(function () use ($merchant, $first): void {
            DB::table('merchant_applications')->where('id', $first->id)->update(['current_slot' => null]);
            MerchantApplication::factory()->create(['merchant_id' => $merchant->id, 'attempt_number' => 1, 'current_slot' => 1]);
        }));
        $this->assertSame(1, $first->fresh()->current_slot);
        $this->assertSame(1, $merchant->applications()->count());
    }

    public function test_application_evidence_is_encrypted_hidden_and_immutable_after_submission(): void
    {
        $merchant = Merchant::factory()->create();
        $application = $merchant->applications()->sole();
        $application->forceFill(['status' => 'pending_review', 'profile_snapshot' => ['business_name' => 'Private Shop'], 'internal_review_notes' => 'Private note', 'submitted_by_user_id' => $merchant->created_by_user_id, 'submitted_at' => now()])->save();
        $this->assertSame('Private Shop', $application->fresh()->profile_snapshot['business_name']);
        $this->assertStringNotContainsString('Private Shop', DB::table('merchant_applications')->where('id', $application->id)->value('profile_snapshot'));
        $this->assertStringNotContainsString('Private note', DB::table('merchant_applications')->where('id', $application->id)->value('internal_review_notes'));
        $this->assertArrayNotHasKey('profile_snapshot', $application->toArray());
        $this->assertArrayNotHasKey('internal_review_notes', $application->toArray());
        $this->expectException(LogicException::class);
        $application->forceFill(['profile_snapshot' => ['business_name' => 'Altered']])->save();
    }

    public function test_audit_application_cannot_reference_another_tenant(): void
    {
        $a = Merchant::factory()->create();
        $b = Merchant::factory()->create();
        $this->rejectedWrite(fn () => AuditLog::factory()->create(['merchant_id' => $a->id, 'application_id' => $b->applications()->sole()->id]));
    }

    public function test_audit_is_append_only_and_sensitive_payload_keys_are_rejected(): void
    {
        $event = AuditLog::factory()->create();
        try {
            AuditLog::factory()->create(['metadata' => ['password' => 'synthetic-secret']]);
            $this->fail('Sensitive metadata key was allowed.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(1, AuditLog::count());
        }
        try {
            $event->forceFill(['metadata' => ['to' => 'active']])->save();
            $this->fail('Audit edit was allowed.');
        } catch (LogicException) {
            $this->assertSame('suspended', $event->fresh()->metadata['to']);
        }
        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_positive_version_constraints_apply_to_raw_sql_updates(): void
    {
        $merchant = Merchant::factory()->create();
        $this->rejectedWrite(fn () => DB::table('users')->where('id', $merchant->created_by_user_id)->update(['auth_version' => 0]));
        $this->rejectedWrite(fn () => DB::table('merchants')->where('id', $merchant->id)->update(['profile_version' => -1]));
        $this->rejectedWrite(fn () => DB::table('merchant_applications')->where('merchant_id', $merchant->id)->update(['attempt_number' => 0]));
        $this->rejectedWrite(fn () => DB::table('merchant_applications')->where('merchant_id', $merchant->id)->update(['profile_version' => 0]));
        $this->rejectedWrite(fn () => DB::table('users')->where('id', $merchant->created_by_user_id)->update(['auth_version' => 'invalid']));
        $this->rejectedWrite(fn () => DB::table('merchants')->where('id', $merchant->id)->update(['profile_version' => 1.5]));
    }

    public function test_positive_version_constraints_also_reject_invalid_inserts(): void
    {
        $this->rejectedWrite(fn () => User::factory()->create(['auth_version' => 0]));
        $this->rejectedWrite(fn () => Merchant::factory()->create(['profile_version' => 0]));
        $this->rejectedWrite(fn () => MerchantApplication::factory()->create(['attempt_number' => 0]));
    }

    public function test_security_fields_are_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $user->fill(['account_status' => 'closed', 'auth_version' => 100]);
        $this->assertFalse($user->isDirty());
        foreach ([new Merchant, new MerchantMembership, new PlatformRoleAssignment, new MerchantApplication, new AuditLog] as $model) {
            $this->assertTrue($model->isGuarded('role'));
            $this->assertTrue($model->isGuarded('merchant_id'));
        }
    }

    public function test_competing_sqlite_writers_and_stale_current_slot_update_are_rejected(): void
    {
        $merchant = Merchant::factory()->create();
        $first = $merchant->applications()->sole();
        $path = tempnam(sys_get_temp_dir(), 'xlipa-b1-');
        $this->assertNotFalse($path);
        $one = null;
        $two = null;

        try {
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            $one = new \PDO('sqlite:'.$path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $two = new \PDO('sqlite:'.$path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $one->exec('PRAGMA foreign_keys = ON');
            $two->exec('PRAGMA foreign_keys = ON');
            $one->exec('PRAGMA busy_timeout = 0');
            $two->exec('PRAGMA busy_timeout = 0');
            $one->exec('BEGIN IMMEDIATE');
            try {
                $two->exec('BEGIN IMMEDIATE');
                $this->fail('A competing SQLite writer acquired the lock.');
            } catch (\PDOException $error) {
                $this->assertSame(5, $error->errorInfo[1]);
            }
            $statement = $one->prepare('UPDATE merchant_applications SET current_slot = NULL WHERE id = ? AND current_slot = 1');
            $statement->execute([$first->id]);
            $this->assertSame(1, $statement->rowCount());
            $nextId = (string) Str::ulid();
            $insert = $one->prepare("INSERT INTO merchant_applications (id, merchant_id, attempt_number, current_slot, profile_version, status) VALUES (?, ?, 2, '1', 1, 'draft')");
            $insert->execute([$nextId, $merchant->id]);
            $one->exec('COMMIT');
            $two->exec('BEGIN IMMEDIATE');
            $stale = $two->prepare('UPDATE merchant_applications SET current_slot = NULL WHERE id = ? AND current_slot = 1');
            $stale->execute([$first->id]);
            $this->assertSame(0, $stale->rowCount());
            $this->assertSame($nextId, $two->query('SELECT id FROM merchant_applications WHERE current_slot = 1')->fetchColumn());
            $two->exec('COMMIT');
        } finally {
            foreach ([$one, $two] as $connection) {
                if ($connection !== null) {
                    try {
                        $connection->exec('ROLLBACK');
                    } catch (\PDOException) {
                        // A completed transaction needs no rollback.
                    }
                }
            }
            unset($connection, $statement, $insert, $stale);
            $one = null;
            $two = null;
            foreach ([$path, $path.'-journal', $path.'-wal', $path.'-shm'] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
        }
    }
}
