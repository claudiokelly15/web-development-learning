<?php

namespace Tests\Feature;

use App\Actions\Merchants\DeniedSubmissionAdmission;
use App\Actions\Merchants\SubmissionAdmission;
use App\Actions\Merchants\SubmissionEvidenceCipher;
use App\Actions\Merchants\SubmissionTransaction;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ApplicationSubmissionHttpHarness;
use Tests\Support\MerchantOnboardingJourney;
use Tests\TestCase;

class FunctionalMerchantApplicationSubmissionTest extends TestCase
{
    use \Tests\Support\MerchantApplicationSubmissionJourney;

    public function test_same_registered_merchant_completes_the_entire_http_submission_and_chief_queue_journey(): void
    {
        [$owner, $merchant, $application, $reference] = $this->preparedApplication();
        $this->submitApplication($merchant, $reference)->assertOk()->assertExactJson(['status' => 'submitted'])
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee($reference);
        $this->assertSame(1, $this->submission->commandCalls);
        $record = (array) DB::table('merchant_application_submission_evidence')->sole();
        $proof = DB::table('contact_verification_proofs')->sole();
        $application->refresh();
        $this->assertSame($merchant->id, $record['merchant_id']);
        $this->assertSame($application->id, $record['application_id']);
        $this->assertSame($owner->id, $record['actor_user_id']);
        $this->assertSame($merchant->memberships()->where('user_id', $owner->id)->sole()->id, $record['membership_id']);
        $this->assertSame(1, $record['attempt_number']);
        $this->assertSame(2, $record['email_binding_version']);
        $this->assertSame(2, $record['profile_version']);
        $this->assertSame('pending_review', $application->status->value);
        $this->assertSame($owner->id, $application->submitted_by_user_id);
        $this->assertSame($record['submitted_at'], $application->getRawOriginal('submitted_at'));
        $this->assertNull($application->profile_snapshot);
        $this->assertSame('consumed', $proof->state);
        $this->assertSame($application->id, $proof->consumed_application_id);
        $this->assertSame($record['submitted_at_ms'], $proof->consumed_at);
        $this->assertSame(1, DB::table('audit_logs')->where('id', $record['audit_event_id'])->where('application_id', $application->id)->where('merchant_id', $merchant->id)->where('event_type', 'application.submitted')->count());
        $cipher = new SubmissionEvidenceCipher($this->submission->custody, $this->submission->verification);
        $payload = $cipher->decrypt(array_intersect_key($record, array_flip(SubmissionEvidenceCipher::FIELDS)), $record['evidence_ciphertext']);
        $this->assertSame($owner->email, $payload['email']);
        $this->assertSame('Kijani Studio', $payload['business_name']);
        $this->assertSame('TZ', $payload['market']);
        $this->assertSame('TZS', $payload['currency']);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(1, DB::table('merchant_business_name_completions')->where('merchant_id', $merchant->id)->count());
        $this->assertInstanceOf(DeniedSubmissionAdmission::class, app(SubmissionAdmission::class));
        $this->submitApplication($merchant, $reference)->assertStatus(409);
        $this->assertSame($record, (array) DB::table('merchant_application_submission_evidence')->sole());
        $this->assertSame(1, $this->submission->commandCalls);
        $this->post(route('merchants.applications.submit', ['merchant' => $merchant->id]), ['_token' => session()->token(), 'application_id' => $application->id, 'profile_version' => 2, 'context_version' => 1])->assertStatus(503);
        $this->get(route('admin.applications'))->assertForbidden();
        $chief = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $chief->id, 'role' => 'chief_admin']);
        $this->signIn($chief);
        $this->get(route('admin.applications', ['status' => 'pending_review']))->assertOk()->assertSee('Kijani Studio')
            ->assertViewHas('applications', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $application->id);
        $detail = route('admin.applications.show', ['application' => $application->id]);
        $this->get($detail)->assertOk()->assertSee('Evidence recorded; selected application and audit references agree')->assertSee('Submission recorded')
            ->assertDontSee('FIXTURE-ABCDEFGH')->assertDontSee('+255712345678')->assertDontSee($owner->email)->assertDontSee($record['submission_request_id']);
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}($detail, ['_token' => session()->token(), 'status' => 'approved'])->assertStatus(405);
        }
        $this->assertSame($record, (array) DB::table('merchant_application_submission_evidence')->sole());
        $this->assertSame('pending_review', $application->fresh()->status->value);
        $this->signIn(User::factory()->create());
        $this->get($detail)->assertForbidden();
        $this->get(route('merchants.applications', ['merchant' => $merchant->id]))->assertNotFound();
    }

    public static function deniedActors(): array
    {
        return [['guest', 401], ['unverified', 403], ['manager', 403], ['staff', 403], ['foreign', 404], ['chief', 404], ['revoked', 404], ['stale-session', 302], ['suspended-account', 302]];
    }

    #[DataProvider('deniedActors')]
    public function test_submission_requires_a_current_verified_owner(string $case, int $status): void
    {
        [$owner, $merchant, , $reference] = $this->preparedApplication();
        if ($case === 'guest') {
            auth()->logout();
        } elseif ($case === 'unverified') {
            $this->signIn(User::factory()->unverified()->create());
        } elseif (in_array($case, ['manager', 'staff', 'foreign', 'chief'], true)) {
            $other = User::factory()->create();
            if (in_array($case, ['manager', 'staff'], true)) {
                MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $other->id, 'role' => $case]);
            }
            if ($case === 'chief') {
                PlatformRoleAssignment::factory()->create(['user_id' => $other->id, 'role' => 'chief_admin']);
            }
            $this->signIn($other);
        } elseif ($case === 'revoked') {
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['status' => 'revoked', 'revoked_at' => '2026-11-14 00:00:00', 'authority_version' => 2]);
        } elseif ($case === 'stale-session') {
            $this->withSession(['auth_version' => 999]);
        } else {
            $this->signIn(User::factory()->create(['account_status' => 'suspended']));
        }
        $this->submitApplication($merchant, $reference)->assertStatus($status)->assertDontSee($reference);
        $this->assertSame(0, $this->submission->commandCalls);
        $this->assertUnsubmitted();
    }

    public static function staleEvidence(): array
    {
        return [['expired', 409], ['revoked-proof', 409], ['authority-version', 409], ['context-version', 409], ['issuer', 503], ['admission', 503], ['retention', 503], ['simulated', 503]];
    }

    #[DataProvider('staleEvidence')]
    public function test_real_service_rejects_stale_or_untrusted_prepared_evidence(string $case, int $status): void
    {
        [, $merchant, , $reference] = $this->preparedApplication();
        if ($case === 'expired') {
            $time = DB::table('contact_verification_proofs')->value('expires_at');
            $this->submission->admission->time = $this->submission->custody->time = $this->submission->verification->time = $time;
        } elseif ($case === 'revoked-proof') {
            DB::table('contact_verification_proofs')->update(['state' => 'revoked', 'state_version' => 2, 'revoked_at' => 1794614400050, 'reason_code' => 'operator_revoked']);
        } elseif ($case === 'authority-version') {
            DB::table('merchant_memberships')->update(['role' => 'manager', 'authority_version' => 2]);
            DB::table('merchant_memberships')->update(['role' => 'owner', 'authority_version' => 3]);
        } elseif ($case === 'context-version') {
            DB::table('merchant_onboarding_contexts')->update(['context_version' => 2]);
        } elseif ($case === 'issuer') {
            $this->submission->admission->issuer = false;
        } elseif ($case === 'admission') {
            $this->submission->admission->enabled = false;
        } elseif ($case === 'retention') {
            $this->submission->admission->retention = false;
        } else {
            $this->submission->verification->transport = 'simulated';
        }
        $this->submitApplication($merchant, $reference)->assertStatus($status)->assertExactJson(['status' => 'unresolved']);
        $this->assertSame(1, $this->submission->commandCalls);
        $this->assertUnsubmitted();
    }

    public function test_context_cannot_cross_tenants_sessions_or_accept_client_expectations_and_csrf_runs(): void
    {
        [$owner, $merchant, , $reference] = $this->preparedApplication();
        $foreign = Merchant::factory()->create(['created_by_user_id' => $owner->id]);
        $before = $foreign->fresh()->getAttributes();
        $this->submitApplication($foreign, $reference)->assertStatus(409);
        $this->submitApplication($merchant, $reference, ['profile_version' => 999])->assertStatus(422);
        $this->submitApplication($merchant, $reference, ['_token' => 'wrong'])->assertStatus(419);
        $session = session()->getId();
        session()->setId(str_repeat('z', 40));
        $this->submitApplication($merchant, $reference)->assertStatus(409);
        session()->setId($session);
        $this->assertSame(0, $this->submission->commandCalls);
        $this->submitApplication($merchant, $reference)->assertOk();
        $this->assertSame($before, $foreign->fresh()->getAttributes());
        $this->assertSame(0, DB::table('merchant_application_submission_evidence')->where('merchant_id', $foreign->id)->count());
    }

    public static function unknownCommits(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('unknownCommits')]
    public function test_uncertain_submission_is_reported_without_replay(bool $committed): void
    {
        [, $merchant, , $reference] = $this->preparedApplication();
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
                throw new RuntimeException('PRIVATE fictional acknowledgement loss');
            }
        };
        app()->instance(SubmissionTransaction::class, $transaction);
        $this->submitApplication($merchant, $reference)->assertStatus(202)->assertExactJson(['status' => 'unresolved'])->assertDontSee('PRIVATE');
        $this->assertSame($committed ? 1 : 0, (int) $this->ownedPdo->query('SELECT count(*) FROM merchant_application_submission_evidence')->fetchColumn());
        $this->assertSame($committed ? 'pending_review' : 'draft', $this->ownedPdo->query('SELECT status FROM merchant_applications')->fetchColumn());
        DB::connection()->setPdo($this->ownedPdo);
        $this->submitApplication($merchant, $reference)->assertStatus(409);
        $this->assertSame(1, $transaction->attempts);
        $this->assertSame(1, $this->submission->commandCalls);
    }

    public function test_submission_evidence_is_immutable_and_default_application_has_no_fixture_route(): void
    {
        [, $merchant, , $reference] = $this->preparedApplication();
        $this->submitApplication($merchant, $reference)->assertOk();
        $record = (array) DB::table('merchant_application_submission_evidence')->sole();
        try {
            DB::table('merchant_application_submission_evidence')->update(['submission_request_id' => '00000000-0000-0000-0000-000000000000']);
            $this->fail('Frozen evidence must reject mutation.');
        } catch (QueryException) {
            $this->assertSame($record, (array) DB::table('merchant_application_submission_evidence')->sole());
        }
        $ordinary = $this->createApplication();
        $this->assertInstanceOf(DeniedSubmissionAdmission::class, $ordinary->make(SubmissionAdmission::class));
        foreach ($ordinary->make('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('__isolated-submission', $route->uri());
        }
    }
}
