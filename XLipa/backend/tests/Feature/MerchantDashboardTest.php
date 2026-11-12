<?php

namespace Tests\Feature;

use App\Http\Controllers\MerchantDashboardController;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\ViewModels\MerchantDashboardOverview;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        Notification::fake();
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function dashboardUrl(Merchant $merchant): string
    {
        return route('merchants.dashboard', ['merchant' => $merchant->id]);
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

    public function test_dashboard_uses_only_foundation_schema_and_shows_real_account_facts(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => 'Kijani Studio']);
        $user = $merchant->creator;
        $before = $this->fingerprint();
        $response = $this->signIn($user)->get($this->dashboardUrl($merchant));
        $response->assertOk()->assertViewIs('merchants.dashboard')->assertViewHas('overview', fn ($overview): bool => $overview instanceof MerchantDashboardOverview)
            ->assertSee('Kijani Studio')->assertSee($user->name)->assertSee($user->email)->assertSee('Email verified')->assertSee('Owner')->assertSee('Draft')->assertSee('TZS only');
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(14, count(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")));
        $this->assertSame(9, DB::table('migrations')->count());
        Notification::assertNothingSent();
    }

    public function test_guests_and_unverified_accounts_cannot_open_dashboard(): void
    {
        $merchant = Merchant::factory()->create();
        $this->get($this->dashboardUrl($merchant))->assertRedirect(route('login'));
        $merchant->creator->forceFill(['email_verified_at' => null])->save();
        $this->signIn($merchant->creator)->get($this->dashboardUrl($merchant))->assertRedirect(route('verification.notice'));
    }

    public static function roles(): array
    {
        return [['owner'], ['manager'], ['staff']];
    }

    #[DataProvider('roles')]
    public function test_membership_roles_reuse_existing_summary_redaction(string $role): void
    {
        $merchant = Merchant::factory()->create(['business_email' => 'PRIVATE_CONTACT@fixture.test', 'address_line1' => 'PRIVATE ADDRESS']);
        $user = $role === 'owner' ? $merchant->creator : User::factory()->create();
        if ($role !== 'owner') {
            MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'role' => $role]);
        }
        $merchant->applications()->sole()->forceFill(['public_feedback' => 'SAFE REVIEW FEEDBACK', 'internal_review_notes' => 'PRIVATE NOTES', 'profile_snapshot' => ['identity' => 'PRIVATE SNAPSHOT']])->save();
        $response = $this->signIn($user)->get($this->dashboardUrl($merchant));
        $response->assertOk()->assertSee(ucfirst($role))->assertSee('Draft')->assertDontSee('PRIVATE CONTACT')->assertDontSee('PRIVATE_CONTACT@fixture.test')
            ->assertDontSee('PRIVATE ADDRESS')->assertDontSee('PRIVATE NOTES')->assertDontSee('PRIVATE SNAPSHOT')->assertDontSee('profile_snapshot')->assertDontSee('internal_review_notes');
        if ($role === 'staff') {
            $response->assertDontSee('SAFE REVIEW FEEDBACK')->assertDontSee('Attempt number')->assertSee('An owner or manager can review application details.');
        } else {
            $response->assertSee('SAFE REVIEW FEEDBACK')->assertSee('Attempt number');
        }
    }

    public function test_foreign_nonmember_and_forged_context_requests_reveal_nothing(): void
    {
        $own = Merchant::factory()->create(['business_name' => 'Own workspace']);
        $foreign = Merchant::factory()->create(['business_name' => 'FOREIGN PRIVATE MERCHANT']);
        $nonmember = User::factory()->create();
        $before = $this->fingerprint();
        $this->signIn($own->creator)->get($this->dashboardUrl($foreign))->assertNotFound()->assertDontSee('FOREIGN PRIVATE MERCHANT');
        $this->get($this->dashboardUrl($own).'?merchant_id='.$foreign->id)->assertOk()->assertSee('Own workspace')->assertDontSee('FOREIGN PRIVATE MERCHANT');
        $this->signIn($nonmember)->get($this->dashboardUrl($own))->assertNotFound()->assertDontSee('Own workspace');
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_membership_revocation_and_role_downgrade_are_fresh(): void
    {
        $merchant = Merchant::factory()->create();
        $user = $merchant->creator;
        $merchant->applications()->sole()->forceFill(['public_feedback' => 'OWNER ONLY FEEDBACK'])->save();
        $this->signIn($user)->get($this->dashboardUrl($merchant))->assertOk()->assertSee('OWNER ONLY FEEDBACK');
        $user->load('merchantMemberships');
        DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('user_id', $user->id)->update(['role' => 'staff']);
        $this->get($this->dashboardUrl($merchant))->assertOk()->assertSee('Staff')->assertDontSee('OWNER ONLY FEEDBACK')->assertDontSee('Attempt number');
        DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->where('user_id', $user->id)->update(['status' => 'revoked']);
        $this->get($this->dashboardUrl($merchant))->assertNotFound()->assertDontSee($merchant->business_name);
        $this->get(route('account'))->assertDontSee($this->dashboardUrl($merchant));
    }

    public static function inactiveAccounts(): array
    {
        return [['suspended'], ['closed'], ['stale-version'], ['missing-version']];
    }

    #[DataProvider('inactiveAccounts')]
    public function test_inactive_or_stale_accounts_cannot_keep_dashboard_access(string $case): void
    {
        $merchant = Merchant::factory()->create();
        $user = $merchant->creator;
        $this->signIn($user);
        if (in_array($case, ['suspended', 'closed'], true)) {
            DB::table('users')->where('id', $user->id)->update(['account_status' => $case]);
        } elseif ($case === 'stale-version') {
            DB::table('users')->where('id', $user->id)->increment('auth_version');
        } else {
            $this->withSession(['auth_version' => null]);
        }
        $this->get($this->dashboardUrl($merchant))->assertRedirect(route('login'))->assertDontSee($merchant->business_name);
        $this->assertGuest();
    }

    public static function platformRoles(): array
    {
        return [['chief_admin'], ['admin']];
    }

    #[DataProvider('platformRoles')]
    public function test_platform_authority_never_bypasses_merchant_membership(string $role): void
    {
        $merchant = Merchant::factory()->create();
        $user = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $user->id, 'role' => $role]);
        $this->signIn($user)->get($this->dashboardUrl($merchant))->assertNotFound();
        MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'role' => 'staff']);
        $merchant->applications()->sole()->forceFill(['public_feedback' => 'NO PLATFORM OVERRIDE'])->save();
        $this->get($this->dashboardUrl($merchant))->assertOk()->assertSee('Staff')->assertDontSee('NO PLATFORM OVERRIDE')->assertDontSee('Attempt number')->assertDontSee('/admin');
    }

    public static function merchantStates(): array
    {
        return [['onboarding'], ['enabled'], ['suspended'], ['closed']];
    }

    #[DataProvider('merchantStates')]
    public function test_merchant_states_are_read_only_and_never_activate_financial_services(string $state): void
    {
        $merchant = Merchant::factory()->create(['operational_status' => $state, 'verification_status' => 'verified']);
        $before = $this->fingerprint();
        $response = $this->signIn($merchant->creator)->get($this->dashboardUrl($merchant));
        $response->assertOk()->assertSee(ucfirst($state))->assertSee('Financial services')->assertSee('Unavailable');
        if (in_array($state, ['suspended', 'closed'], true)) {
            $response->assertSee('Merchant access is limited')->assertSee('This is a read-only overview.');
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public static function applications(): array
    {
        return [['draft', 'Your onboarding is in progress'], ['pending_review', 'Your application is awaiting review'], ['information_requested', 'Your application needs attention'], ['approved', 'Your application is approved'], ['rejected', 'Your application was not approved']];
    }

    #[DataProvider('applications')]
    public function test_application_states_reuse_summary_without_inventing_readiness(string $state, string $heading): void
    {
        $merchant = Merchant::factory()->create();
        DB::table('merchant_applications')->where('merchant_id', $merchant->id)->update(['status' => $state]);
        $this->signIn($merchant->creator)->get($this->dashboardUrl($merchant))->assertOk()->assertSee($heading)
            ->assertSee('Financial services')->assertSee('Unavailable')->assertSee('does not confirm phone ownership')->assertDontSee('Ready to accept payments');
    }

    public function test_missing_current_application_is_not_repaired_or_replaced_by_history(): void
    {
        $merchant = Merchant::factory()->create();
        DB::table('merchant_applications')->where('merchant_id', $merchant->id)->update(['current_slot' => null, 'public_feedback' => 'HISTORICAL FEEDBACK']);
        $before = $this->fingerprint();
        $this->signIn($merchant->creator)->get($this->dashboardUrl($merchant))->assertOk()->assertSee('Onboarding status is unavailable')
            ->assertSee('No current application')->assertDontSee('HISTORICAL FEEDBACK')->assertDontSee('Attempt number');
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_untrusted_merchant_account_and_feedback_text_is_escaped(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '<img src=x onerror=alert(1)>']);
        $user = $merchant->creator;
        $user->forceFill(['name' => '<script>alert("account")</script>'])->save();
        $merchant->applications()->sole()->forceFill(['public_feedback' => '<svg onload=alert(2)>'])->save();
        $response = $this->signIn($user)->get($this->dashboardUrl($merchant));
        $response->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertSee('&lt;script&gt;', false)->assertSee('&lt;svg onload=alert(2)&gt;', false);
        $document = $this->document($response->getContent());
        $this->assertSame(0, $document->query('//script|//svg|//*[@onerror or @onload]')->length);
        $this->assertSame(1, $document->query('//h1')->length);
    }

    public function test_navigation_has_only_real_destinations_and_no_fabricated_financial_values(): void
    {
        $merchant = Merchant::factory()->create();
        $html = $this->signIn($merchant->creator)->get($this->dashboardUrl($merchant))->assertOk()->getContent();
        $document = $this->document($html);
        $buttons = $document->query('//nav[@aria-label="Merchant navigation"]//button');
        $this->assertSame(9, $buttons->length);
        foreach ($buttons as $button) {
            $this->assertTrue($button->hasAttribute('disabled'));
            $this->assertSame('true', $button->getAttribute('aria-disabled'));
            $this->assertStringContainsString('Unavailable', $button->textContent);
        }
        $allowed = [route('home'), route('account'), route('merchants.dashboard', ['merchant' => $merchant->id]), route('merchants.onboarding', ['merchant' => $merchant->id]), route('pages.contact'), route('pages.developers'), '#dashboard-content'];
        foreach ($document->query('//a[@href]') as $anchor) {
            $this->assertContains($anchor->getAttribute('href'), $allowed);
        }
        $this->assertSame(1, $document->query('//a[@aria-current="page"]')->length);
        $forms = $document->query('//form');
        $this->assertSame(1, $forms->length);
        $this->assertSame(route('logout'), $forms->item(0)->getAttribute('action'));
        $this->assertSame('post', $forms->item(0)->getAttribute('method'));
        $this->assertSame(1, $document->query('//form/input[@name="_token"]')->length);
        $this->assertDoesNotMatchRegularExpression('/TZS\s*[0-9]|[0-9]+(?:\.[0-9]+)?%|Total revenue|Recent transactions|Available balance/i', $html);
        $this->assertStringNotContainsString('http://cdn', $html);
        $this->assertSame(0, $document->query('//script|//iframe')->length);
        $this->assertSame(1, $document->query('//meta[@name="viewport"]')->length);
    }

    public function test_account_selector_and_existing_routes_remain_compatible(): void
    {
        $merchant = Merchant::factory()->create();
        $foreign = Merchant::factory()->create();
        $this->signIn($merchant->creator)->get(route('account'))->assertOk()->assertSee($this->dashboardUrl($merchant))->assertDontSee($this->dashboardUrl($foreign))
            ->assertSee(route('merchants.onboarding', ['merchant' => $merchant->id]));
        $this->get(route('merchants.onboarding', ['merchant' => $merchant->id]))->assertOk()->assertSee('Merchant onboarding status');
        $this->get(route('pages.contact'))->assertOk();
        $this->get(route('pages.developers'))->assertOk();
    }

    public function test_missing_invalid_targets_and_write_methods_do_not_expose_or_mutate_data(): void
    {
        $merchant = Merchant::factory()->create();
        $this->signIn($merchant->creator);
        $before = $this->fingerprint();
        $responses = [$this->get('/merchants/'.Str::ulid().'/dashboard'), $this->get('/merchants/invalid/dashboard')];
        foreach ($responses as $response) {
            $response->assertNotFound()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Frame-Options', 'DENY');
        }
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}($this->dashboardUrl($merchant), ['role' => 'chief_admin', 'status' => 'approved'])->assertStatus(405)
                ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_privacy_headers_protect_the_dashboard(): void
    {
        $merchant = Merchant::factory()->create();
        $this->signIn($merchant->creator)->get($this->dashboardUrl($merchant))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_unexpected_errors_remain_redacted(): void
    {
        $merchant = Merchant::factory()->create();
        $this->signIn($merchant->creator);
        config(['app.debug' => false]);
        Log::spy();
        $this->mock(MerchantDashboardController::class)->shouldReceive('show')->once()->andThrow(new RuntimeException('PRIVATE DASHBOARD FAILURE'));
        $this->get($this->dashboardUrl($merchant))->assertStatus(500)->assertDontSee('PRIVATE DASHBOARD FAILURE')->assertHeader('Cache-Control', 'no-store, private');
        Log::shouldHaveReceived('error')->once()->with('Merchant request failed.', ['exception_type' => RuntimeException::class]);
    }

    public function test_minimal_signup_unnamed_pages_have_labels_and_optional_offline_exports(): void
    {
        $signup = $this->get(route('register'))->assertOk()->getContent();
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $this->signIn($merchant->creator);
        $before = $this->fingerprint();
        $exports = ['signup' => $signup];
        foreach (['account' => route('account'), 'dashboard' => $this->dashboardUrl($merchant), 'onboarding' => route('merchants.onboarding', ['merchant' => $merchant->id]), 'applications' => route('merchants.applications', ['merchant' => $merchant->id]), 'verification' => route('merchants.verification', ['merchant' => $merchant->id])] as $page => $url) {
            $html = $this->get($url)->assertOk()->assertSee('Business name not provided')->getContent();
            $document = $this->document($html);
            $this->assertSame(1, $document->query('//h1')->length);
            foreach ($document->query('//a[@href]') as $anchor) {
                $this->assertNotSame('', trim($anchor->textContent.$anchor->getAttribute('aria-label')));
            }
            $exports[$page] = $html;
        }
        $this->assertSame('', $merchant->fresh()->business_name);
        $this->assertSame($before, $this->fingerprint());
        if (getenv('XLIPA_MINIMAL_VISUAL') === '1') {
            $directory = storage_path('framework/testing/minimal-visual-'.bin2hex(random_bytes(10)));
            mkdir($directory, 0700, true);
            foreach ($exports as $page => $html) {
                foreach (['xlipa-site.css', 'xlipa-dashboard.css', 'xlipa-responsive.css', 'xlipa-legal.css', 'xlipa-verification.css'] as $stylesheet) {
                    $html = preg_replace('~<link rel="stylesheet" href="(?:'.preg_quote(rtrim(asset('assets'), '/').'/', '~').'|assets/)'.preg_quote($stylesheet, '~').'"\s*/?>~', '<style>'.file_get_contents(public_path('assets/'.$stylesheet)).'</style>', $html);
                }
                $html = str_replace([asset('assets/xlipa-favicon.svg'), 'assets/xlipa-favicon.svg?v=2', 'assets/xlipa-favicon.svg'], 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('assets/xlipa-favicon.svg'))), $html);
                file_put_contents($directory.'/'.$page.'.html', $html);
            }
            file_put_contents($directory.'/fixture.json', '{"synthetic":true,"feature":"MINIMAL-REGISTRATION"}');
            fwrite(STDOUT, 'MINIMAL_VISUAL='.$directory.PHP_EOL);
        }
    }

    public function test_accessible_layout_and_canonical_assets_with_optional_offline_fixture_export(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => 'Kijani Studio']);
        $user = $merchant->creator;
        $user->forceFill(['name' => 'Amina Kassim', 'email' => 'amina@fixture.test'])->save();
        $html = $this->signIn($user)->get($this->dashboardUrl($merchant))->assertOk()->getContent();
        $document = $this->document($html);
        $this->assertSame(1, $document->query('//main[@id="dashboard-content"]')->length);
        $this->assertSame(1, $document->query('//a[@href="#dashboard-content"]')->length);
        $this->assertSame(1, $document->query('//html[@lang="en"]')->length);
        $this->assertSame(1, $document->query('//nav[@aria-label="Merchant navigation"]')->length);
        $this->assertSame(file_get_contents(base_path('../frontend/assets/xlipa-dashboard.css')), file_get_contents(public_path('assets/xlipa-dashboard.css')));

        if (getenv('XLIPA_PD01_VISUAL') === '1') {
            $directory = storage_path('framework/testing/pd01-visual-'.bin2hex(random_bytes(10)));
            mkdir($directory, 0700, true);
            foreach (['xlipa-site.css', 'xlipa-dashboard.css'] as $stylesheet) {
                $html = str_replace('<link rel="stylesheet" href="'.asset('assets/'.$stylesheet).'">', '<style>'.file_get_contents(base_path('../frontend/assets/'.$stylesheet)).'</style>', $html);
            }
            $html = str_replace(asset('assets/xlipa-favicon.svg'), 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(base_path('../frontend/assets/xlipa-favicon.svg'))), $html);
            file_put_contents($directory.'/dashboard.html', $html);
            file_put_contents($directory.'/fixture.json', json_encode(['synthetic' => true, 'feature' => 'PD-01'], JSON_THROW_ON_ERROR));
            fwrite(STDOUT, 'PD01_VISUAL='.$directory.PHP_EOL);
        }
    }
}
