<?php

namespace Tests\Feature;

use App\Http\Controllers\MerchantOnboardingController;
use App\Models\Merchant;
use App\Models\MerchantApplication;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\ViewModels\MerchantOnboardingSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

class MerchantAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new LogicException('Merchant access tests require disposable in-memory SQLite.');
        }
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        B32CRehearsal::foundation();
        Notification::fake();
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function statusUrl(Merchant $merchant): string
    {
        return route('merchants.onboarding', ['merchant' => $merchant->id]);
    }

    private function databaseFingerprint(): string
    {
        $records = [];
        foreach (['users', 'merchants', 'merchant_memberships', 'merchant_applications', 'audit_logs', 'platform_role_assignments', 'password_reset_tokens', 'sessions'] as $table) {
            $records[$table] = DB::select('SELECT * FROM '.$table.' ORDER BY rowid');
        }

        return hash('sha256', json_encode($records, JSON_THROW_ON_ERROR));
    }

    public function test_guests_and_unverified_users_cannot_read_status(): void
    {
        $merchant = Merchant::factory()->create();
        $this->get($this->statusUrl($merchant))->assertRedirect(route('login'));
        $user = $merchant->creator;
        $user->forceFill(['email_verified_at' => null])->save();
        $this->signIn($user)->get($this->statusUrl($merchant))->assertRedirect(route('verification.notice'));
        $this->assertFalse(Gate::forUser($user)->allows('viewSummary', $merchant));
    }

    /** @return array<string, array{string}> */
    public static function summaryRoles(): array
    {
        return ['owner' => ['owner'], 'manager' => ['manager'], 'staff' => ['staff']];
    }

    #[DataProvider('summaryRoles')]
    public function test_role_scoped_summary_contains_only_approved_information(string $role): void
    {
        $merchant = Merchant::factory()->create(['business_email' => 'private-contact@example.test', 'address_line1' => 'PRIVATE BUSINESS ADDRESS']);
        $user = $role === 'owner' ? $merchant->creator : User::factory()->create();
        if ($role !== 'owner') {
            MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'role' => $role]);
        }
        $application = $merchant->applications()->sole();
        $application->forceFill(['public_feedback' => 'PUBLIC OWNER MANAGER FEEDBACK', 'internal_review_notes' => 'PRIVATE ADMIN NOTES', 'profile_snapshot' => ['private' => 'PRIVATE SNAPSHOT']])->save();
        $response = $this->signIn($user)->get($this->statusUrl($merchant));
        $response->assertOk()->assertSee('Draft')->assertSee('unverified')->assertDontSee('PRIVATE ADMIN NOTES')->assertDontSee('PRIVATE SNAPSHOT')
            ->assertDontSee('PRIVATE BUSINESS ADDRESS')->assertDontSee('private-contact@example.test')->assertViewHas('summary', fn ($summary): bool => $summary instanceof MerchantOnboardingSummary);
        $this->assertTrue(Gate::forUser($user)->allows('viewSummary', $merchant));
        $this->assertSame($role !== 'staff', Gate::forUser($user)->allows('view', [$application, $merchant]));
        if ($role === 'staff') {
            $response->assertDontSee('PUBLIC OWNER MANAGER FEEDBACK')->assertDontSee('Attempt number')->assertDontSee('Submitted at');
        } else {
            $response->assertSee('PUBLIC OWNER MANAGER FEEDBACK')->assertSee('Attempt number');
        }
        $this->assertFalse(Gate::forUser($user)->allows('updateProfile', $merchant));
        $this->assertFalse(Gate::forUser($user)->allows('submit', [$application, $merchant]));
    }

    public function test_nonmembers_and_cross_merchant_access_receive_not_found(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => 'OTHER PRIVATE BUSINESS']);
        $this->signIn(User::factory()->create())->get($this->statusUrl($merchant))->assertNotFound()->assertDontSee('OTHER PRIVATE BUSINESS');
        $own = Merchant::factory()->create();
        $this->signIn($own->creator)->get($this->statusUrl($merchant))->assertNotFound();
        $this->assertFalse(Gate::forUser($own->creator)->allows('viewSummary', $merchant));
    }

    public function test_membership_revocation_is_fresh_and_creator_does_not_bypass_it(): void
    {
        $merchant = Merchant::factory()->create();
        $user = $merchant->creator;
        $this->signIn($user)->get($this->statusUrl($merchant))->assertOk();
        $user->load('merchantMemberships');
        DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->get($this->statusUrl($merchant))->assertNotFound();
        $this->assertFalse(Gate::forUser($user)->allows('viewSummary', $merchant));
        $this->assertFalse(Gate::forUser($user)->allows('view', [$merchant->applications()->sole(), $merchant]));
    }

    public function test_role_downgrade_removes_application_details_on_the_next_request(): void
    {
        $merchant = Merchant::factory()->create();
        $manager = User::factory()->create();
        $membership = MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $manager->id, 'role' => 'manager']);
        $merchant->applications()->sole()->forceFill(['public_feedback' => 'OWNER MANAGER ONLY FEEDBACK'])->save();
        $this->signIn($manager)->get($this->statusUrl($merchant))->assertOk()->assertSee('OWNER MANAGER ONLY FEEDBACK');
        $manager->load('merchantMemberships');
        $membership->forceFill(['role' => 'staff'])->save();
        $this->get($this->statusUrl($merchant))->assertOk()->assertSee('Draft')->assertDontSee('OWNER MANAGER ONLY FEEDBACK')->assertDontSee('Attempt number');
    }

    public function test_account_status_and_auth_version_are_enforced_before_context_resolution(): void
    {
        $merchant = Merchant::factory()->create();
        $user = $merchant->creator;
        foreach (['suspended', 'closed'] as $status) {
            DB::table('users')->where('id', $user->id)->update(['account_status' => $status]);
            $this->signIn($user)->get($this->statusUrl($merchant))->assertRedirect(route('login'));
            $this->assertFalse(Gate::forUser($user)->allows('viewSummary', $merchant));
        }
        DB::table('users')->where('id', $user->id)->update(['account_status' => 'active', 'auth_version' => 2]);
        $this->signIn($user)->get($this->statusUrl($merchant))->assertRedirect(route('login'));
        $this->actingAs($user->fresh())->withSession(['auth_version' => null])->get($this->statusUrl($merchant))->assertRedirect(route('login'));
    }

    public function test_missing_and_invalid_merchants_are_handled_safely(): void
    {
        $this->signIn(User::factory()->create());
        $this->get('/merchants/'.Str::ulid().'/onboarding')->assertNotFound();
        $this->get('/merchants/not-a-merchant/onboarding')->assertNotFound();
        $this->get('/merchants/onboarding')->assertNotFound();
    }

    public function test_multiple_memberships_require_explicit_selection_and_ignore_forged_context(): void
    {
        $user = User::factory()->create();
        $first = Merchant::factory()->create(['created_by_user_id' => $user->id, 'business_name' => 'FIRST OWN BUSINESS']);
        $second = Merchant::factory()->create(['created_by_user_id' => $user->id, 'business_name' => 'SECOND OWN BUSINESS']);
        $other = Merchant::factory()->create(['business_name' => 'OTHER PRIVATE BUSINESS']);
        $this->signIn($user)->get('/account')->assertOk()->assertSee($this->statusUrl($first), false)->assertSee($this->statusUrl($second), false)->assertDontSee('OTHER PRIVATE BUSINESS');
        $this->withSession(['selected_merchant_id' => $other->id])->get($this->statusUrl($first).'?merchant_id='.$other->id.'&role=chief_admin&application_id='.$other->applications()->sole()->id)
            ->assertOk()->assertSee('FIRST OWN BUSINESS')->assertDontSee('SECOND OWN BUSINESS')->assertDontSee('OTHER PRIVATE BUSINESS');
        $this->get($this->statusUrl($second))->assertOk()->assertSee('SECOND OWN BUSINESS')->assertDontSee('FIRST OWN BUSINESS');
        $this->get($this->statusUrl($other).'?merchant_id='.$first->id)->assertNotFound();
        $this->get('/merchants')->assertNotFound();
    }

    public function test_empty_selector_does_not_create_records(): void
    {
        $user = User::factory()->create();
        $before = $this->databaseFingerprint();
        $this->signIn($user)->get('/account')->assertOk()->assertSee('No active merchant memberships.');
        $this->assertSame($before, $this->databaseFingerprint());
    }

    /** @return array<string, array{string}> */
    public static function platformRoles(): array
    {
        return ['admin' => ['admin'], 'chief' => ['chief_admin']];
    }

    #[DataProvider('platformRoles')]
    public function test_platform_roles_do_not_grant_merchant_access(string $role): void
    {
        $merchant = Merchant::factory()->create();
        $user = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $user->id, 'role' => $role]);
        $this->signIn($user)->get($this->statusUrl($merchant))->assertNotFound();
        $this->assertFalse(Gate::forUser($user)->allows('viewSummary', $merchant));
        $this->assertFalse(Gate::forUser($user)->allows('view', [$merchant->applications()->sole(), $merchant]));
        $this->signIn($merchant->creator)->get('/admin')->assertNotFound();
        $this->assertSame(1, PlatformRoleAssignment::count());
    }

    public function test_application_policy_rejects_mismatched_tenants_and_historical_attempts(): void
    {
        $first = Merchant::factory()->create();
        $second = Merchant::factory()->create();
        $foreign = $second->applications()->sole();
        $historical = MerchantApplication::factory()->create(['merchant_id' => $first->id, 'public_feedback' => 'PRIVATE HISTORICAL FEEDBACK']);
        $user = $first->creator;
        $this->assertFalse(Gate::forUser($user)->allows('view', [$foreign, $first]));
        $this->assertFalse(Gate::forUser($user)->allows('view', [$foreign, $second]));
        $this->assertFalse(Gate::forUser($user)->allows('view', [$historical, $first]));
        $this->signIn($user)->get($this->statusUrl($first))->assertOk()->assertDontSee('PRIVATE HISTORICAL FEEDBACK');
        $this->get('/merchants/'.$first->id.'/applications/'.$foreign->id)->assertNotFound();
    }

    /** @return array<string, array{string, string}> */
    public static function applicationStates(): array
    {
        return ['draft' => ['draft', 'Draft'], 'pending' => ['pending_review', 'Awaiting review'], 'information' => ['information_requested', 'More information required'], 'approved' => ['approved', 'Approved'], 'rejected' => ['rejected', 'Rejected']];
    }

    #[DataProvider('applicationStates')]
    public function test_existing_application_states_are_read_without_mutation(string $state, string $label): void
    {
        $merchant = Merchant::factory()->create();
        $application = $merchant->applications()->sole();
        $application->forceFill(['status' => $state, 'submitted_at' => $state === 'draft' ? null : now(), 'public_feedback' => '<script>public-feedback</script>'])->save();
        $before = $this->databaseFingerprint();
        $this->signIn($merchant->creator)->get($this->statusUrl($merchant))->assertOk()->assertSee($label)
            ->assertSee('&lt;script&gt;public-feedback&lt;/script&gt;', false)->assertDontSee('<script>public-feedback</script>', false);
        $this->assertSame($before, $this->databaseFingerprint());
        Notification::assertNothingSent();
    }

    public function test_missing_current_application_does_not_create_a_draft(): void
    {
        $merchant = Merchant::factory()->create();
        DB::table('merchant_applications')->where('merchant_id', $merchant->id)->update(['current_slot' => null]);
        $before = $this->databaseFingerprint();
        $this->signIn($merchant->creator)->get($this->statusUrl($merchant))->assertOk()->assertSee('No current application is available.');
        $this->assertSame($before, $this->databaseFingerprint());
    }

    public function test_suspended_and_closed_merchants_remain_read_only_without_status_reset(): void
    {
        $merchant = Merchant::factory()->create();
        foreach (['suspended', 'closed'] as $state) {
            $merchant->forceFill(['operational_status' => $state])->save();
            $before = $this->databaseFingerprint();
            $this->signIn($merchant->creator)->get($this->statusUrl($merchant))->assertOk()->assertSee($state);
            $this->assertSame($before, $this->databaseFingerprint());
        }
    }

    public function test_read_only_route_has_no_write_methods_or_dead_feature_links(): void
    {
        $merchant = Merchant::factory()->create();
        $this->signIn($merchant->creator)->get($this->statusUrl($merchant))->assertOk()->assertDontSee('/profile/edit')->assertDontSee('/submit')->assertDontSee('/admin/');
        $before = $this->databaseFingerprint();
        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $this->{$method}($this->statusUrl($merchant), ['status' => 'approved', 'role' => 'chief_admin'])->assertStatus(405);
        }
        $this->assertSame($before, $this->databaseFingerprint());
    }

    public function test_privacy_headers_cover_success_denials_invalid_routes_and_method_errors(): void
    {
        $merchant = Merchant::factory()->create();
        $this->signIn($merchant->creator);
        $responses = [$this->get($this->statusUrl($merchant)), $this->get('/merchants/'.Str::ulid().'/onboarding'), $this->get('/merchants/invalid/onboarding'), $this->post($this->statusUrl($merchant))];
        foreach ($responses as $response) {
            $response->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
        }
    }

    public function test_unexpected_merchant_errors_are_generic_and_logged_without_sensitive_details(): void
    {
        $merchant = Merchant::factory()->create();
        $before = $this->databaseFingerprint();
        config(['app.debug' => false]);
        Log::spy();
        $this->mock(MerchantOnboardingController::class)->shouldReceive('show')->once()->andThrow(new RuntimeException('PRIVATE EXCEPTION PAYLOAD'));
        $this->signIn($merchant->creator)->get($this->statusUrl($merchant))->assertStatus(500)->assertDontSee('PRIVATE EXCEPTION PAYLOAD')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        Log::shouldHaveReceived('error')->once()->with('Merchant request failed.', ['exception_type' => RuntimeException::class]);
        $this->assertSame($before, $this->databaseFingerprint());
    }
}
