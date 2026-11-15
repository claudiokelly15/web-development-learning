<?php

namespace Tests\Feature;

use App\Actions\Merchants\ApplicationDecisionAdmission;
use App\Actions\Merchants\SubmissionTransaction;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\MerchantDecisionJourney;
use Tests\TestCase;

class FunctionalChiefAdminDecisionTest extends TestCase
{
    use MerchantDecisionJourney;

    public static function outcomes(): array
    {
        return [['approved', null], ['rejected', 'requirements_not_met']];
    }

    #[DataProvider('outcomes')]
    public function test_same_p2_application_is_decided_atomically_and_visible_to_owner_and_chief(string $outcome, ?string $reason): void
    {
        [$owner, $merchant, $application] = $this->submitted();
        $merchantBefore = $merchant->fresh()->getAttributes();
        $evidence = (array) DB::table('merchant_application_submission_evidence')->sole();
        $chief = $this->chief();
        $detail = route('admin.applications.show', ['application' => $application->id]);
        $this->get($detail)->assertOk()->assertSee('Submission recorded');
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), $outcome, $reason);
        $this->requestDecision($merchant, $application->id, $ref)->assertOk()->assertExactJson(['status' => $outcome])->assertDontSee($ref)
            ->assertHeader('Cache-Control', 'no-store, private');
        $application->refresh();
        $audit = (array) DB::table('audit_logs')->where('event_type', 'application.reviewed')->sole();
        $this->assertSame($outcome, $application->status->value);
        $this->assertSame($chief->id, $application->reviewed_by_user_id);
        $this->assertSame($audit['occurred_at'], $application->getRawOriginal('reviewed_at'));
        $this->assertSame($merchant->id, $audit['merchant_id']);
        $this->assertSame($application->id, $audit['application_id']);
        $this->assertSame($chief->id, $audit['actor_user_id']);
        $this->assertNull($audit['target_user_id']);
        $this->assertSame(['from' => 'pending_review', 'to' => $outcome, 'profile_version' => 2, 'reason_code' => $reason ?? 'requirements_satisfied'], json_decode($audit['metadata'], true));
        $this->assertSame($evidence, (array) DB::table('merchant_application_submission_evidence')->sole());
        $this->assertSame($merchantBefore, $merchant->fresh()->getAttributes());
        $this->assertSame('onboarding', $merchant->fresh()->operational_status->value);
        $this->assertSame('unverified', $merchant->fresh()->verification_status->value);
        $this->assertSame(WorkflowSchemaReadiness::BNC, app(WorkflowSchemaReadiness::class)->profile());
        $this->get(route('admin.applications', ['status' => $outcome]))->assertOk()->assertViewHas('applications', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $application->id);
        $this->get($detail)->assertOk()->assertSee('Review recorded')->assertDontSee('FIXTURE-ABCDEFGH')->assertDontSee('+255712345678')->assertDontSee($owner->email);
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus(409);
        $opposite = $outcome === 'approved' ? 'rejected' : 'approved';
        $other = $this->decisions->prepare($chief, $application, session()->getId(), $opposite, $opposite === 'rejected' ? 'requirements_not_met' : null);
        $this->requestDecision($merchant, $application->id, $other)->assertStatus(409);
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}($detail, ['_token' => session()->token(), 'status' => $opposite])->assertStatus(405);
        }
        $this->signIn($owner);
        $this->get(route('merchants.applications', ['merchant' => $merchant->id]))->assertOk()->assertSee(ucfirst($outcome));
        $this->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertOk()->assertSee('Financial services')->assertSee('Unavailable');
        $this->assertSame($audit, (array) DB::table('audit_logs')->where('event_type', 'application.reviewed')->sole());
        try {
            DB::table('audit_logs')->where('id', $audit['id'])->update(['metadata' => '{}']);
            $this->fail('Decision history must be immutable.');
        } catch (QueryException) {
            $this->assertSame($audit, (array) DB::table('audit_logs')->where('id', $audit['id'])->sole());
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public static function invalidInputs(): array
    {
        return [['rejected', null], ['rejected', ''], ['rejected', str_repeat('x', 51)], ['rejected', '<private>'], ['approved', 'unexpected'], ['information_requested', null]];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_outcome_and_rejection_reason_cannot_write(string $outcome, ?string $reason): void
    {
        [, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), $outcome, $reason);
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus(422)->assertDontSee('<private>');
        $this->assertHeld($application->id);
    }

    public static function unauthorizedActors(): array
    {
        return [['owner', 403], ['owner-chief', 404], ['manager', 403], ['merchant-staff', 403], ['platform-staff', 403], ['revoked', 403], ['suspended', 302], ['stale-session', 302], ['unverified', 403]];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_current_independent_chief_authority_is_required(string $case, int $status): void
    {
        [$owner, $merchant, $application] = $this->submitted();
        if (in_array($case, ['owner', 'owner-chief'], true)) {
            $actor = $owner;
        } else {
            $actor = User::factory()->create(['account_status' => $case === 'suspended' ? 'suspended' : 'active', 'email_verified_at' => $case === 'unverified' ? null : now()]);
        }
        if (in_array($case, ['manager', 'merchant-staff'], true)) {
            MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $actor->id, 'role' => $case === 'manager' ? 'manager' : 'staff']);
        }
        if (in_array($case, ['owner-chief', 'platform-staff', 'revoked', 'suspended', 'stale-session', 'unverified'], true)) {
            PlatformRoleAssignment::factory()->create(['user_id' => $actor->id, 'role' => $case === 'platform-staff' ? 'admin' : 'chief_admin', 'revoked_at' => $case === 'revoked' ? now() : null]);
        }
        $actor = $actor->fresh();
        $this->signIn($actor);
        $ref = $this->decisions->prepare($actor, $application, session()->getId(), 'approved');
        if ($case === 'stale-session') {
            $this->withSession(['auth_version' => 999]);
        }
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus($status);
        $this->assertHeld($application->id);
    }

    public static function staleExpectations(): array
    {
        return [['attempt_number', 2, 409], ['profile_version', 3, 409], ['reviewer_auth_version', 2, 403], ['reviewer_authority_version', 2, 503], ['reviewer_email_binding_version', 2, 403], ['profile_version', '2', 422], ['submission_request_id', '00000000-0000-0000-0000-000000000000', 409]];
    }

    #[DataProvider('staleExpectations')]
    public function test_stale_or_coerced_expectations_are_rejected(string $field, mixed $value, int $status): void
    {
        [, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), 'approved', overrides: [$field => $value]);
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus($status);
        $this->assertHeld($application->id);
    }

    public function test_competing_prepared_decisions_have_one_winner_and_no_duplicate_audit(): void
    {
        [, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $first = $this->decisions->prepare($chief, $application, session()->getId(), 'approved');
        $second = $this->decisions->prepare($chief, $application, session()->getId(), 'rejected', 'requirements_not_met');
        $this->requestDecision($merchant, $application->id, $first)->assertOk();
        $this->requestDecision($merchant, $application->id, $second)->assertStatus(409);
        $this->assertSame('approved', $application->fresh()->status->value);
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'application.reviewed')->count());
    }

    public function test_foreign_scope_csrf_and_client_claims_cannot_dispatch(): void
    {
        [, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), 'approved');
        $foreign = Merchant::factory()->create();
        $this->requestDecision($foreign, $application->id, $ref)->assertStatus(409);
        $this->requestDecision($merchant, '01ARZ3NDEKTSV4RRFFQ69G5FAV', $ref)->assertStatus(409);
        $this->requestDecision($merchant, $application->id, $ref, ['_token' => 'wrong'])->assertStatus(419);
        $this->requestDecision($merchant, $application->id, $ref, ['allow' => true])->assertStatus(422);
        $this->assertSame(0, $this->decisions->calls);
        $mismatched = clone $application;
        $mismatched->setAttribute('merchant_id', $foreign->id);
        $foreignRef = $this->decisions->prepare($chief, $mismatched, session()->getId(), 'approved');
        $this->requestDecision($foreign, $application->id, $foreignRef)->assertNotFound()->assertDontSee('Kijani Studio');
        $this->assertSame(1, $this->decisions->calls);
        $this->assertHeld($application->id);
    }

    public static function evidenceHolds(): array
    {
        return [['revoked-proof', 409], ['issuer', 503], ['custody', 503], ['admission', 503], ['default-admission', 503], ['authority-regrant', 503]];
    }

    #[DataProvider('evidenceHolds')]
    public function test_current_evidence_and_independent_write_admission_remain_required(string $case, int $status): void
    {
        [, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), 'approved');
        if ($case === 'revoked-proof') {
            DB::table('contact_verification_proofs')->update(['state' => 'revoked', 'state_version' => 3, 'revoked_at' => 1794614400200, 'reason_code' => 'operator_revoked']);
        } elseif ($case === 'issuer') {
            $this->submission->admission->issuer = false;
        } elseif ($case === 'custody') {
            $this->submission->custody->enabled = false;
        } elseif ($case === 'admission') {
            $this->decisions->admission->allow = false;
        } elseif ($case === 'default-admission') {
            $this->decisions->useFictionalAdmission = false;
        } else {
            $this->decisions->admission->version++;
        }
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus($status);
        $this->assertHeld($application->id);
    }

    public function test_natural_proof_expiry_preserves_historical_submission_and_requires_decision_admission(): void
    {
        [, $merchant, $application] = $this->submitted();
        $record = (array) DB::table('merchant_application_submission_evidence')->sole();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), 'approved');
        $time = DB::table('contact_verification_proofs')->value('expires_at') + 1;
        $this->decisions->admission->time = $this->submission->custody->time = $this->submission->verification->time = $time;
        $this->requestDecision($merchant, $application->id, $ref)->assertOk()->assertExactJson(['status' => 'approved']);
        $this->assertSame($record, (array) DB::table('merchant_application_submission_evidence')->sole());
        $this->assertSame('consumed', DB::table('contact_verification_proofs')->value('state'));
    }

    public static function failurePoints(): array
    {
        return [['audit', 503], ['application', 503], ['final-authority', 403]];
    }

    #[DataProvider('failurePoints')]
    public function test_late_failures_roll_back_the_decision_and_leave_submission_intact(string $point, int $status): void
    {
        [, $merchant, $application] = $this->submitted();
        $before = (array) DB::table('merchant_application_submission_evidence')->sole();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), 'approved');
        DB::listen(function ($query) use ($point, $chief): void {
            if ($point === 'audit' && str_starts_with($query->sql, 'insert into "audit_logs"') && in_array('application.reviewed', $query->bindings, true)) {
                throw new RuntimeException('PRIVATE audit outage');
            }
            if (str_starts_with($query->sql, 'update "merchant_applications"')) {
                if ($point === 'application') {
                    throw new RuntimeException('PRIVATE state acknowledgement loss');
                }
                if ($point === 'final-authority') {
                    DB::table('platform_role_assignments')->where('user_id', $chief->id)->update(['revoked_at' => now()]);
                }
            }
        });
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus($status)->assertDontSee('PRIVATE');
        $this->assertHeld($application->id);
        $this->assertSame($before, (array) DB::table('merchant_application_submission_evidence')->sole());
    }

    public function test_role_revocation_at_the_writer_boundary_denies_after_http_authentication(): void
    {
        [, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), 'approved');
        app()->instance(SubmissionTransaction::class, new class($chief->id) extends SubmissionTransaction
        {
            public function __construct(private readonly int $chief) {}

            public function run(Closure $operation): mixed
            {
                DB::table('platform_role_assignments')->where('user_id', $this->chief)->update(['revoked_at' => now()]);

                return parent::run($operation);
            }
        });
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus(403);
        $this->assertHeld($application->id);
    }

    public static function uncertainCommits(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('uncertainCommits')]
    public function test_unknown_commit_holds_all_prepared_requests_without_retry(bool $committed): void
    {
        [, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), 'approved');
        $other = $this->decisions->prepare($chief, $application, session()->getId(), 'rejected', 'requirements_not_met');
        $transaction = new class($committed) extends SubmissionTransaction
        {
            public int $attempts = 0;

            public function __construct(private readonly bool $committed) {}

            protected function commit(Connection $connection): void
            {
                $this->attempts++;
                if ($this->committed) {
                    parent::commit($connection);
                }
                throw new RuntimeException('PRIVATE commit acknowledgement loss');
            }
        };
        app()->instance(SubmissionTransaction::class, $transaction);
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus(202)->assertExactJson(['status' => 'unresolved']);
        $this->assertSame($committed ? 'approved' : 'pending_review', $this->ownedPdo->query('SELECT status FROM merchant_applications')->fetchColumn());
        $this->assertSame($committed ? 1 : 0, (int) $this->ownedPdo->query("SELECT count(*) FROM audit_logs WHERE event_type='application.reviewed'")->fetchColumn());
        DB::connection()->setPdo($this->ownedPdo);
        $this->requestDecision($merchant, $application->id, $ref)->assertStatus(409);
        $this->requestDecision($merchant, $application->id, $other)->assertStatus(409);
        $this->assertSame(1, $transaction->attempts);
        $this->assertSame(1, $this->decisions->calls);
    }

    public function test_ordinary_runtime_has_denied_admission_and_no_fixture_routes(): void
    {
        $ordinary = $this->createApplication();
        $this->assertFalse($ordinary->make(ApplicationDecisionAdmission::class)->authorized(1, 'merchant', 'application', [], 'approved', null));
        foreach ($ordinary->make('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('__isolated-decisions', $route->uri());
        }
    }
}
