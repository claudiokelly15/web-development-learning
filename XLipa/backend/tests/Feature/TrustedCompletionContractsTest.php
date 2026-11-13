<?php

namespace Tests\Feature;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\BusinessNameCompletionRequestContext;
use App\Actions\Merchants\DeniedBusinessNameCompletionAdmission;
use App\Actions\Merchants\TrustedCompletionTimePolicy;
use App\Actions\Merchants\TrustedReleaseApproval;
use App\Actions\Merchants\TrustedStoreLineage;
use App\Actions\Merchants\TrustedWorkflowProfile;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FakeBusinessNameCompletionRequestContext;
use Tests\Support\FakeCompletionTrustAuthority;
use Tests\Support\FakeTrustedCompletionAdmission;
use Tests\TestCase;

class TrustedCompletionContractsTest extends TestCase
{
    private array $descriptor;

    private FakeCompletionTrustAuthority $authority;

    private FakeTrustedCompletionAdmission $admission;

    protected function setUp(): void
    {
        parent::setUp();
        $runtime = ['requested_profile' => WorkflowSchemaReadiness::BNC, 'environment' => 'fictional', 'store_id' => 'fixture-store', 'generation' => 'generation-1', 'fence_epoch' => 1, 'worker_id' => 'worker-1'];
        $artifacts = ['manifests' => [], 'sources' => []];
        foreach (['bnc' => 'manifest', 'integration' => 'integration-manifest', 'backend' => 'backend-manifest'] as $key => $file) {
            $path = database_path('activation/bnc1/'.$file.'.json');
            $artifacts['manifests'][$key] = hash_file('sha256', $path);
            $manifest = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            foreach (['base_pins', 'source_pins'] as $group) {
                foreach ($manifest[$group] ?? [] as $source => $digest) {
                    $actual = hash_file('sha256', base_path($source));
                    $this->assertSame($digest, $actual);
                    $artifacts['sources'][$source] = $actual;
                }
            }
        }
        $this->descriptor = ['version' => 1, ...$runtime, 'approved_profile' => WorkflowSchemaReadiness::BNC, 'release_id' => 'fictional-release-1', ...$artifacts,
            'approval_id' => 'independent-approval-1', 'provenance' => 'fictional-separate-custody', 'not_before' => 9000, 'expires_at' => 20000, 'revoked' => false];
        $this->authority = new FakeCompletionTrustAuthority($runtime, $artifacts);
        $this->authority->approve($this->descriptor);
        DB::connection()->setPdo($this->authority->memoryConnection()->getRawPdo());
        $this->admission = new FakeTrustedCompletionAdmission($this->authority, $this->descriptor, $this->owner());
        $this->admission->grant(7, $this->merchant(), $this->expected(), false);
    }

