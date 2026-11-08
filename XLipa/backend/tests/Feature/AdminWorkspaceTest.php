<?php

namespace Tests\Feature;

use App\Actions\Auth\ResolvePlatformRole;
use App\Enums\PlatformRole;
use App\Http\Controllers\AdminWorkspaceController;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\ViewModels\AdminWorkspaceOverview;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        Notification::fake();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function ownRoute(string $role): string
    {
        return $role === 'chief_admin' ? 'admin.chief' : 'admin.staff';
    }

    private function fingerprint(): string
    {
        $rows = [];
        foreach (['users', 'merchants', 'merchant_memberships', 'merchant_applications', 'audit_logs', 'platform_role_assignments', 'password_reset_tokens', 'sessions'] as $table) {
            $rows[$table] = DB::select('SELECT * FROM '.$table.' ORDER BY rowid');
        }

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    public static function roles(): array
    {
        return [['chief_admin'], ['admin']];
    }

    public function test_guests_are_denied_at_every_workspace_entry(): void
    {
        foreach (['admin.index', 'admin.chief', 'admin.staff', 'admin.access'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'))->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    #[DataProvider('roles')]
    public function test_each_role_has_its_own_workspace_and_self_access_only(string $role): void
    {
        $label = $role === 'chief_admin' ? 'Chief Admin' : 'Staff Admin';
        $user = $this->actor($role);
        $before = $this->fingerprint();
        $this->signIn($user)->get(route('admin.index'))->assertRedirect(route($this->ownRoute($role)));
        $this->get(route($this->ownRoute($role)))->assertOk()->assertViewIs('admin.workspace')->assertSee($label.' workspace')
            ->assertSee($user->name)->assertSee($user->email)->assertViewHas('overview', fn ($overview): bool => $overview instanceof AdminWorkspaceOverview);
        $this->get(route('admin.access'))->assertOk()->assertSee('Your capability scope')->assertSee('Unavailable');
        $other = $role === 'chief_admin' ? 'admin.staff' : 'admin.chief';
        $this->get(route($other))->assertForbidden()->assertDontSee($user->email);
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(14, count(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")));
        Notification::assertNothingSent();
    }

    #[DataProvider('roles')]
    public function test_action_specific_policies_have_no_management_or_wildcard_grant(string $role): void
    {
        $user = $this->actor($role);
        $gate = Gate::forUser($user);
        $this->assertSame($role === 'chief_admin', $gate->allows('viewChiefWorkspace', PlatformRoleAssignment::class));
        $this->assertSame($role === 'admin', $gate->allows('viewStaffWorkspace', PlatformRoleAssignment::class));
        $this->assertTrue($gate->allows('viewOwnWorkspaceAccess', PlatformRoleAssignment::class));
        foreach (['viewAny', 'create', 'update', 'delete', 'viewMerchants', 'viewApplications', 'approveMerchant', 'viewTransactions', 'moveFunds', 'manageStaff', 'viewAuditLogs', 'manageSettings'] as $ability) {
            $this->assertFalse($gate->allows($ability, PlatformRoleAssignment::class), $ability);
        }
    }

    public static function merchantRoles(): array
    {
        return [['owner'], ['manager'], ['staff']];
    }

    #[DataProvider('merchantRoles')]
    public function test_merchant_membership_and_forged_claims_never_grant_platform_access(string $role): void
    {
        $merchant = Merchant::factory()->create();
        $user = $role === 'owner' ? $merchant->creator : User::factory()->create();
        if ($role !== 'owner') {
            MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'role' => $role]);
        }
        $this->signIn($user)->withSession(['platform_role' => 'chief_admin', 'is_admin' => true]);
        $before = $this->fingerprint();
        $this->get(route('admin.index').'?role=chief_admin&user_id='.$user->id)->assertNotFound();
        foreach (['admin.chief', 'admin.staff', 'admin.access'] as $name) {
            $this->get(route($name).'?role=chief_admin&merchant_id='.$merchant->id)->assertForbidden()->assertDontSee($merchant->business_name);
        }
        $this->get(route('account'))->assertOk()->assertDontSee('Open administrative workspace')->assertSee('Open dashboard');
        $this->assertSame(0, DB::table('platform_role_assignments')->count());
        $this->assertSame($before, $this->fingerprint());
    }

    #[DataProvider('roles')]
    public function test_revocation_is_fresh_even_with_a_loaded_role_relationship(string $role): void
    {
        $user = $this->actor($role);
        $user->load('platformRoleAssignment');
        $this->signIn($user)->get(route($this->ownRoute($role)))->assertOk();
        DB::table('platform_role_assignments')->where('user_id', $user->id)->update(['revoked_at' => now()]);
        $this->get(route($this->ownRoute($role)))->assertForbidden();
        $this->get(route('admin.index'))->assertNotFound();
        $this->get(route('admin.access'))->assertForbidden();
        $this->get(route('account'))->assertDontSee('Open administrative workspace');
        $this->assertNull(app(ResolvePlatformRole::class)->execute($user));
    }

    public function test_role_changes_are_observed_without_inheriting_the_previous_authority(): void
    {
        $user = $this->actor('chief_admin');
        $user->load('platformRoleAssignment');
        $this->signIn($user)->get(route('admin.chief'))->assertOk();
        DB::table('platform_role_assignments')->where('user_id', $user->id)->update(['role' => 'admin']);
        $this->get(route('admin.chief'))->assertForbidden();
        $this->get(route('admin.index'))->assertRedirect(route('admin.staff'));
        $this->get(route('admin.staff'))->assertOk()->assertSee('Staff Admin workspace')->assertDontSee('Staff Management');
        $this->assertFalse(Gate::forUser($user)->allows('viewChiefWorkspace', PlatformRoleAssignment::class));
        $this->assertSame(PlatformRole::Admin, app(ResolvePlatformRole::class)->execute($user));
    }

    public static function inactiveAssignments(): array
    {
        return [['future-grant'], ['future-revocation'], ['revoked'], ['deleted']];
    }

    #[DataProvider('inactiveAssignments')]
    public function test_ungranted_revoked_and_missing_assignments_fail_closed(string $case): void
    {
        $user = $this->actor('chief_admin');
        if ($case === 'deleted') {
            DB::table('platform_role_assignments')->where('user_id', $user->id)->delete();
        } else {
            DB::table('platform_role_assignments')->where('user_id', $user->id)->update(match ($case) {
                'future-grant' => ['granted_at' => now()->addHour()],
                'future-revocation' => ['revoked_at' => now()->addHour()],
                default => ['revoked_at' => now()->subMinute()],
            });
        }
        $this->signIn($user)->get(route('admin.index'))->assertNotFound();
        $this->get(route('admin.chief'))->assertForbidden();
        $this->assertNull(app(ResolvePlatformRole::class)->execute($user));
    }

    public static function inactiveAccounts(): array
    {
        return [['suspended'], ['closed'], ['stale-version'], ['missing-version'], ['unverified']];
    }

    #[DataProvider('inactiveAccounts')]
    public function test_account_restrictions_precede_platform_authority(string $case): void
    {
        $user = $this->actor('chief_admin');
        $this->signIn($user);
        if (in_array($case, ['suspended', 'closed'], true)) {
            DB::table('users')->where('id', $user->id)->update(['account_status' => $case]);
        } elseif ($case === 'stale-version') {
            DB::table('users')->where('id', $user->id)->increment('auth_version');
        } elseif ($case === 'missing-version') {
            $this->withSession(['auth_version' => null]);
        } else {
            DB::table('users')->where('id', $user->id)->update(['email_verified_at' => null]);
        }
        $this->get(route('admin.chief'))->assertRedirect(route($case === 'unverified' ? 'verification.notice' : 'login'))->assertDontSee($user->email);
        if (in_array($case, ['suspended', 'closed', 'unverified'], true)) {
            $this->assertFalse(Gate::forUser($user)->allows('viewOwnWorkspaceAccess', PlatformRoleAssignment::class));
        }
    }

    #[DataProvider('roles')]
    public function test_no_platform_role_grants_merchant_visibility_without_membership(string $role): void
    {
        $user = $this->actor($role);
        $merchant = Merchant::factory()->create();
        $this->signIn($user)->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertNotFound();
        $this->get(route('merchants.onboarding', ['merchant' => $merchant->id]))->assertNotFound();
        $this->get(route('account'))->assertOk()->assertSee('Open administrative workspace')->assertDontSee($merchant->business_name);
    }

    #[DataProvider('roles')]
    public function test_only_self_account_and_role_facts_are_read_and_presented(string $role): void
    {
        $user = $this->actor($role);
        $other = $this->actor('chief_admin');
        $other->forceFill(['name' => 'FOREIGN PLATFORM PERSON', 'email' => 'private-person@fixture.test'])->save();
        $merchant = Merchant::factory()->create(['business_name' => 'PRIVATE MERCHANT NAME', 'business_email' => 'private-merchant@fixture.test']);
        $merchant->applications()->sole()->forceFill(['public_feedback' => 'PRIVATE APPLICATION FEEDBACK', 'internal_review_notes' => 'PRIVATE NOTES', 'profile_snapshot' => ['identity' => 'PRIVATE IDENTITY']])->save();
        $this->signIn($user);
        $before = $this->fingerprint();
        DB::enableQueryLog();
        try {
            $response = $this->get(route($this->ownRoute($role)).'?user_id='.$other->id.'&role=chief_admin&merchant_id='.$merchant->id);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $response->assertOk()->assertSee($user->name)->assertSee($user->email)->assertDontSee('FOREIGN PLATFORM PERSON')->assertDontSee('private-person@fixture.test')
            ->assertDontSee('PRIVATE MERCHANT NAME')->assertDontSee('private-merchant@fixture.test')->assertDontSee('PRIVATE APPLICATION FEEDBACK')->assertDontSee('PRIVATE NOTES')->assertDontSee('PRIVATE IDENTITY');
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/\bfrom\s+["`\[]?(merchants|merchant_applications|audit_logs|merchant_identity_records|merchant_memberships)\b/i', $query['query']);
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_missing_assignment_schema_denies_without_provisioning_or_repair(): void
    {
        $user = $this->actor('chief_admin');
        Schema::drop('platform_role_assignments');
        $this->signIn($user)->get(route('admin.index'))->assertNotFound();
        $this->get(route('admin.chief'))->assertForbidden();
        $this->get(route('account'))->assertOk()->assertDontSee('Open administrative workspace');
        $this->assertFalse(Schema::hasTable('platform_role_assignments'));
    }

    #[DataProvider('roles')]
    public function test_navigation_and_capability_status_are_explicit_for_each_role(string $role): void
    {
        $user = $this->actor($role);
        $html = $this->signIn($user)->get(route($this->ownRoute($role)))->assertOk()->getContent();
        $document = $this->document($html);
        $buttons = $document->query('//nav[@aria-label="Administrative navigation"]//button');
        $this->assertSame($role === 'chief_admin' ? 14 : 3, $buttons->length);
        foreach ($buttons as $button) {
            $this->assertTrue($button->hasAttribute('disabled'));
            $this->assertSame('true', $button->getAttribute('aria-disabled'));
            $this->assertStringContainsString('Unavailable', $button->textContent);
        }
        $allowed = [route('home'), route('account'), route($this->ownRoute($role)), route('admin.access'), route('pages.contact'), '#admin-content'];
        if ($role === 'chief_admin') {
            $allowed[] = route('admin.applications');
            $this->assertSame(1, $document->query('//a[@href="'.route('admin.applications').'"]')->length);
        } else {
            $this->assertSame(0, $document->query('//a[@href="'.route('admin.applications').'"]')->length);
        }
        foreach ($document->query('//a[@href]') as $anchor) {
            $this->assertContains($anchor->getAttribute('href'), $allowed);
        }
        $this->assertSame(1, $document->query('//a[@aria-current="page"]')->length);
        $this->assertSame(1, $document->query('//form')->length);
        $this->assertSame(route('logout'), $document->query('//form')->item(0)->getAttribute('action'));
        $this->assertSame(1, $document->query('//form/input[@name="_token"]')->length);
        $this->assertSame(0, $document->query('//script|//iframe')->length);
        $this->assertDoesNotMatchRegularExpression('/TZS\s*[0-9]|[0-9]+(?:\.[0-9]+)?%|Total revenue|Completed settlements|Successful transactions|Available balance/i', $html);
        $access = $this->get(route('admin.access'))->assertOk()->getContent();
        $scope = $this->document($access);
        $this->assertSame(1, $scope->query('//a[@aria-current="page" and @href="'.route('admin.access').'"]')->length);
        $this->assertSame(1, $scope->query('//dl/div[dt="View Chief Admin overview"]/dd[text()="'.($role === 'chief_admin' ? 'Available' : 'Not permitted').'"]')->length);
        $this->assertSame(1, $scope->query('//dl/div[dt="View Staff Admin overview"]/dd[text()="'.($role === 'admin' ? 'Available' : 'Not permitted').'"]')->length);
    }

    public function test_untrusted_self_account_text_is_escaped(): void
    {
        $user = $this->actor('chief_admin');
        $user->forceFill(['name' => '<img src=x onerror=alert(1)>', 'email' => '<svg onload=alert(2)>@fixture.test'])->save();
        $html = $this->signIn($user)->get(route('admin.chief'))->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertSee('&lt;svg onload=alert(2)&gt;', false)->getContent();
        $this->assertSame(0, $this->document($html)->query('//script|//svg|//*[@onload or @onerror]')->length);
    }

    #[DataProvider('roles')]
    public function test_unimplemented_routes_and_all_write_methods_remain_unavailable(string $role): void
    {
        $user = $this->actor($role);
        $this->signIn($user);
        $before = $this->fingerprint();
        foreach (['/admin/merchants', '/admin/applications/approve', '/admin/transactions', '/admin/staff-management', '/admin/audit-logs', '/admin/settings', '/admin/access/'.$user->id] as $url) {
            $this->get($url)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Frame-Options', 'DENY');
        }
        foreach (['admin.index', 'admin.chief', 'admin.staff', 'admin.access', 'admin.applications'] as $name) {
            foreach (['post', 'put', 'patch', 'delete'] as $method) {
                $this->{$method}(route($name), ['role' => 'chief_admin', 'status' => 'approved', 'user_id' => $user->id])->assertStatus(405)->assertHeader('Cache-Control', 'no-store, private');
            }
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_success_and_authorization_denials_have_private_headers(): void
    {
        $this->signIn($this->actor('admin'));
        foreach ([route('admin.staff'), route('admin.access'), route('admin.chief')] as $url) {
            $this->get($url)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
        }
    }

    public function test_unexpected_admin_errors_are_generic_and_redacted(): void
    {
        $this->signIn($this->actor('chief_admin'));
        config(['app.debug' => false]);
        Log::spy();
        $this->mock(AdminWorkspaceController::class)->shouldReceive('chief')->once()->andThrow(new RuntimeException('PRIVATE ADMIN FAILURE'));
        $this->get(route('admin.chief'))->assertStatus(500)->assertDontSee('PRIVATE ADMIN FAILURE')->assertHeader('Cache-Control', 'no-store, private');
        Log::shouldHaveReceived('error')->once()->with('Administrative request failed.', ['exception_type' => RuntimeException::class]);
    }

    #[DataProvider('roles')]
    public function test_shared_styles_and_accessible_layout_with_optional_offline_exports(string $role): void
    {
        $user = $this->actor($role);
        $user->forceFill(['name' => $role === 'chief_admin' ? 'Amina Platform' : 'Juma Staff', 'email' => $role.'@fixture.test'])->save();
        $this->signIn($user);
        $pages = ['workspace' => $this->ownRoute($role), 'access' => 'admin.access'];
        $exports = [];
        foreach ($pages as $file => $name) {
            $html = $this->get(route($name))->assertOk()->getContent();
            $document = $this->document($html);
            $this->assertSame(1, $document->query('//main[@id="admin-content"]')->length);
            $this->assertSame(1, $document->query('//a[@href="#admin-content"]')->length);
            $this->assertSame(1, $document->query('//h1')->length);
            $this->assertSame(1, $document->query('//meta[@name="viewport"]')->length);
            $exports[$file] = $html;
        }
        $this->assertSame(file_get_contents(base_path('../frontend/assets/xlipa-admin.css')), file_get_contents(public_path('assets/xlipa-admin.css')));
        if (getenv('XLIPA_PD02_VISUAL') === '1') {
            $directory = storage_path('framework/testing/pd02-visual-'.bin2hex(random_bytes(10)));
            mkdir($directory, 0700, true);
            foreach ($exports as $file => $html) {
                foreach (['xlipa-site.css', 'xlipa-dashboard.css', 'xlipa-admin.css'] as $stylesheet) {
                    $html = str_replace('<link rel="stylesheet" href="'.asset('assets/'.$stylesheet).'">', '<style>'.file_get_contents(base_path('../frontend/assets/'.$stylesheet)).'</style>', $html);
                }
                $html = str_replace(asset('assets/xlipa-favicon.svg'), 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(base_path('../frontend/assets/xlipa-favicon.svg'))), $html);
                file_put_contents($directory.'/'.$file.'.html', $html);
            }
            file_put_contents($directory.'/fixture.json', json_encode(['synthetic' => true, 'feature' => 'PD-02', 'role' => $role], JSON_THROW_ON_ERROR));
            fwrite(STDOUT, 'PD02_VISUAL='.$role.':'.$directory.PHP_EOL);
        }
    }
}
