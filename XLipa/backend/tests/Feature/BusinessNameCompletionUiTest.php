<?php

namespace Tests\Feature;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\BusinessNameCompletionResolver;
use App\Actions\Merchants\BusinessNameCompletionSchema as BNC;
use App\Actions\Merchants\BusinessNameCompletionService;
use App\Actions\Merchants\SubmissionEvidenceSchema as SE;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\B32CRehearsal;
use Tests\Support\FakeBusinessNameCompletionAdmission;
use Tests\TestCase;

class BusinessNameCompletionUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        Notification::fake();
        foreach ([BusinessNameCompletionService::class, BusinessNameCompletionResolver::class] as $service) {
            $this->app->bind($service, fn () => throw new RuntimeException('A read-only preview must never invoke completion services.'));
        }
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function url(Merchant $merchant): string
    {
        return route('merchants.onboarding', ['merchant' => $merchant->id]);
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

    private function fingerprint(): string
    {
        $rows = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
            $rows[$table->name] = DB::select('SELECT * FROM '.$table->name.' ORDER BY rowid');
        }

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function test_empty_owner_sees_accessible_disabled_preview_and_read_only_navigation(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $before = $this->fingerprint();
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace|create|alter|drop)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $response = $this->signIn($merchant->creator)->get($this->url($merchant));
        $response->assertOk()->assertViewHas('completionPreview', true)->assertSee('Add your business name')->assertSee('Saving is not available yet.')->assertSee('Application submission is currently unavailable.')
            ->assertSee(route('merchants.dashboard', ['merchant' => $merchant->id]), false)->assertSee(route('account'), false);
        $document = $this->document($response->getContent());
        $this->assertSame(1, $document->query('//*[@data-completion-preview]')->length);
        $this->assertSame(1, $document->query('//label[@for="completion-business-name"]')->length);
        $this->assertSame(1, $document->query('//input[@id="completion-business-name" and @disabled and not(@name) and not(@value)]')->length);
        $this->assertSame(1, $document->query('//fieldset[@disabled]//button[@type="button" and @disabled and @aria-disabled="true"]')->length);
        $this->assertSame(0, $document->query('//form|//input[@type="hidden"]|//textarea|//script')->length);
        $this->assertSame(1, $document->query('//head/link[contains(@href,"xlipa-onboarding.css")]')->length);
        $this->assertSame(1, $document->query('//*[@id="completion-unavailable" and @role="status"]')->length);
        $this->assertSame([], $writes);
        $this->assertSame($before, $this->fingerprint());
        foreach (['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Frame-Options' => 'DENY', 'X-Content-Type-Options' => 'nosniff'] as $header => $value) {
            $this->assertStringContainsString($value, $response->headers->get($header));
        }
        Notification::assertNothingSent();
    }

    public static function roles(): array
    {
        return [['owner', true], ['manager', false], ['staff', false]];
    }

    #[DataProvider('roles')]
    public function test_only_an_active_owner_sees_the_completion_section(string $role, bool $visible): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $user = $merchant->creator;
        if ($role !== 'owner') {
            $user = User::factory()->create();
            MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'role' => $role]);
        }
        $response = $this->signIn($user)->get($this->url($merchant))->assertOk()->assertViewHas('completionPreview', $visible);
        $this->assertSame($visible ? 1 : 0, $this->document($response->getContent())->query('//*[@data-completion-preview]')->length);
        $response->assertSee('Merchant status')->assertSee('Merchant overview');
    }

    public static function ineligible(): array
    {
        return [['named'], ['whitespace'], ['suspended'], ['closed'], ['not-current'], ['wrong-version'], ['missing'], ['submitted-projection'], ['review-projection'], ['snapshot']];
    }

    #[DataProvider('ineligible')]
    public function test_ineligible_workflow_states_keep_existing_read_only_summary(string $state): void
    {
        $merchant = Merchant::factory()->create(['business_name' => match ($state) {
            'named' => 'Existing Name', 'whitespace' => '   ', default => ''
        }]);
        switch ($state) {
            case 'suspended': case 'closed': DB::table('merchants')->where('id', $merchant->id)->update(['operational_status' => $state]);
                break;
            case 'not-current': DB::table('merchant_applications')->update(['current_slot' => null]);
                break;
            case 'wrong-version': DB::table('merchant_applications')->update(['profile_version' => 2]);
                break;
            case 'missing': DB::table('merchant_applications')->delete();
                break;
            case 'submitted-projection': DB::table('merchant_applications')->update(['submitted_at' => '2026-11-13 00:00:00']);
                break;
            case 'review-projection': DB::table('merchant_applications')->update(['internal_review_notes' => 'PRIVATE INVALID CIPHERTEXT']);
                break;
            case 'snapshot': DB::table('merchant_applications')->update(['profile_snapshot' => 'PRIVATE INVALID CIPHERTEXT']);
                break;
        }
        $before = $this->fingerprint();
        $this->signIn($merchant->creator)->get($this->url($merchant))->assertOk()->assertViewHas('completionPreview', false)->assertDontSee('Add your business name')->assertDontSee('PRIVATE INVALID CIPHERTEXT')->assertSee('Merchant status');
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_guest_unverified_stale_session_and_revoked_owner_are_denied(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $this->get($this->url($merchant))->assertRedirect(route('login'));
        $user = $merchant->creator;
        $user->forceFill(['email_verified_at' => null])->save();
        $this->signIn($user)->get($this->url($merchant))->assertRedirect(route('verification.notice'))->assertDontSee('Add your business name');
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->signIn($user)->withSession(['auth_version' => 2])->get($this->url($merchant))->assertRedirect(route('login'));
        DB::table('merchant_memberships')->update(['status' => 'revoked', 'revoked_at' => '2026-11-13 00:00:00']);
        $this->signIn($user)->get($this->url($merchant))->assertNotFound()->assertDontSee('Add your business name');
    }

    public function test_foreign_and_platform_only_users_have_no_completion_visibility(): void
    {
        $own = Merchant::factory()->create(['business_name' => '']);
        $foreign = Merchant::factory()->create(['business_name' => 'FOREIGN PRIVATE MERCHANT']);
        $this->signIn($own->creator)->get($this->url($foreign))->assertNotFound()->assertDontSee('FOREIGN PRIVATE MERCHANT');
        $this->get($this->url($own).'?merchant_id='.$foreign->id.'&businessName=PRIVATE_QUERY_NAME&completion_request_id='.Str::uuid())->assertOk()->assertSee('Add your business name')->assertDontSee('PRIVATE_QUERY_NAME')->assertDontSee('FOREIGN PRIVATE MERCHANT');
        $chief = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $chief->id, 'role' => 'chief_admin']);
        $this->signIn($chief)->get($this->url($own))->assertNotFound()->assertDontSee('Add your business name');
    }

    public function test_untrusted_old_input_and_query_are_never_completion_context(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '', 'business_email' => 'PRIVATE_EMAIL@fixture.test']);
        $response = $this->signIn($merchant->creator)->withSession(['_old_input' => ['businessName' => 'PRIVATE_OLD_NAME', 'context_token' => 'PRIVATE_CONTEXT']])->get($this->url($merchant).'?businessName=PRIVATE_QUERY_NAME&auth_version=99');
        $response->assertOk()->assertDontSee('PRIVATE_OLD_NAME')->assertDontSee('PRIVATE_QUERY_NAME')->assertDontSee('PRIVATE_CONTEXT')->assertDontSee('PRIVATE_EMAIL@fixture.test')
            ->assertDontSee('completion_request_id')->assertDontSee('merchant_profile_version')->assertDontSee('email_binding_version');
    }

    public function test_stored_name_and_feedback_remain_escaped(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '<script>alert("name")</script>']);
        $merchant->applications()->sole()->forceFill(['public_feedback' => '<img src=x onerror=alert(1)>'])->save();
        $response = $this->signIn($merchant->creator)->get($this->url($merchant))->assertOk()->assertSee('&lt;script&gt;', false)->assertSee('&lt;img', false);
        $this->assertSame(0, $this->document($response->getContent())->query('//script|//*[@onerror]')->length);
        $response->assertDontSee('Add your business name');
    }

    public static function writeMethods(): array
    {
        return [['POST'], ['PUT'], ['PATCH'], ['DELETE']];
    }

    #[DataProvider('writeMethods')]
    public function test_onboarding_has_no_write_method_or_completion_route(string $method): void
    {
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $before = $this->fingerprint();
        $this->signIn($merchant->creator)->call($method, $this->url($merchant), ['businessName' => 'PRIVATE BODY'])->assertStatus(405);
        $this->post($this->url($merchant).'/business-name', ['businessName' => 'PRIVATE BODY'])->assertNotFound();
        $this->post($this->url($merchant).'/business-name/resolve', ['context_token' => 'PRIVATE_CONTEXT'])->assertNotFound();
        $route = app('router')->getRoutes()->getByName('merchants.onboarding');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_complete_bnc_fixture_does_not_enable_controls_even_with_fictional_admission(): void
    {
        B32CRehearsal::activate();
        $merchant = Merchant::factory()->create(['business_name' => '']);
        config(['se1.selected' => realpath(SE::artifact())]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SE::artifact()], '--realpath' => true, '--no-interaction' => true]));
        config(['bnc1.selected' => realpath(BNC::artifact())]);
        BNC::install();
        $this->app->instance(BusinessNameCompletionAdmission::class, new FakeBusinessNameCompletionAdmission);
        $before = $this->fingerprint();
        $response = $this->signIn($merchant->creator->fresh())->get($this->url($merchant))->assertOk()->assertViewHas('completionPreview', true);
        $this->assertSame(1, $this->document($response->getContent())->query('//input[@disabled]')->length);
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(0, DB::table(BNC::TABLE)->count());
        $this->assertSame(0, DB::table(BNC::WITNESSES)->count());
    }

    public function test_responsive_asset_parity_and_optional_offline_visual_fixtures(): void
    {
        $this->assertSame(file_get_contents(base_path('../frontend/assets/xlipa-onboarding.css')), file_get_contents(public_path('assets/xlipa-onboarding.css')));
        $merchant = Merchant::factory()->create(['business_name' => '']);
        $owner = $merchant->creator;
        $exports = ['owner-empty' => $this->signIn($owner)->get($this->url($merchant))->assertOk()->getContent()];
        $manager = User::factory()->create();
        MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $manager->id, 'role' => 'manager']);
        $exports['manager-empty'] = $this->signIn($manager)->get($this->url($merchant))->assertOk()->getContent();
        $long = Merchant::factory()->create(['created_by_user_id' => $owner->id, 'business_name' => str_repeat('界', 200)]);
        $exports['owner-long-name'] = $this->signIn($owner)->get($this->url($long))->assertOk()->getContent();
        foreach ($exports as $html) {
            $this->assertSame(1, $this->document($html)->query('//meta[@name="viewport"]')->length);
        }
        if (getenv('XLIPA_BNC_INT03_VISUAL') === '1') {
            $directory = storage_path('framework/testing/bnc-int03-visual-'.bin2hex(random_bytes(10)));
            mkdir($directory, 0700, true);
            foreach ($exports as $page => $html) {
                foreach (['xlipa-site.css', 'xlipa-dashboard.css', 'xlipa-responsive.css', 'xlipa-legal.css', 'xlipa-onboarding.css'] as $stylesheet) {
                    $html = str_replace('<link rel="stylesheet" href="'.asset('assets/'.$stylesheet).'">', '<style>'.file_get_contents(public_path('assets/'.$stylesheet)).'</style>', $html);
                }
                $html = str_replace(asset('assets/xlipa-favicon.svg'), 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('assets/xlipa-favicon.svg'))), $html);
                $html = preg_replace('/<meta name="csrf-token"[^>]*>/', '', $html);
                file_put_contents($directory.'/'.$page.'.html', $html);
            }
            file_put_contents($directory.'/fixture.json', '{"synthetic":true,"stage":"BNC-INT-03"}');
            fwrite(STDOUT, 'BNC_INT03_VISUAL='.$directory.PHP_EOL);
        }
    }
}