    private function merchant(): string
    {
        return '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    }

    private function expected(): array
    {
        return ['application_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW', 'attempt_number' => 1, 'merchant_profile_version' => 1, 'draft_profile_version' => 1, 'membership_id' => 1, 'authority_version' => 1, 'auth_version' => 1, 'email_binding_version' => 1, 'completion_request_id' => '12345678-1234-1234-1234-123456789abc'];
    }

    private function owner(): array
    {
        return ['actor' => 7, 'merchant' => $this->merchant(), 'membership_id' => 1, 'session' => 'fixture-session', 'verified' => true, 'active' => true, 'role' => 'owner', 'revoked' => false, 'suspended' => false, 'auth_version' => 1, 'email_binding_version' => 1, 'authority_version' => 1, 'session_auth_version' => 1, 'session_email_binding_version' => 1];
    }

    private function select(?array $descriptor = null): array
    {
        return (new TrustedWorkflowProfile)->select($descriptor ?? $this->descriptor, $this->authority->runtime, $this->authority->observed, $this->authority, $this->authority);
    }

    private function denied(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected fail-closed trust boundary.');
        } catch (RuntimeException $error) {
            $this->assertSame('Trusted workflow admission is unavailable.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    public function test_valid_independent_fictional_approval_binds_all_artifacts_and_exact_profile(): void
    {
        $this->assertSame($this->descriptor, $this->select());
        $this->assertSame(WorkflowSchemaReadiness::BNC, $this->admission->expectedProfile());
        $this->assertTrue($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
        $connection = $this->authority->memoryConnection();
        $this->assertTrue($this->admission->authoritative($connection, 7, $this->merchant(), $this->expected(), false));
    }

    public static function invalidDescriptors(): array
    {
        return [['version', 2], ['approved_profile', 'foundation'], ['requested_profile', 'unknown'],
            ['revoked', true], ['expires_at', 10000], ['not_before', 10001], ['fence_epoch', '1'],
            ['provenance', 'untrusted'], ['release_id', 'other-release'], ['approval_id', 'other-approval'],
            ['environment', 'production'], ['store_id', 'restored-store'], ['generation', 'generation-0'],
            ['fence_epoch', 0], ['worker_id', 'stale-worker'], ['expires_at', 253402300800000]];
    }

    #[DataProvider('invalidDescriptors')]
    public function test_absent_stale_or_altered_descriptor_never_falls_back(string $field, mixed $value): void
    {
        $bad = $this->descriptor;
        $bad[$field] = $value;
        $this->denied(fn () => $this->select($bad));
    }

    public function test_null_missing_extra_and_reordered_descriptor_fields_fail_safely(): void
    {
        $this->denied(fn () => (new TrustedWorkflowProfile)->select(null, $this->authority->runtime, $this->authority->observed, $this->authority, $this->authority));
        $bad = $this->descriptor;
        unset($bad['provenance']);
        $this->denied(fn () => $this->select($bad));
        $this->denied(fn () => $this->select($this->descriptor + ['fallback' => 'foundation']));
        $this->assertSame($this->descriptor, $this->select());
    }

    public static function changedRuntime(): array
    {
        return [['environment', 'wrong-environment'], ['store_id', 'wrong-store'], ['generation', 'generation-2'], ['fence_epoch', 2], ['worker_id', 'worker-2'], ['requested_profile', 'foundation']];
    }

    #[DataProvider('changedRuntime')]
    public function test_runtime_identity_changes_reject_stale_descriptors(string $key, mixed $value): void
    {
        $this->authority->runtime[$key] = $value;
        $this->denied(fn () => $this->select());
        $this->assertNull($this->admission->expectedProfile());
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
    }

    public function test_missing_revoked_and_unavailable_approval_deny_even_with_matching_files(): void
    {
        $empty = new FakeCompletionTrustAuthority($this->authority->runtime, $this->authority->observed);
        $this->denied(fn () => (new TrustedWorkflowProfile)->select($this->descriptor, $empty->runtime, $empty->observed, $empty, $empty));
        $this->authority->revoked[] = $this->descriptor['approval_id'];
        $this->denied(fn () => $this->select());
        $this->authority->revoked = [];
        $this->authority->approvalAvailable = false;
        $this->denied(fn () => $this->select());
    }

    public static function artifactChanges(): array
    {
        return [['manifests', 'bnc'], ['manifests', 'integration'], ['manifests', 'backend'], ['sources', 'app/Providers/AppServiceProvider.php']];
    }

    #[DataProvider('artifactChanges')]
    public function test_mixed_release_and_source_changes_require_independent_successor_approval(string $map, string $key): void
    {
        $this->authority->observed[$map][$key] = str_repeat('a', 64);
        $this->denied(fn () => $this->select());
        $bad = $this->descriptor;
        $bad[$map][$key] = str_repeat('a', 64);
        $this->denied(fn () => $this->select($bad));
        try {
            $this->authority->approve($bad);
            $this->fail('Cannot redefine an immutable release.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fictional trust authority is unavailable.', $error->getMessage());
        }
        $bad['release_id'] = 'fictional-successor-2';
        $bad['approval_id'] = 'independent-approval-2';
        $this->authority->approve($bad);
        $this->assertSame($bad, $this->select($bad));
    }

    public static function profiles(): array
    {
        return [[WorkflowSchemaReadiness::FOUNDATION], [WorkflowSchemaReadiness::C], [WorkflowSchemaReadiness::SE], [WorkflowSchemaReadiness::BNC]];
    }

    #[DataProvider('profiles')]
    public function test_exact_existing_profile_vocabulary_is_preserved_without_schema_inference(string $profile): void
    {
        $descriptor = $this->descriptor;
        $descriptor['requested_profile'] = $descriptor['approved_profile'] = $profile;
        $descriptor['release_id'] = 'fictional-'.hash('sha256', $profile);
        $descriptor['approval_id'] = 'approval-'.hash('sha256', $profile);
        $this->authority->runtime['requested_profile'] = $profile;
        $this->authority->approve($descriptor);
        $this->assertSame($profile, $this->select($descriptor)['approved_profile']);
    }

    public static function wrongOwners(): array
    {
        return [['actor', 8], ['merchant', '01ARZ3NDEKTSV4RRFFQ69G5FAX'], ['membership_id', 2],
            ['session', 'wrong-session'], ['verified', false], ['active', false], ['role', 'manager'], ['role', 'staff'],
            ['revoked', true], ['suspended', true], ['auth_version', 2], ['email_binding_version', 2],
            ['authority_version', 2], ['session_auth_version', 2], ['session_email_binding_version', 2]];
    }

    #[DataProvider('wrongOwners')]
    public function test_current_owner_and_session_are_required_independently_of_release_approval(string $key, mixed $value): void
    {
        $this->admission->owner[$key] = $value;
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
    }

    public function test_grants_bind_exact_actor_tenant_uuid_expectations_and_operation_mode(): void
    {
        $this->assertFalse($this->admission->authorized(8, $this->merchant(), $this->expected(), false));
        $this->assertFalse($this->admission->authorized(7, '01ARZ3NDEKTSV4RRFFQ69G5FAX', $this->expected(), false));
        foreach ($this->expected() as $key => $value) {
            $expected = $this->expected();
            $expected[$key] = is_int($value) ? $value + 1 : ($key === 'application_id' ? '01ARZ3NDEKTSV4RRFFQ69G5FAX' : 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
            $this->assertFalse($this->admission->authorized(7, $this->merchant(), $expected, false));
        }
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), true));
        $this->admission->grant(7, $this->merchant(), $this->expected(), true);
        $this->assertTrue($this->admission->authorized(7, $this->merchant(), $this->expected(), true));
        $this->admission->owner['auth_version'] = $this->admission->owner['session_auth_version'] = 2;
        $this->admission->owner['email_binding_version'] = $this->admission->owner['session_email_binding_version'] = 2;
        $this->admission->owner['authority_version'] = 2;
        $this->assertTrue($this->admission->authorized(7, $this->merchant(), $this->expected(), true));
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
    }

    public function test_replay_and_recovery_holds_never_authorize_repeat_command(): void
    {
        $this->authority->dispatched(7, $this->merchant(), $this->expected()['completion_request_id']);
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
        $this->admission->grant(7, $this->merchant(), $this->expected(), true);
        $this->assertTrue($this->admission->authorized(7, $this->merchant(), $this->expected(), true));
        $this->authority->recoveryHold = true;
        $this->assertFalse($this->authority->contextAccess(9000, 11000, 15000, true, false));
        $this->assertTrue($this->authority->contextAccess(9000, 9500, 15000, true, true));
        $this->authority->reconciliation = false;
        $this->assertFalse($this->admission->reconciliationReady(7, $this->merchant()));
    }

    public function test_only_enrolled_memory_connection_with_exact_pdo_and_current_fence_is_admitted(): void
    {
        $connection = $this->authority->memoryConnection();
        $this->assertTrue($this->admission->authoritative($connection, 7, $this->merchant(), $this->expected(), false));
        $untrusted = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:');
        $this->assertFalse($this->admission->authoritative($untrusted, 7, $this->merchant(), $this->expected(), false));
        $connection->setPdo(new PDO('sqlite::memory:'));
        $this->assertFalse($this->admission->authoritative($connection, 7, $this->merchant(), $this->expected(), false));
        $fresh = $this->authority->memoryConnection();
        $this->assertTrue($this->admission->authoritative($fresh, 7, $this->merchant(), $this->expected(), false));
        $this->authority->cutover = true;
        $this->assertFalse($this->admission->authoritative($fresh, 7, $this->merchant(), $this->expected(), false));
        $this->authority->cutover = false;
        $this->authority->runtime['fence_epoch'] = 2;
        $this->assertFalse($this->authority->authoritative($fresh, $this->descriptor));
        $this->authority->lineageAvailable = false;
        $this->assertFalse($this->authority->authoritative($fresh, $this->descriptor));
    }

    public function test_successor_generation_does_not_readmit_old_writer_or_reopened_connection(): void
    {
        $old = $this->authority->memoryConnection();
        $next = $this->descriptor;
        $next['generation'] = $this->authority->runtime['generation'] = 'generation-2';
        $next['fence_epoch'] = $this->authority->runtime['fence_epoch'] = 2;
        $next['worker_id'] = $this->authority->runtime['worker_id'] = 'worker-2';
        $next['approval_id'] = 'cutover-approval-2';
        $this->authority->approve($next);
        $this->assertSame($next, $this->select($next));
        $this->assertFalse($this->authority->authoritative($old, $next));
        $this->assertTrue($this->authority->authoritative($this->authority->memoryConnection(), $next));
        $this->assertFalse($this->authority->authoritative(new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:'), $next));
    }

    public static function clockFailures(): array
    {
        return [['time', -1], ['time', 253402300800000], ['time', 9999], ['time', 11001], ['clockAvailable', false]];
    }

    #[DataProvider('clockFailures')]
    public function test_clock_regression_jump_bounds_and_outage_deny(string $key, mixed $value): void
    {
        $this->select();
        $this->authority->$key = $value;
        $this->denied(fn () => $this->select());
    }

    public function test_retention_and_historical_policy_authority_are_independent_from_current_policy(): void
    {
        $this->assertTrue($this->admission->retentionAllowed('fixture.retention.v1', 9000));
        $this->assertFalse($this->admission->retentionAllowed('fixture.retention.v1', 5000));
        $this->assertFalse($this->admission->retentionAllowed('fixture.retention.v1', 10001));
        $this->assertFalse($this->admission->retentionAllowed('unknown', 9000));
        $this->authority->policy = 'fixture.retention.v2';
        $this->authority->policies['fixture.retention.v2'] = 1000;
        $this->assertSame('fixture.retention.v2', $this->admission->recordPolicy());
        $this->assertTrue($this->admission->retentionAllowed('fixture.retention.v1', 9000));
        unset($this->authority->policies['fixture.retention.v1']);
        $this->assertFalse($this->admission->retentionAllowed('fixture.retention.v1', 9000));
        $this->assertFalse($this->authority->contextAccess(9000, 10000, 12000, false, false));
        $this->assertTrue($this->authority->contextAccess(9000, 9500, 12000, true, true));
        $this->assertFalse($this->authority->contextAccess(9000, 9500, 10000, true, true));
    }

    public function test_int05_context_preserves_original_bytes_and_denies_rotation_revocation_and_generation_change(): void
    {
        $binding = ['actor_id' => 7, 'merchant_id' => $this->merchant(), 'session_id' => 'fixture-session', 'generation' => hash('sha256', $this->descriptor['store_id'].'|'.$this->descriptor['generation'].'|'.$this->descriptor['fence_epoch'])];
        $context = new FakeBusinessNameCompletionRequestContext(fn (array $binding, string $reference): bool => $this->admission->authorized($binding['actor_id'], $binding['merchant_id'], $this->expected(), true));
        $name = "PRIVATE e\u{0301}  Kijani🌿";
        $ref = $context->prepare($binding, $this->expected(), $name, 10000, 11000, 13000);
        $original = $context->dispatch($ref, $binding, 10001);
        $context->unresolved($ref, $binding, $original['token'], 10002);
        $this->assertSame($name, $original['name']);
        $this->assertSame($this->expected(), $original['expected']);
        $this->admission->grant(7, $this->merchant(), $this->expected(), true);
        $resolved = $context->beginResolution($ref, $binding, 10003);
        $this->assertSame($original['name'], $resolved['name']);
        $this->assertSame($original['expected'], $resolved['expected']);
        foreach (['session_id', 'generation'] as $key) {
            $wrong = $binding;
            $wrong[$key] = 'rotated-value';
            try {
                $context->beginResolution($ref, $wrong, 10004);
                $this->fail('Expected rotated context denial.');
            } catch (RuntimeException $error) {
                $this->assertSame('Private completion context is unavailable.', $error->getMessage());
            }
        }
        $this->admission->owner['revoked'] = true;
        try {
            $context->beginResolution($ref, $binding, 10005);
            $this->fail('Expected revoked-owner denial.');
        } catch (RuntimeException $error) {
            $this->assertSame('Private completion context is unavailable.', $error->getMessage());
        }
        $this->assertSame('unresolved', $context->status($ref, $binding, 13001)['state']);
    }

    public function test_runtime_defaults_and_working_connection_remain_unmodified(): void
    {
        $this->assertInstanceOf(DeniedBusinessNameCompletionAdmission::class, app(BusinessNameCompletionAdmission::class));
        foreach ([TrustedReleaseApproval::class, TrustedStoreLineage::class, TrustedCompletionTimePolicy::class, BusinessNameCompletionRequestContext::class] as $interface) {
            $this->assertFalse(app()->bound($interface));
        }
        $original = DB::connection()->getRawPdo();
        $this->assertInstanceOf(PDO::class, $original);
        $this->select();
        $fixture = $this->authority->memoryConnection();
        $this->assertTrue($this->authority->authoritative($fixture, $this->descriptor));
        $this->assertSame($original, DB::connection()->getRawPdo());
        $unopened = new SQLiteConnection(fn () => throw new RuntimeException('MUST NOT OPEN'), 'fictional-not-memory');
        $this->assertFalse($this->authority->authoritative($unopened, $this->descriptor));
        DB::connection()->setPdo(fn () => throw new RuntimeException('MUST NOT OPEN'));
        $this->assertNull($this->admission->expectedProfile());
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
        DB::connection()->setPdo($original);
    }

    public function test_unsupported_profile_cannot_be_approved_into_supported_vocabulary(): void
    {
        $bad = $this->descriptor;
        $bad['requested_profile'] = $bad['approved_profile'] = $this->authority->runtime['requested_profile'] = 'unsupported';
        $bad['release_id'] = 'fictional-unsupported';
        $bad['approval_id'] = 'approval-unsupported';
        $this->authority->approve($bad);
        $this->denied(fn () => $this->select($bad));
    }

    public static function malformedArtifacts(): array
    {
        return [['missing-manifest'], ['extra-manifest'], ['uppercase-digest'], ['missing-source'], ['extra-source']];
    }

    #[DataProvider('malformedArtifacts')]
    public function test_partial_or_extra_artifacts_never_supply_source_compatibility(string $case): void
    {
        $bad = $this->descriptor;
        if ($case === 'missing-manifest') {
            unset($bad['manifests']['backend']);
        } elseif ($case === 'extra-manifest') {
            $bad['manifests']['unexpected'] = str_repeat('a', 64);
        } elseif ($case === 'uppercase-digest') {
            $bad['manifests']['backend'] = strtoupper($bad['manifests']['backend']);
        } elseif ($case === 'missing-source') {
            unset($bad['sources']['app/Providers/AppServiceProvider.php']);
        } else {
            $bad['sources']['unexpected.php'] = str_repeat('a', 64);
        }
        $this->denied(fn () => $this->select($bad));
    }

    public function test_fictional_grant_denies_changed_runtime_database_configuration_without_opening_it(): void
    {
        $original = DB::connection()->getRawPdo();
        config(['database.connections.sqlite.database' => 'fictional-forbidden-target']);
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
        $this->assertNull($this->admission->expectedProfile());
        $this->assertSame($original, DB::connection()->getRawPdo());
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_owner_authority_outage_and_session_rotation_do_not_use_old_grant(): void
    {
        $this->admission->ownerAvailable = false;
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
        $this->admission->ownerAvailable = true;
        $this->admission->session = $this->admission->owner['session'] = 'rotated-session';
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), false));
        $this->assertFalse($this->admission->authorized(7, $this->merchant(), $this->expected(), true));
    }
}
