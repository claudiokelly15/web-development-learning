<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminApplicationController;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\MerchantApplication;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

class AdminApplicationReviewTest extends TestCase
{
    private User $chief;

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        Notification::fake();
        $this->chief = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $this->chief->id, 'role' => 'chief_admin']);
    }

    private function signIn(?User $user = null): static
    {
        $user ??= $this->chief;

        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function application(string $status = 'pending_review', ?Merchant $merchant = null): MerchantApplication
    {
        return MerchantApplication::factory()->create(['merchant_id' => ($merchant ?? Merchant::factory()->create())->id, 'status' => $status,
            'submitted_at' => '2026-11-08 12:00:00', 'profile_snapshot' => ['nin' => 'PRIVATE-SNAPSHOT'], 'internal_review_notes' => 'PRIVATE-NOTES']);
    }

    private function url(MerchantApplication $application): string
    {
        return route('admin.applications.show', ['application' => $application->id]);
    }

    private function fingerprint(): string
    {
        $rows = [];
        foreach (['users', 'merchants', 'merchant_memberships', 'merchant_applications', 'audit_logs', 'platform_role_assignments'] as $table) {
            $rows[$table] = DB::select('SELECT * FROM '.$table.' ORDER BY rowid');
        }

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function test_filtering_and_pagination_preserve_scope(): void
    {
        for ($i = 0; $i < 22; $i++) {
            $this->application();
        }
        $this->application('rejected');
        $this->signIn()->get(route('admin.applications', ['status' => 'pending_review']))->assertOk()
            ->assertViewHas('applications', fn ($rows): bool => $rows->total() === 22 && $rows->count() === 20 && str_contains($rows->nextPageUrl(), 'status=pending_review'));
        $this->get(route('admin.applications', ['status' => 'pending_review', 'page' => 2]))->assertOk()->assertViewHas('applications', fn ($rows): bool => $rows->count() === 2);
        $this->get(route('admin.applications', ['status' => 'rejected']))->assertOk()->assertViewHas('applications', fn ($rows): bool => $rows->total() === 1);
        foreach (['draft', 'unknown', ['pending_review']] as $status) {
            $this->getJson(route('admin.applications', ['status' => $status]))->assertUnprocessable();
        }
        foreach (['0', '-1', '100001', 'bad'] as $page) {
            $this->getJson(route('admin.applications', ['page' => $page]))->assertUnprocessable();
        }
    }

    public function test_detail_is_redacted_scoped_paginated_and_read_only(): void
    {
        $application = $this->application();
        $other = $this->application();
        foreach (range(1, 23) as $i) {
            AuditLog::factory()->create(['merchant_id' => $application->merchant_id, 'application_id' => $application->id, 'event_type' => 'application.reviewed', 'metadata' => ['reason_code' => 'PRIVATE-AUDIT']]);
        }
        AuditLog::factory()->create(['merchant_id' => $other->merchant_id, 'application_id' => $other->id, 'event_type' => 'application.submitted']);
        $before = $this->fingerprint();
        $this->signIn()->get($this->url($application))->assertOk()->assertSee('Submission timeline')->assertSee('Read-only audit history')
            ->assertSee('integrity unverified')->assertDontSee('PRIVATE-SNAPSHOT')->assertDontSee('PRIVATE-NOTES')->assertDontSee('PRIVATE-AUDIT')
            ->assertViewHas('history', fn ($rows): bool => $rows->total() === 23 && $rows->count() === 20)
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Frame-Options', 'DENY');
        $this->get($this->url($application).'?page=2')->assertOk()->assertViewHas('history', fn ($rows): bool => $rows->count() === 3);
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}($this->url($application), ['status' => 'approved'])->assertStatus(405);
        }
        $this->assertSame($before, $this->fingerprint());
        Notification::assertNothingSent();
    }

    public function test_conflicts_drafts_and_unknown_ids_are_hidden(): void
    {
        $own = Merchant::factory()->create(['created_by_user_id' => $this->chief->id]);
        $linked = Merchant::factory()->create();
        MerchantMembership::factory()->create(['merchant_id' => $linked->id, 'user_id' => $this->chief->id, 'role' => 'manager', 'status' => 'revoked', 'revoked_at' => now()]);
        $submitted = $this->application();
        DB::table('merchant_applications')->where('id', $submitted->id)->update(['submitted_by_user_id' => $this->chief->id]);
        $this->signIn();
        foreach ([$this->application('approved', $own), $this->application('pending_review', $linked), $this->application('draft'), $submitted] as $application) {
            $this->get($this->url($application))->assertNotFound();
        }
        $this->get('/admin/applications/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
        $this->get('/admin/applications/approve')->assertNotFound();
    }

    public function test_guest_staff_merchant_and_fresh_revocation_are_denied(): void
    {
        $application = $this->application();
        $this->get($this->url($application))->assertRedirect(route('login'));
        $staff = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $staff->id, 'role' => 'admin']);
        foreach ([$staff, User::factory()->create()] as $actor) {
            $this->signIn($actor)->get($this->url($application))->assertForbidden();
        }
        $this->signIn()->get($this->url($application))->assertOk();
        DB::table('platform_role_assignments')->where('user_id', $this->chief->id)->update(['revoked_at' => now()]);
        $this->get($this->url($application))->assertForbidden();
    }

    public function test_stale_sessions_and_unverified_accounts_cannot_read_details(): void
    {
        $application = $this->application();
        $this->signIn()->withSession(['auth_version' => 0])->get($this->url($application))->assertRedirect(route('login'));
        DB::table('users')->where('id', $this->chief->id)->update(['email_verified_at' => null]);
        $this->signIn()->get($this->url($application))->assertRedirect(route('verification.notice'));
    }

    public function test_escaped_text_and_optional_offline_exports(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '<img src=x onerror=alert(1)>']);
        $application = $this->application('pending_review', $merchant);
        for ($i = 0; $i < 21; $i++) {
            $this->application();
            AuditLog::factory()->create(['merchant_id' => $application->merchant_id, 'application_id' => $application->id, 'event_type' => 'application.submitted']);
        }
        $this->signIn();
        $directory = null;
        if (getenv('XLIPA_PD05A_VISUAL') === '1') {
            $directory = storage_path('framework/testing/pd05a-visual-'.bin2hex(random_bytes(10)));
            mkdir($directory, 0700, true);
        }
        foreach (['list' => route('admin.applications'), 'detail' => $this->url($application)] as $page => $url) {
            $html = $this->get($url)->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->getContent();
            $this->assertStringNotContainsString('<img src=x', $html);
            $this->assertStringContainsString('name="viewport"', $html);
            if ($directory !== null) {
                foreach (['xlipa-site.css', 'xlipa-responsive.css', 'xlipa-legal.css', 'xlipa-dashboard.css'] as $stylesheet) {
                    $html = str_replace('<link rel="stylesheet" href="'.asset('assets/'.$stylesheet).'">', '<style>'.file_get_contents(public_path('assets/'.$stylesheet)).'</style>', $html);
                }
                $html = str_replace(asset('assets/xlipa-favicon.svg'), 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('assets/xlipa-favicon.svg'))), $html);
                file_put_contents($directory.'/'.$page.'.html', $html);
            }
        }
        if ($directory !== null) {
            file_put_contents($directory.'/fixture.json', '{"synthetic":true,"feature":"PD-05A"}');
            fwrite(STDOUT, 'PD05A_VISUAL='.$directory.PHP_EOL);
        }
    }

    public function test_detail_errors_remain_generic_even_with_debug_enabled(): void
    {
        $application = $this->application();
        $this->signIn();
        config(['app.debug' => true]);
        $this->mock(AdminApplicationController::class)->shouldReceive('show')->once()->andThrow(new \RuntimeException('PRIVATE-SQL-FAILURE'));
        $this->get($this->url($application))->assertStatus(503)->assertDontSee('PRIVATE-SQL-FAILURE')->assertHeader('Cache-Control', 'no-store, private');
    }
}
