<?php

namespace Tests\Feature;

use App\Actions\Merchants\BusinessNameCompletionSchema;
use App\Actions\Merchants\SubmissionEvidenceSchema;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

class MerchantOnboardingPreparationTest extends TestCase
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

    private function url(Merchant $merchant): string
    {
        return route('merchants.onboarding', ['merchant' => $merchant->id]);
    }

    private function fingerprint(): string
    {
        $rows = [];
        foreach (['users', 'merchants', 'merchant_memberships', 'merchant_applications', 'audit_logs'] as $table) {
            $rows[$table] = DB::select('SELECT * FROM '.$table.' ORDER BY rowid');
        }

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function test_eligible_owner_sees_disabled_accessible_preview_without_writes(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $before = $this->fingerprint();
        $response = $this->signIn($merchant->creator)->get($this->url($merchant));
        $response->assertOk()->assertViewHas('completionPreview', true)->assertSee('Saving is not available yet.')
            ->assertSee('Business name not provided')->assertSee('Your onboarding status')
            ->assertSee(route('merchants.dashboard', ['merchant' => $merchant->id]))
            ->assertHeader('Cache-Control', 'no-store, private');
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent(), LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        foreach (['//fieldset[@disabled]', '//input[@id="completion-business-name" and @disabled and not(@name)]', '//label[@for="completion-business-name"]', '//button[@type="button" and @disabled and @aria-disabled="true"]', '//*[@id="completion-unavailable" and @role="status"]', '//main[@id="workspace-content" and @tabindex="-1"]', '//a[@href="#workspace-content"]', '//h1'] as $query) {
            $this->assertSame(1, $xpath->query($query)->length, $query);
        }
        $this->assertSame(0, $xpath->query('//form|//script|//input[@type="hidden"]')->length);
        $this->assertSame($before, $this->fingerprint());
        Notification::assertNothingSent();
    }

    public static function hiddenCases(): array
    {
        return array_map(fn (string $case): array => [$case], ['named', 'manager', 'staff', 'suspended', 'closed', 'missing-draft', 'stale-draft', 'submitted', 'snapshot']);
    }

    #[DataProvider('hiddenCases')]
    public function test_ineligible_states_preserve_status_without_preview(string $case): void
    {
        $merchant = Merchant::factory()->create(['business_name' => $case === 'named' ? 'Kijani Studio' : '']);
        $user = $merchant->creator;
        if (in_array($case, ['manager', 'staff'], true)) {
            $user = User::factory()->create();
            MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'role' => $case]);
        } elseif (in_array($case, ['suspended', 'closed'], true)) {
            $merchant->forceFill(['operational_status' => $case])->save();
        } elseif ($case === 'missing-draft') {
            $merchant->applications()->update(['current_slot' => null]);
        } elseif ($case === 'stale-draft') {
            $merchant->applications()->update(['profile_version' => 2]);
        } elseif ($case === 'submitted') {
            $merchant->applications()->update(['status' => 'pending_review']);
        } elseif ($case === 'snapshot') {
            $merchant->applications()->update(['profile_snapshot' => ['private' => 'PRIVATE SNAPSHOT']]);
        }
        $before = $this->fingerprint();
        $this->signIn($user)->get($this->url($merchant))->assertOk()->assertViewHas('completionPreview', false)
            ->assertDontSee('data-completion-preview', false)->assertSee('Your onboarding status')->assertDontSee('PRIVATE SNAPSHOT');
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_guests_unverified_revoked_foreign_and_platform_only_users_are_denied(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $this->get($this->url($merchant))->assertRedirect(route('login'));
        $user = $merchant->creator;
        $user->forceFill(['email_verified_at' => null])->save();
        $this->signIn($user)->get($this->url($merchant))->assertRedirect(route('verification.notice'));
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->signIn($user)->get($this->url($merchant))->assertOk();
        $user->load('merchantMemberships');
        $merchant->memberships()->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->get($this->url($merchant))->assertNotFound()->assertDontSee('data-completion-preview', false);
        $foreign = Merchant::factory()->create();
        $this->signIn($foreign->creator)->get($this->url($merchant))->assertNotFound();
        PlatformRoleAssignment::factory()->create(['user_id' => $foreign->creator->id, 'role' => 'chief_admin']);
        $this->get($this->url($merchant))->assertNotFound();
    }

    public function test_stale_session_and_forged_tenant_do_not_supply_authority(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $foreign = Merchant::factory()->create(['business_name' => 'FOREIGN PRIVATE NAME']);
        $this->signIn($merchant->creator)->get($this->url($merchant).'?merchant_id='.$foreign->id.'&role=owner')->assertOk()->assertDontSee('FOREIGN PRIVATE NAME');
        $this->withSession(['auth_version' => 999])->get($this->url($merchant))->assertRedirect(route('login'));
    }

    public function test_output_is_escaped_and_private_fields_are_absent(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '<script>alert(1)</script>', 'business_email' => 'PRIVATE@fixture.test']);
        $merchant->applications()->update(['public_feedback' => '<svg onload=alert(2)>', 'internal_review_notes' => 'PRIVATE NOTES']);
        $this->signIn($merchant->creator)->get($this->url($merchant))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertSee('&lt;svg', false)->assertDontSee('<script>', false)
            ->assertDontSee('<svg', false)->assertDontSee('PRIVATE NOTES')->assertDontSee('PRIVATE@fixture.test');
    }

    public function test_no_completion_write_route_and_write_methods_are_rejected(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $this->signIn($merchant->creator);
        $before = $this->fingerprint();
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}($this->url($merchant), ['business_name' => 'Unapproved'])->assertStatus(405);
        }
        foreach (app('router')->getRoutes() as $route) {
            if (str_contains($route->uri(), 'onboarding') || str_contains($route->uri(), 'business-name') || str_starts_with($route->uri(), 'admin/')) {
                $this->assertSame(['GET', 'HEAD'], $route->methods());
            }
        }
        $this->post(route('merchants.applications.submit', ['merchant' => $merchant->id]), ['application_id' => $merchant->applications()->value('id'), 'profile_version' => 1, 'context_version' => 0])->assertStatus(503);
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_responsive_styles_use_existing_variables_and_canonical_copy(): void
    {
        $css = file_get_contents(public_path('assets/xlipa-onboarding.css'));
        $this->assertSame(file_get_contents(base_path('../frontend/assets/xlipa-onboarding.css')), $css);
        foreach (['onboarding-page', 'onboarding-navigation', 'onboarding-heading', 'onboarding-grid', 'onboarding-grid-with-preview', 'onboarding-completion', 'onboarding-status', 'onboarding-related-links', '@media (max-width: 850px)', '@media (max-width: 580px)', 'minmax(0, 1fr)', 'overflow-wrap: anywhere', 'a:focus-visible'] as $required) {
            $this->assertStringContainsString($required, $css);
        }
        preg_match_all('/var\((--[a-z-]+)\)/', $css, $variables);
        $workspace = file_get_contents(public_path('assets/xlipa-dashboard.css'));
        foreach (array_unique($variables[1]) as $variable) {
            $this->assertStringContainsString($variable.':', $workspace);
        }

    }

    public function test_preview_is_read_only_under_composed_bnc_schema_and_denied_admission(): void
    {
        B32CRehearsal::activate();
        $merchant = Merchant::factory()->create(['business_name' => '']);
        config(['se1.selected' => realpath(SubmissionEvidenceSchema::artifact())]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SubmissionEvidenceSchema::artifact()], '--realpath' => true, '--no-interaction' => true]));
        config(['bnc1.selected' => realpath(BusinessNameCompletionSchema::artifact())]);
        BusinessNameCompletionSchema::install();
        $before = $this->fingerprint();
        $this->signIn($merchant->creator)->get($this->url($merchant))->assertOk()->assertViewHas('completionPreview', true);
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(0, DB::table('merchant_business_name_completions')->count());
        $this->assertSame(0, DB::table('merchant_business_name_completion_witnesses')->count());
    }
}
