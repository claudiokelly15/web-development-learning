<?php

namespace Tests\Feature;

use App\Actions\Merchants\MerchantApplicationSubmission;
use App\Actions\Merchants\MerchantVerification;
use App\Actions\Merchants\VerificationEnvironment;
use App\Actions\Merchants\VerificationStore;
use App\Models\Merchant;
use App\Models\MerchantApplication;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\B32CRehearsal;
use Tests\Support\FakeMerchantVerificationEnvironment;
use Tests\TestCase;

class MerchantApplicationSubmissionTest extends TestCase
{
    private User $owner;

    private Merchant $merchant;

    private FakeMerchantVerificationEnvironment $environment;

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        Notification::fake();
        $this->environment = new FakeMerchantVerificationEnvironment;
        $this->app->instance(VerificationEnvironment::class, $this->environment);
        $this->owner = User::factory()->create();
        $this->merchant = Merchant::factory()->create(['created_by_user_id' => $this->owner->id]);
    }

    private function signIn(?User $user = null): static
    {
        $user ??= $this->owner;

        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function url(bool $submit = false, ?Merchant $merchant = null): string
    {
        return route($submit ? 'merchants.applications.submit' : 'merchants.applications', ['merchant' => ($merchant ?? $this->merchant)->id]);
    }

    private function input(): array
    {
        $draft = $this->merchant->applications()->where('current_slot', 1)->firstOrFail();

        return ['application_id' => $draft->id, 'profile_version' => $draft->profile_version, 'context_version' => DB::table('merchant_onboarding_contexts')->where('merchant_id', $this->merchant->id)->value('context_version') ?? 0];
    }

    private function fingerprint(): string
    {
        return hash('sha256', json_encode(array_map(fn (string $table): array => DB::select('SELECT * FROM '.$table.' ORDER BY rowid'), ['merchants', 'merchant_applications', 'merchant_onboarding_contexts', 'merchant_identity_records', 'merchant_contact_versions', 'contact_verification_proofs', 'verification_limit_state', 'audit_logs']), JSON_THROW_ON_ERROR));
    }

    private function proof(): void
    {
        $verification = app(MerchantVerification::class);
        $verification->capture($this->owner, $this->merchant, 'contact', ['phone' => '+255712345678', 'context_version' => 0]);
        $verification->capture($this->owner, $this->merchant, 'identity', ['nin' => 'FIXTURE-ABCDEFGH', 'context_version' => 2, 'subject_claim' => 'individual_owner', 'declaration' => true]);
        $challenge = $verification->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1');
        $this->assertTrue($verification->verify($this->owner, $this->merchant, $challenge, $this->environment->lastCode));
    }

    public function test_guests_cannot_access_history_submission_or_review(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));
        $this->post($this->url(true), [])->assertRedirect(route('login'));
        $this->get(route('admin.applications'))->assertRedirect(route('login'));
    }

    public static function nonOwners(): array
    {
        return [['manager', 403], ['staff', 403], ['chief_admin', 404], ['admin', 404]];
    }

    #[DataProvider('nonOwners')]
    public function test_only_fresh_owners_have_history_and_submission_access(string $role, int $status): void
    {
        $actor = User::factory()->create();
        if (in_array($role, ['manager', 'staff'], true)) {
            MerchantMembership::factory()->create(['merchant_id' => $this->merchant->id, 'user_id' => $actor->id, 'role' => $role]);
        } else {
            PlatformRoleAssignment::factory()->create(['user_id' => $actor->id, 'role' => $role]);
        }
        $before = $this->fingerprint();
        $this->signIn($actor)->get($this->url())->assertStatus($status);
        $this->postJson($this->url(true), $this->input())->assertStatus($status);
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_owner_history_is_redacted_and_statuses_match_schema(): void
    {
        foreach (['pending_review', 'information_requested', 'approved', 'rejected'] as $index => $status) {
            MerchantApplication::factory()->create(['merchant_id' => $this->merchant->id, 'attempt_number' => $index + 2, 'status' => $status,
                'profile_snapshot' => ['nin' => 'PRIVATE-SNAPSHOT'], 'internal_review_notes' => 'PRIVATE-NOTES', 'public_feedback' => 'UNREVIEWED-FREE-TEXT']);
        }
        $before = $this->fingerprint();
        $this->signIn()->get($this->url())->assertOk()->assertSee('Draft')->assertSee('Submitted / awaiting review')->assertSee('Additional information required')
            ->assertSee('Approved')->assertSee('Rejected')->assertSee('Submission unavailable')->assertDontSee('PRIVATE-SNAPSHOT')->assertDontSee('PRIVATE-NOTES')->assertDontSee('UNREVIEWED-FREE-TEXT')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_revoked_membership_and_cross_tenant_requests_are_denied(): void
    {
        $foreign = Merchant::factory()->create();
        $this->signIn()->get($this->url(false, $foreign))->assertNotFound();
        $this->postJson($this->url(true, $foreign), $this->input())->assertNotFound();
        $this->merchant->load('memberships');
        DB::table('merchant_memberships')->where('merchant_id', $this->merchant->id)->update(['revoked_at' => now()]);
        $this->get($this->url())->assertForbidden();
        $this->postJson($this->url(true), $this->input())->assertForbidden();
    }

    public function test_stale_account_auth_version_is_denied_at_execution(): void
    {
        $this->signIn();
        DB::table('users')->where('id', $this->owner->id)->increment('auth_version');
        $this->postJson($this->url(true), $this->input())->assertRedirect(route('login'));
        $this->assertContains('Current verified account contact is required.', app(MerchantApplicationSubmission::class)->blockers($this->owner, $this->merchant));
    }

    public static function staleVersions(): array
    {
        return [['application_id'], ['profile_version'], ['context_version']];
    }

    #[DataProvider('staleVersions')]
    public function test_stale_submission_has_no_partial_writes(string $field): void
    {
        $input = $this->input();
        $input[$field] = $field === 'application_id' ? (string) Str::ulid() : $input[$field] + 1;
        $before = $this->fingerprint();
        $this->signIn()->postJson($this->url(true), $input)->assertConflict();
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_simulated_proof_and_duplicate_requests_never_submit_or_consume_evidence(): void
    {
        $this->proof();
        $before = $this->fingerprint();
        $this->signIn();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson($this->url(true), $this->input())->assertStatus(503);
        }
        $this->get($this->url())->assertSee('Simulated proofs cannot authorize submission')->assertDontSee('FIXTURE-ABCDEFGH')->assertDontSee('+255712345678');
        $this->assertNotContains('A current phone possession proof is required.', app(MerchantApplicationSubmission::class)->blockers($this->owner, $this->merchant));
        $this->assertSame('available', DB::table('contact_verification_proofs')->value('state'));
        $this->assertSame('draft', DB::table('merchant_applications')->value('status'));
        $this->assertSame($before, $this->fingerprint());
    }

    public static function invalidProofs(): array
    {
        return [['expired'], ['revoked'], ['replaced']];
    }

    #[DataProvider('invalidProofs')]
    public function test_expired_revoked_and_old_contact_proofs_are_unusable(string $case): void
    {
        $this->proof();
        if ($case === 'expired') {
            $this->environment->time += 1800000;
        } elseif ($case === 'revoked') {
            DB::table('contact_verification_proofs')->update(['state' => 'revoked', 'state_version' => 2, 'revoked_at' => $this->environment->time, 'reason_code' => 'contact_changed']);
        } else {
            app(MerchantVerification::class)->capture($this->owner, $this->merchant, 'contact', ['phone' => '+255712345679', 'context_version' => $this->input()['context_version']]);
        }
        $before = $this->fingerprint();
        $this->signIn()->postJson($this->url(true), $this->input())->assertStatus(503);
        $this->assertSame($before, $this->fingerprint());
        $this->assertContains('A current phone possession proof is required.', app(MerchantApplicationSubmission::class)->blockers($this->owner, $this->merchant));
    }

    public function test_pending_r06_is_reported_and_never_discharged_by_submission(): void
    {
        $verification = app(MerchantVerification::class);
        $verification->capture($this->owner, $this->merchant, 'contact', ['phone' => '+255712345678', 'context_version' => 0]);
        $challenge = $verification->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1');
        DB::connection()->beforeExecuting(function (string $query, array $bindings): void {
            if (str_starts_with($query, 'insert into "audit_logs"') && in_array('otp.changed', $bindings, true)) {
                throw new \RuntimeException('Fictional audit outage');
            }
        });
        $wrong = $this->environment->lastCode === '111111' ? '222222' : '111111';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $verification->verify($this->owner, $this->merchant, $challenge, $wrong);
        }
        $this->assertNotNull(DB::table('verification_limit_state')->value('pending_audit_event_id'));
        $before = $this->fingerprint();
        $this->signIn()->get($this->url())->assertOk()->assertSee('held for audit reconciliation');
        $this->postJson($this->url(true), $this->input())->assertStatus(503);
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_default_dependencies_deny_without_access_to_c_tables(): void
    {
        B32CRehearsal::foundation();
        $this->owner = User::factory()->create();
        $this->merchant = Merchant::factory()->create(['created_by_user_id' => $this->owner->id]);
        $this->app->instance(VerificationEnvironment::class, new VerificationEnvironment);
        $this->signIn()->get($this->url())->assertOk()->assertSee('Private verification services are unavailable');
        $this->postJson($this->url(true), ['application_id' => $this->merchant->applications()->value('id'), 'profile_version' => 1, 'context_version' => 0])->assertStatus(503);
    }

    public function test_chief_queue_is_redacted_staff_is_denied_and_conflicts_are_excluded(): void
    {
        MerchantApplication::factory()->create(['merchant_id' => $this->merchant->id, 'status' => 'pending_review', 'profile_snapshot' => ['nin' => 'PRIVATE-NIN'], 'internal_review_notes' => 'PRIVATE-REVIEW']);
        $chief = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $chief->id, 'role' => 'chief_admin']);
        $own = Merchant::factory()->create(['created_by_user_id' => $chief->id, 'business_name' => 'CONFLICT-MERCHANT']);
        MerchantApplication::factory()->create(['merchant_id' => $own->id, 'status' => 'approved']);
        $linked = Merchant::factory()->create(['business_name' => 'HISTORICAL-MEMBERSHIP-CONFLICT']);
        MerchantMembership::factory()->create(['merchant_id' => $linked->id, 'user_id' => $chief->id, 'role' => 'manager', 'status' => 'revoked', 'revoked_at' => now()]);
        MerchantApplication::factory()->create(['merchant_id' => $linked->id, 'status' => 'pending_review']);
        $before = $this->fingerprint();
        $this->signIn($chief)->get(route('admin.applications'))->assertOk()->assertSee($this->merchant->business_name)->assertSee('Submitted / awaiting review')
            ->assertDontSee('PRIVATE-NIN')->assertDontSee('PRIVATE-REVIEW')->assertDontSee('CONFLICT-MERCHANT')->assertDontSee('HISTORICAL-MEMBERSHIP-CONFLICT')->assertSee('Review actions unavailable');
        $this->assertSame($before, $this->fingerprint());
        DB::table('platform_role_assignments')->where('user_id', $chief->id)->update(['role' => 'admin']);
        $this->get(route('admin.applications'))->assertForbidden();
        $this->get(route('admin.staff'))->assertOk()->assertDontSee(route('admin.applications'), false);
    }

    public function test_chief_revocation_is_fresh_and_review_writes_are_unavailable(): void
    {
        $chief = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $chief->id, 'role' => 'chief_admin']);
        $this->signIn($chief)->get(route('admin.applications'))->assertOk();
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}(route('admin.applications'), [])->assertStatus(405);
        }
        DB::table('platform_role_assignments')->where('user_id', $chief->id)->update(['revoked_at' => now()]);
        $this->get(route('admin.applications'))->assertForbidden();
    }

    public function test_invalid_nested_fields_are_not_flashed_or_rendered_even_in_debug(): void
    {
        config(['app.debug' => true]);
        $before = $this->fingerprint();
        $this->signIn()->post($this->url(true), [...$this->input(), 'nin' => ['secret' => 'PRIVATE-INPUT']])->assertStatus(422)->assertDontSee('PRIVATE-INPUT')->assertSessionMissing('_old_input');
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_existing_submitted_attempt_cannot_be_resubmitted(): void
    {
        $input = $this->input();
        DB::table('merchant_applications')->where('id', $input['application_id'])->update(['status' => 'pending_review', 'submitted_at' => now(), 'submitted_by_user_id' => $this->owner->id]);
        $before = $this->fingerprint();
        $this->signIn()->postJson($this->url(true), $input)->assertConflict();
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_restricted_merchant_and_missing_policy_remain_blocked(): void
    {
        DB::table('merchants')->where('id', $this->merchant->id)->update(['operational_status' => 'suspended']);
        $this->environment->identity = false;
        $before = $this->fingerprint();
        $reasons = app(MerchantApplicationSubmission::class)->blockers($this->owner, $this->merchant);
        $this->assertContains('An available merchant with a business name is required.', $reasons);
        $this->assertContains('A current declaration under the approved identity policy is required.', $reasons);
        $this->signIn()->postJson($this->url(true), $this->input())->assertStatus(503);
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_submission_requires_csrf_and_unsupported_methods_are_denied(): void
    {
        $this->signIn();
        $before = $this->fingerprint();
        foreach (['put', 'patch', 'delete'] as $method) {
            $this->{$method}($this->url(true), $this->input())->assertStatus(405);
        }
        $this->app['env'] = 'local';
        try {
            $this->post($this->url(true), $this->input())->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_competing_submission_writer_is_refused_and_release_still_cannot_submit(): void
    {
        $input = $this->input();
        $path = tempnam(sys_get_temp_dir(), 'xlipa-pd04a-');
        $this->assertNotFalse($path);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($path)));
        unlink($path);
        $other = null;
        try {
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $path, 'database.connections.sqlite.busy_timeout' => 0]);
            DB::statement('PRAGMA recursive_triggers=ON');
            $other = new \PDO('sqlite:'.$path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $other->exec('PRAGMA busy_timeout=0');
            $other->exec('BEGIN IMMEDIATE');
            $before = $this->fingerprint();
            try {
                app(MerchantApplicationSubmission::class)->submit($this->owner, $this->merchant, $input['application_id'], $input['profile_version'], $input['context_version']);
                $this->fail('A competing writer must be refused.');
            } catch (QueryException $error) {
                $this->assertContains($error->errorInfo[1], [5, 6]);
            }
            $other->exec('ROLLBACK');
            try {
                app(MerchantApplicationSubmission::class)->submit($this->owner, $this->merchant, $input['application_id'], $input['profile_version'], $input['context_version']);
            } catch (HttpExceptionInterface $error) {
                $this->assertSame(503, $error->getStatusCode());
            }
            $this->assertSame($before, $this->fingerprint());
            $this->assertSame(0, DB::connection()->transactionLevel());
        } finally {
            $other = null;
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => ':memory:']);
            foreach ([$path, $path.'-journal', $path.'-wal', $path.'-shm'] as $temporary) {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
    }

    public function test_managed_writer_rolls_back_and_nested_submission_cannot_escape(): void
    {
        $before = $this->fingerprint();
        $input = $this->input();
        try {
            app(VerificationStore::class)->run(function () use ($input): void {
                DB::table('merchants')->where('id', $this->merchant->id)->update(['business_name' => 'ROLLBACK-FIXTURE']);
                app(MerchantApplicationSubmission::class)->submit($this->owner, $this->merchant, $input['application_id'], $input['profile_version'], $input['context_version']);
            });
            $this->fail('Nested submission must fail.');
        } catch (\LogicException) {
            $this->assertSame($before, $this->fingerprint());
            $this->assertSame(0, DB::connection()->transactionLevel());
        }
    }
}
