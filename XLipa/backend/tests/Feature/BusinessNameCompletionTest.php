<?php

namespace Tests\Feature;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\BusinessNameCompletionContext;
use App\Actions\Merchants\BusinessNameCompletionOutcomeUnknown;
use App\Actions\Merchants\BusinessNameCompletionResolver;
use App\Actions\Merchants\BusinessNameCompletionSchema as BNC;
use App\Actions\Merchants\BusinessNameCompletionService;
use App\Actions\Merchants\DeniedBusinessNameCompletionAdmission;
use App\Actions\Merchants\SubmissionEvidenceSchema as SE;
use App\Actions\Merchants\SubmissionTransaction;
use App\Models\Merchant;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\B32CRehearsal;
use Tests\Support\FakeBusinessNameCompletionAdmission;
use Tests\TestCase;

class BusinessNameCompletionTest extends TestCase
{
    private User $owner;

    private Merchant $merchant;

    private array $expected;

    private FakeBusinessNameCompletionAdmission $admission;

    private string $name = 'Fictional Kijiji Shop';

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        Notification::fake();
        $this->owner = User::factory()->create();
        $this->merchant = Merchant::factory()->create(['created_by_user_id' => $this->owner->id, 'business_name' => '']);
        config(['se1.selected' => realpath(SE::artifact())]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SE::artifact()], '--realpath' => true, '--no-interaction' => true]));
        config(['bnc1.selected' => realpath(BNC::artifact())]);
        BNC::install();
        $this->owner->refresh();
        $this->actingAs($this->owner)->withSession(['auth_version' => 1]);
        $this->expected = ['application_id' => $this->merchant->applications()->value('id'), 'attempt_number' => 1, 'merchant_profile_version' => 1, 'draft_profile_version' => 1, 'membership_id' => $this->merchant->memberships()->value('id'), 'authority_version' => 1, 'auth_version' => 1, 'email_binding_version' => 1, 'completion_request_id' => (string) Str::uuid()];
        $this->admission = new FakeBusinessNameCompletionAdmission;
        $this->app->instance(BusinessNameCompletionAdmission::class, $this->admission);
    }

    private function complete(?array $expected = null, mixed $name = null): array
    {
        return app(BusinessNameCompletionService::class)->complete($this->owner, $this->merchant->id, $expected ?? $this->expected, $name ?? $this->name);
    }

    private function resolve(?array $expected = null, ?string $name = null): array
    {
        return app(BusinessNameCompletionResolver::class)->resolve($this->owner, $this->merchant->id, $expected ?? $this->expected, $name ?? $this->name);
    }

    private function fingerprint(): string
    {
        $rows = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
            $rows[$table->name] = DB::select('SELECT * FROM '.$table->name.' ORDER BY rowid');
        }

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private function denied(Closure $operation, int $status): void
    {
        $before = $this->fingerprint();
        try {
            $operation();
            $this->fail('Expected completion denial.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
            $this->assertStringNotContainsString($this->name, $error->getMessage());
            $this->assertStringNotContainsString('SQLSTATE', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        $this->assertSame($before, $this->fingerprint());
    }

    private function fileFixture(Closure $operation): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlipa-bnc-command-');
        $this->assertNotFalse($path);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($path)));
        unlink($path);
        try {
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $path, 'database.connections.sqlite.busy_timeout' => 0]);
            DB::statement('PRAGMA recursive_triggers=ON');
            $operation($path);
        } finally {
            $this->admission->connections = [];
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => ':memory:']);
            foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }

    public function test_atomic_completion_and_privacy_without_kyc_or_side_effects(): void
    {
        $beforeMerchant = (array) DB::table('merchants')->first();
        $beforeDraft = (array) DB::table('merchant_applications')->first();
        $beforeAudit = DB::table('audit_logs')->get()->toArray();
        $result = $this->complete(name: '  '.$this->name.'  ');
        $this->assertSame('completed', $result['status']);
        $record = (array) DB::table(BNC::TABLE)->sole();
        $this->assertSame($record['id'], $result['completion_id']);
        $this->assertSame(1, DB::table(BNC::WITNESSES)->count());
        $this->assertSame($record['id'], DB::table(BNC::WITNESSES)->value('completion_id'));
        $this->assertSame([...$beforeMerchant, 'business_name' => $this->name, 'profile_version' => 2, 'updated_at' => $record['occurred_at']], (array) DB::table('merchants')->sole());
        $this->assertSame([...$beforeDraft, 'profile_version' => 2, 'updated_at' => $record['occurred_at']], (array) DB::table('merchant_applications')->sole());
        $this->assertSame($beforeAudit, DB::table('audit_logs')->get()->toArray());
        $this->assertSame($this->admission->time, $record['occurred_at_ms']);
        $this->assertSame('fixture.redacted', $record['record_policy_version']);
        $this->assertStringNotContainsString($this->name, json_encode([$record, $result, DB::table(BNC::WITNESSES)->get()]));
        foreach (['merchant_identity_records', 'merchant_contact_versions', 'phone_otp_challenges', SE::TABLE] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        Notification::assertNothingSent();
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_exact_version_noop_stale_noop_and_duplicate_uuid_are_distinct(): void
    {
        $this->complete();
        $this->denied(fn () => $this->complete(), 409);
        $this->denied(fn () => $this->complete([...$this->expected, 'completion_request_id' => (string) Str::uuid()]), 409);
        $fresh = [...$this->expected, 'merchant_profile_version' => 2, 'draft_profile_version' => 2, 'completion_request_id' => (string) Str::uuid()];
        $before = $this->fingerprint();
        $this->assertSame(['status' => 'unchanged'], $this->complete($fresh));
        $this->assertSame($before, $this->fingerprint());
        $this->denied(fn () => $this->complete($fresh, 'Another Fictional Name'), 409);
    }

    public static function invalidNames(): array
    {
        return [[''], ['   '], [null], [123], [false], [[]], ["Name\0"], ["Name\n"], ["\tName"], ["\xC3\x28"], [str_repeat('界', 201)], ["\u{00A0}"], ["\u{200B}Name"], ["Name\u{00A0}"], ["Name\u{2028}X"]];
    }

    #[DataProvider('invalidNames')]
    public function test_strict_name_validation(mixed $name): void
    {
        $this->denied(fn () => app(BusinessNameCompletionService::class)->complete($this->owner, $this->merchant->id, $this->expected, $name), 422);
    }

    public static function validNames(): array
    {
        return [['12345'], [str_repeat('界', 200)], ['Café & <Shop>'], ["Shop\u{00A0}Name"]];
    }

    #[DataProvider('validNames')]
    public function test_valid_name_bytes_are_preserved(string $name): void
    {
        $this->complete(name: $name);
        $this->assertSame($name, DB::table('merchants')->value('business_name'));
    }

    public static function invalidExpectations(): array
    {
        return [['merchant_profile_version', '1', 422], ['draft_profile_version', 2, 409], ['attempt_number', 2, 409], ['membership_id', 999999, 403], ['auth_version', 2, 403], ['authority_version', 2, 403], ['email_binding_version', 2, 403], ['application_id', '00000000000000000000000000', 409], ['completion_request_id', 'NOT-A-UUID', 422], ['trusted_time', 1, 422], ['merchant_profile_version', true, 422], ['merchant_profile_version', 1.0, 422], ['application_id', ' padded ', 422]];
    }

    #[DataProvider('invalidExpectations')]
    public function test_exact_expectations_and_allowlist(string $field, mixed $value, int $status): void
    {
        $this->denied(fn () => $this->complete([...$this->expected, $field => $value]), $status);
    }

    public static function trustFailures(): array
    {
        return [['allow', false], ['profile', 'se1'], ['restore', false], ['retention', false], ['reconciliation', false], ['time', -1], ['time', 253402300800000], ['policy', 'private policy']];
    }

    #[DataProvider('trustFailures')]
    public function test_each_independent_trust_requirement_denies(string $field, mixed $value): void
    {
        $this->admission->$field = $value;
        $this->denied(fn () => $this->complete(), 503);
    }

    public function test_default_binding_and_denied_implementation_supply_no_authority(): void
    {
        $this->app->forgetInstance(BusinessNameCompletionAdmission::class);
        $this->assertInstanceOf(DeniedBusinessNameCompletionAdmission::class, app(BusinessNameCompletionAdmission::class));
        $this->denied(fn () => $this->complete(), 503);
    }

    public static function authorityFailures(): array
    {
        return [['session'], ['guest'], ['email'], ['revoked'], ['foreign'], ['suspended'], ['closed'], ['draft'], ['missing']];
    }

    #[DataProvider('authorityFailures')]
    public function test_fresh_authority_and_workflow_denials(string $failure): void
    {
        $status = 403;
        switch ($failure) {
            case 'session': $this->withSession(['auth_version' => 2]);
                break;
            case 'guest': auth()->logout();
                break;
            case 'email': DB::table('users')->update(['email_verified_at' => null, 'email_binding_version' => 2]);
                break;
            case 'revoked': DB::table('merchant_memberships')->update(['status' => 'revoked', 'revoked_at' => '2026-11-13 00:00:00', 'authority_version' => 2]);
                break;
            case 'foreign': $other = User::factory()->create();
                $this->actingAs($other)->withSession(['auth_version' => 1]);
                $this->owner = $other;
                break;
            case 'suspended': case 'closed': DB::table('merchants')->update(['operational_status' => $failure]);
                $status = 409;
                break;
            case 'draft': DB::table('merchant_applications')->update(['current_slot' => null]);
                $status = 409;
                break;
            case 'missing': $this->expected['application_id'] = (string) Str::ulid();
                $status = 409;
                break;
        }
        $this->denied(fn () => $this->complete(), $status);
    }

    public function test_final_admission_failure_rolls_back_complete_bundle(): void
    {
        $this->admission->inspect = function (): void {
            if (count($this->admission->connections) === 3) {
                $this->admission->retention = false;
            }
        };
        $this->denied(fn () => $this->complete(), 503);
        $this->assertSame(0, DB::table(BNC::TABLE)->count());
    }

    public function test_revocation_at_writer_boundary_denies_before_completion_record(): void
    {
        $this->admission->inspect = function (): void {
            if (count($this->admission->connections) === 2) {
                DB::table('merchant_memberships')->update(['status' => 'revoked', 'revoked_at' => '2026-11-13 00:00:00', 'authority_version' => 2]);
            }
        };
        $this->denied(fn () => $this->complete(), 403);
        $this->assertSame(0, DB::table(BNC::TABLE)->count());
    }

    public function test_future_observed_time_is_a_reconciliation_hold_not_a_clock_source(): void
    {
        DB::table('verification_limit_state')->insert(['scope_key' => str_repeat('e', 64), 'scope_key_version' => 'contact.v1', 'policy_rule' => 'otp.fail.rolling30.v1', 'merchant_id' => $this->merchant->id, 'actor_user_id' => $this->owner->id, 'last_observed_at' => $this->admission->time + 1, 'updated_at' => $this->admission->time + 1]);
        $this->denied(fn () => $this->complete(), 409);
        $this->assertSame(1794528000123, $this->admission->time);
    }

    public function test_trusted_time_regression_under_writer_denies(): void
    {
        $this->admission->inspect = function (): void {
            if (count($this->admission->connections) === 2) {
                $this->admission->time--;
            }
        };
        $this->denied(fn () => $this->complete(), 503);
    }

    public function test_max_version_noop_needs_no_increment_and_actual_change_denies(): void
    {
        $merchant = Merchant::factory()->create(['created_by_user_id' => $this->owner->id, 'business_name' => $this->name, 'profile_version' => PHP_INT_MAX]);
        DB::table('merchant_applications')->where('merchant_id', $merchant->id)->update(['profile_version' => PHP_INT_MAX]);
        $this->merchant = $merchant;
        $this->expected = [...$this->expected, 'application_id' => $merchant->applications()->value('id'), 'membership_id' => $merchant->memberships()->value('id'), 'merchant_profile_version' => PHP_INT_MAX, 'draft_profile_version' => PHP_INT_MAX];
        $before = $this->fingerprint();
        $this->assertSame(['status' => 'unchanged'], $this->complete());
        $this->assertSame($before, $this->fingerprint());
        $empty = Merchant::factory()->create(['created_by_user_id' => $this->owner->id, 'business_name' => '', 'profile_version' => PHP_INT_MAX]);
        DB::table('merchant_applications')->where('merchant_id', $empty->id)->update(['profile_version' => PHP_INT_MAX]);
        $this->merchant = $empty;
        $this->expected = [...$this->expected, 'application_id' => $empty->applications()->value('id'), 'membership_id' => $empty->memberships()->value('id')];
        $this->denied(fn () => $this->complete(), 409);
    }

    public function test_noop_rechecks_the_graph_after_final_admission(): void
    {
        $this->complete();
        $this->admission->connections = [];
        $this->admission->inspect = function (): void {
            if (count($this->admission->connections) === 3) {
                DB::table('merchants')->update(['business_name' => 'Concurrent Name', 'profile_version' => 3]);
                DB::table('merchant_applications')->update(['profile_version' => 3]);
            }
        };
        $fresh = [...$this->expected, 'merchant_profile_version' => 2, 'draft_profile_version' => 2, 'completion_request_id' => (string) Str::uuid()];
        $this->denied(fn () => $this->complete($fresh), 409);
    }

    public function test_current_email_and_authenticated_identity_are_reloaded(): void
    {
        $other = User::factory()->create();
        $this->owner->email = 'stale-binding@fixture.test';
        $this->denied(fn () => $this->complete(), 403);
        $this->owner->refresh();
        $this->actingAs($other)->withSession(['auth_version' => 1]);
        $this->denied(fn () => $this->complete(), 403);
    }

    public function test_selected_profile_and_schema_tampering_cannot_enter_command(): void
    {
        config(['workflow_schema.expected_profile' => 'se1']);
        $this->denied(fn () => $this->complete(), 503);
        config(['workflow_schema.expected_profile' => 'b32c+se1+bnc1']);
        DB::unprepared('DROP TRIGGER bnc1_witness_insert');
        $this->denied(fn () => $this->complete(), 503);
    }

    public static function faultPoints(): array
    {
        return [['record'], ['merchant']];
    }

    #[DataProvider('faultPoints')]
    public function test_statement_faults_roll_back_without_private_error_leakage(string $point): void
    {
        DB::listen(function ($query) use ($point): void {
            if (($point === 'record' && str_starts_with($query->sql, 'insert into "'.BNC::TABLE.'"')) || ($point === 'merchant' && str_starts_with($query->sql, 'update "merchants"'))) {
                throw new RuntimeException($this->name.' SQLSTATE fictional fault');
            }
        });
        $this->denied(fn () => $this->complete(), 503);
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_generated_competing_writer_then_one_winner_and_stale_loser(): void
    {
        $this->fileFixture(function (string $path): void {
            $other = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $other->exec('PRAGMA busy_timeout=0');
            $other->exec('BEGIN IMMEDIATE');
            try {
                $this->denied(fn () => $this->complete(), 503);
            } finally {
                $other->exec('ROLLBACK');
                $other = null;
            }
            $this->complete();
            $this->denied(fn () => $this->complete([...$this->expected, 'completion_request_id' => (string) Str::uuid()], 'Competing Name'), 409);
            $this->assertSame(1, DB::table(BNC::TABLE)->count());
            $this->assertSame(1, DB::table(BNC::WITNESSES)->count());
        });
    }

    public static function acknowledgements(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('acknowledgements')]
    public function test_unknown_commit_is_not_replayed_and_explicit_resolution_uses_new_connection(bool $committed): void
    {
        $this->fileFixture(function () use ($committed): void {
            $transaction = new class($committed) extends SubmissionTransaction
            {
                public int $attempts = 0;

                public function __construct(private bool $committed) {}

                protected function commit(Connection $connection): void
                {
                    $this->attempts++;
                    if ($this->committed) {
                        parent::commit($connection);
                    }
                    throw new RuntimeException('Fictional lost acknowledgement');
                }
            };
            $this->app->instance(SubmissionTransaction::class, $transaction);
            $old = DB::connection();
            try {
                $this->complete();
                $this->fail('Expected unknown outcome.');
            } catch (BusinessNameCompletionOutcomeUnknown $error) {
                $this->assertStringContainsString('unknown', $error->getMessage());
                $this->assertNull($error->getPrevious());
            }
            $this->assertSame(1, $transaction->attempts);
            $this->assertNotSame($old, DB::connection());
            $this->app->instance(SubmissionTransaction::class, new SubmissionTransaction);
            DB::statement('PRAGMA recursive_triggers=ON');
            $this->admission->restore = false;
            $this->denied(fn () => $this->resolve(), 503);
            $this->admission->restore = true;
            $freshBefore = DB::connection();
            $this->assertSame($committed ? 'confirmed' : 'absent', $this->resolve()['status']);
            $this->assertNotSame($freshBefore, DB::connection());
            $this->assertSame($committed ? 1 : 0, DB::table(BNC::TABLE)->count());
            $this->assertSame(1, $transaction->attempts);
        });
    }

    public function test_resolver_conflicts_changed_tuple_and_never_claims_changed_state_success(): void
    {
        $this->fileFixture(function (): void {
            $this->complete();
            $this->denied(fn () => $this->resolve([...$this->expected, 'attempt_number' => 2]), 409);
            $this->assertSame('historical', $this->resolve(name: 'Different Requested Name')['status']);
            DB::table('merchants')->update(['business_name' => 'Later Name', 'profile_version' => 3]);
            DB::table('merchant_applications')->update(['profile_version' => 3]);
            $this->assertSame('historical', $this->resolve()['status']);
            $this->assertSame('held', $this->resolve([...$this->expected, 'completion_request_id' => (string) Str::uuid()])['status']);
            $this->assertSame('Later Name', DB::table('merchants')->value('business_name'));
        });
    }

    public function test_resolver_absence_requires_fence_and_rejects_current_revoked_owner(): void
    {
        $this->fileFixture(function (): void {
            $this->admission->restore = false;
            $this->denied(fn () => $this->resolve(), 503);
            $this->admission->restore = true;
            $before = $this->fingerprint();
            $this->assertSame(['status' => 'absent'], $this->resolve());
            $this->assertSame($before, $this->fingerprint());
            $this->complete();
            DB::table('merchant_memberships')->update(['status' => 'revoked', 'revoked_at' => '2026-11-13 00:00:00', 'authority_version' => 2]);
            $this->denied(fn () => $this->resolve(), 403);
        });
    }

    public function test_uncertain_noop_has_no_invented_historical_receipt(): void
    {
        $this->fileFixture(function (): void {
            $this->complete();
            $fresh = [...$this->expected, 'merchant_profile_version' => 2, 'draft_profile_version' => 2, 'completion_request_id' => (string) Str::uuid()];
            $transaction = new class extends SubmissionTransaction
            {
                public int $attempts = 0;

                protected function commit(Connection $connection): void
                {
                    $this->attempts++;
                    parent::commit($connection);
                    throw new RuntimeException('Fictional lost no-op acknowledgement');
                }
            };
            $this->app->instance(SubmissionTransaction::class, $transaction);
            try {
                $this->complete($fresh);
                $this->fail('Unacknowledged no-op must remain unknown.');
            } catch (BusinessNameCompletionOutcomeUnknown $error) {
                $this->assertNull($error->getPrevious());
            }
            $this->assertSame(1, $transaction->attempts);
            $this->app->instance(SubmissionTransaction::class, new SubmissionTransaction);
            $this->assertSame(['status' => 'held'], $this->resolve($fresh));
            $this->assertSame(1, DB::table(BNC::TABLE)->count());
        });
    }

    public function test_manifest_tampering_fails_closed_without_repair(): void
    {
        $path = BusinessNameCompletionContext::manifestPath();
        $original = file_get_contents($path);
        try {
            file_put_contents($path, str_replace('BNC-INT-02', 'unknown', $original));
            $this->denied(fn () => $this->complete(), 503);
            $this->assertStringContainsString('unknown', file_get_contents($path));
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_rollback_failure_is_unknown_and_purges_without_replaying(): void
    {
        $this->fileFixture(function (string $path): void {
            $pdo = new class('sqlite:'.$path) extends PDO
            {
                public int $rollbacks = 0;

                public function rollBack(): bool
                {
                    $this->rollbacks++;
                    throw new RuntimeException('Fictional rollback acknowledgement failure');
                }
            };
            $connection = DB::connection();
            $connection->setPdo($pdo);
            DB::statement('PRAGMA foreign_keys=ON');
            DB::statement('PRAGMA recursive_triggers=ON');
            $writes = 0;
            $connection->beforeExecuting(function (string $sql) use (&$writes): void {
                if (str_starts_with($sql, 'update "merchants"')) {
                    $writes++;
                    throw new RuntimeException('Fictional fault after record');
                }
            });
            try {
                $this->complete();
                $this->fail('Rollback failure must be unknown.');
            } catch (BusinessNameCompletionOutcomeUnknown $error) {
                $this->assertNull($error->getPrevious());
            }
            $this->assertSame(1, $writes);
            $this->assertSame(1, $pdo->rollbacks);
            $this->assertNotSame($connection, DB::connection());
            $pdo = null;
            $this->admission->connections = [];
            $this->assertSame('absent', $this->resolve()['status']);
        });
    }
}
