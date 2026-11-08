<?php

namespace Tests\Feature;

use App\Actions\Merchants\MerchantVerification;
use App\Actions\Merchants\VerificationAuditReconciler;
use App\Actions\Merchants\VerificationCipher;
use App\Actions\Merchants\VerificationEnvironment;
use App\Actions\Merchants\VerificationReadiness;
use App\Actions\Merchants\VerificationStore;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\B32CRehearsal;
use Tests\Support\FakeMerchantVerificationEnvironment;
use Tests\Support\SyntheticCipher;
use Tests\Support\SyntheticKeys;
use Tests\TestCase;

class MerchantVerificationTest extends TestCase
{
    private FakeMerchantVerificationEnvironment $environment;

    private User $owner;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        Notification::fake();
        $this->environment = new FakeMerchantVerificationEnvironment;
        $this->app->instance(VerificationEnvironment::class, $this->environment);
        $this->owner = User::factory()->create(['password' => Hash::make('fixture-password')]);
        $this->merchant = Merchant::factory()->create(['created_by_user_id' => $this->owner->id]);
    }

    private function signIn(?User $user = null): static
    {
        $user ??= $this->owner;

        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function url(string $action = 'show', ?Merchant $merchant = null): string
    {
        return route(match ($action) {
            'show' => 'merchants.verification','code' => 'merchants.verification.verify',default => 'merchants.verification.'.$action
        }, ['merchant' => ($merchant ?? $this->merchant)->id]);
    }

    private function service(): MerchantVerification
    {
        return app(MerchantVerification::class);
    }

    private function capture(string $phone = '+255712345678'): void
    {
        $version = DB::table('merchant_onboarding_contexts')->where('merchant_id', $this->merchant->id)->value('context_version') ?? 0;
        $this->service()->capture($this->owner, $this->merchant, 'contact', ['phone' => $phone, 'context_version' => $version]);
    }

    private function issue(): string
    {
        return $this->service()->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1');
    }

    private function wrongCode(): string
    {
        return $this->environment->lastCode === '111111' ? '222222' : '111111';
    }

    private function fingerprint(): string
    {
        return hash('sha256', json_encode(array_map(fn (string $table): array => DB::select('SELECT * FROM '.$table.' ORDER BY rowid'), ['merchant_onboarding_contexts', 'merchant_contact_versions', 'merchant_identity_records', 'phone_otp_challenges', 'otp_delivery_intents', 'contact_verification_proofs', 'verification_limit_state', 'audit_logs']), JSON_THROW_ON_ERROR));
    }

    public function test_guest_is_denied_without_any_capture(): void
    {
        $before = $this->fingerprint();
        foreach (['show', 'contact', 'identity', 'request', 'code'] as $action) {
            ($action === 'show' ? $this->get($this->url()) : $this->post($this->url($action), []))->assertRedirect(route('login'));
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_owner_sees_private_forms_and_existing_pages_link_safely(): void
    {
        $this->signIn()->get($this->url())->assertOk()->assertSee('Phone verification')->assertSee('Private identity')->assertDontSee('FIXTURE-');
        $this->get(route('merchants.dashboard', ['merchant' => $this->merchant->id]))->assertOk()->assertSee($this->url(), false);
        $this->get(route('merchants.onboarding', ['merchant' => $this->merchant->id]))->assertOk()->assertSee($this->url(), false);
        $this->assertSame(0, DB::table('merchant_onboarding_contexts')->count());
    }

    public static function nonOwners(): array
    {
        return [['manager'], ['staff'], ['chief_admin'], ['admin']];
    }

    #[DataProvider('nonOwners')]
    public function test_other_roles_cannot_read_or_write_sensitive_workflow(string $role): void
    {
        $actor = User::factory()->create();
        if (in_array($role, ['manager', 'staff'], true)) {
            MerchantMembership::factory()->create(['merchant_id' => $this->merchant->id, 'user_id' => $actor->id, 'role' => $role]);
        } else {
            PlatformRoleAssignment::factory()->create(['user_id' => $actor->id, 'role' => $role]);
        }
        $before = $this->fingerprint();
        $this->signIn($actor);
        $status = in_array($role, ['manager', 'staff'], true) ? 403 : 404;
        $this->get($this->url())->assertStatus($status);
        foreach (['contact', 'identity', 'request', 'code'] as $action) {
            $this->postJson($this->url($action), ['role' => 'owner', 'nin' => 'DO-NOT-ECHO'])->assertStatus($status)->assertDontSee('DO-NOT-ECHO');
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_cross_tenant_route_and_forged_context_are_denied(): void
    {
        $other = Merchant::factory()->create();
        $this->signIn()->get($this->url('show', $other))->assertNotFound();
        $this->postJson($this->url('contact'), ['merchant_id' => $other->id, 'phone' => '+255712345678', 'context_version' => 0, 'password' => 'fixture-password'])->assertUnprocessable();
        $this->assertSame(0, DB::table('merchant_contact_versions')->count());
    }

    public static function accountStates(): array
    {
        return [['suspended'], ['closed'], ['unverified'], ['stale_version'], ['revoked'], ['downgraded']];
    }

    #[DataProvider('accountStates')]
    public function test_fresh_account_and_owner_checks_deny_changed_authority(string $state): void
    {
        $this->signIn();
        $this->owner->load('merchantMemberships');
        if ($state === 'revoked') {
            DB::table('merchant_memberships')->where('user_id', $this->owner->id)->update(['status' => 'revoked', 'revoked_at' => now()]);
        } elseif ($state === 'downgraded') {
            DB::table('merchant_memberships')->where('user_id', $this->owner->id)->update(['role' => 'staff']);
        } elseif ($state === 'unverified') {
            DB::table('users')->where('id', $this->owner->id)->update(['email_verified_at' => null]);
        } elseif ($state === 'stale_version') {
            DB::table('users')->where('id', $this->owner->id)->update(['auth_version' => 2]);
        } else {
            DB::table('users')->where('id', $this->owner->id)->update(['account_status' => $state]);
        }
        $before = $this->fingerprint();
        $response = $this->get($this->url());
        $this->assertContains($response->status(), [302, 403, 404]);
        $this->assertSame($before, $this->fingerprint());
    }

    public static function merchantStates(): array
    {
        return [['suspended'], ['closed'], ['pending_review'], ['missing_draft']];
    }

    #[DataProvider('merchantStates')]
    public function test_restricted_merchant_or_application_is_read_only(string $state): void
    {
        if (in_array($state, ['suspended', 'closed'], true)) {
            DB::table('merchants')->where('id', $this->merchant->id)->update(['operational_status' => $state]);
        } else {
            DB::table('merchant_applications')->where('merchant_id', $this->merchant->id)->update(['status' => 'pending_review', 'current_slot' => $state === 'missing_draft' ? null : 1]);
        }
        $before = $this->fingerprint();
        $this->signIn()->get($this->url())->assertOk()->assertSee('Verification changes are on hold');
        $this->postJson($this->url('contact'), ['phone' => '+255712345678', 'context_version' => 0, 'password' => 'fixture-password'])->assertConflict();
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_default_dependencies_are_unavailable_and_never_write(): void
    {
        $this->app->instance(VerificationEnvironment::class, new VerificationEnvironment);
        $before = $this->fingerprint();
        $this->signIn()->get($this->url())->assertOk()->assertSee('Capture and delivery are unavailable');
        $this->postJson($this->url('contact'), ['phone' => '+255712345678', 'password' => 'fixture-password', 'context_version' => 0])->assertStatus(503);
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(0, $this->environment->sends);
    }

    public function test_missing_c_schema_or_ledger_fails_closed(): void
    {
        DB::table('migrations')->where('migration', '2026_11_07_200000_activate_b32_security_contract')->delete();
        $this->signIn()->get($this->url())->assertOk()->assertSee('Capture and delivery are unavailable');
        $this->assertFalse(app(VerificationReadiness::class)->ready());
        DB::statement('DROP TABLE merchant_onboarding_contexts');
        $this->postJson($this->url('contact'), ['phone' => '+255712345678', 'context_version' => 0, 'password' => 'fixture-password'])->assertStatus(503);
    }

    public function test_schema_drift_or_missing_key_denies_before_capture(): void
    {
        $this->environment->keys['nin'] = 'bad';
        $this->assertFalse(app(VerificationReadiness::class)->ready());
        $this->environment->keys['nin'] = random_bytes(32);
        DB::statement('DROP TRIGGER r06_pending_discharge');
        $this->assertFalse(app(VerificationReadiness::class)->ready());
        $this->assertSame(0, DB::table('merchant_contact_versions')->count());
    }

    public function test_contact_capture_is_encrypted_and_requires_password_and_expected_version(): void
    {
        $this->signIn()->postJson($this->url('contact'), ['phone' => '0712345678', 'password' => 'wrong', 'context_version' => 0])->assertUnprocessable();
        $this->post($this->url('contact'), ['phone' => '0712345678', 'password' => 'fixture-password', 'context_version' => 0])->assertRedirect($this->url());
        $row = DB::table('merchant_contact_versions')->sole();
        $this->assertStringNotContainsString('712345678', $row->phone_ciphertext);
        $plain = app(VerificationCipher::class)->decrypt('phone', $row->phone_ciphertext, $this->service()->binding($this->merchant->id, $row->id, 'application_phone', 1, $this->owner->id), $row->phone_key_ref);
        $this->assertSame('+255712345678', $plain);
        $before = $this->fingerprint();
        $this->postJson($this->url('contact'), ['phone' => '+255712345679', 'password' => 'fixture-password', 'context_version' => 0])->assertConflict();
        $this->assertSame($before, $this->fingerprint());
    }

    public function test_identity_is_encrypted_write_only_and_not_kyc_success(): void
    {
        $input = ['nin' => 'FIXTURE-ABCD1234', 'subject_claim' => 'individual_owner', 'declaration' => '1', 'context_version' => 0, 'password' => 'fixture-password'];
        $this->signIn()->post($this->url('identity'), $input)->assertRedirect($this->url());
        $row = DB::table('merchant_identity_records')->sole();
        $this->assertStringNotContainsString($input['nin'], $row->nin_ciphertext);
        $this->assertSame('fixture-only-v1', $row->syntax_policy_version);
        $this->get($this->url())->assertOk()->assertSee('identity not independently verified')->assertDontSee($input['nin'])->assertDontSee('ABCD1234');
        $this->assertNull($this->app['session']->getOldInput('nin'));
        $this->assertSame('onboarding', DB::table('merchants')->value('operational_status'));
    }

    public static function invalidIdentity(): array
    {
        return [[''], ['123'], ["FIXTURE-ABCD1234\n"], ['<script>alert(1)</script>']];
    }

    #[DataProvider('invalidIdentity')]
    public function test_invalid_or_incomplete_identity_never_persists_or_flashes(string $nin): void
    {
        $before = $this->fingerprint();
        $this->signIn()->post($this->url('identity'), ['nin' => $nin, 'subject_claim' => 'individual_owner', 'declaration' => '1', 'context_version' => 0, 'password' => 'fixture-password'])->assertStatus($nin === '123' ? 422 : ($nin === '' || str_contains($nin, "\n") ? 302 : 422));
        $this->assertSame($before, $this->fingerprint());
        $this->assertEmpty($this->app['session']->get('_old_input', []));
    }

    public function test_missing_declaration_and_unapproved_syntax_are_not_substituted(): void
    {
        $this->signIn()->postJson($this->url('identity'), ['nin' => 'FIXTURE-ABCD1234', 'subject_claim' => 'individual_owner', 'context_version' => 0, 'password' => 'fixture-password'])->assertUnprocessable();
        $this->environment->identity = false;
        $this->get($this->url())->assertOk()->assertSee('Identity entry is unavailable');
        $this->postJson($this->url('identity'), ['nin' => 'FIXTURE-ABCD1234', 'subject_claim' => 'individual_owner', 'declaration' => '1', 'context_version' => 0, 'password' => 'fixture-password'])->assertStatus(503);
        $this->assertSame(0, DB::table('merchant_identity_records')->count());
    }

    public function test_otp_success_is_single_use_bound_and_explicitly_simulated(): void
    {
        $this->capture();
        $id = $this->issue();
        $code = $this->environment->lastCode;
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/D', $code);
        $this->assertSame('active', DB::table('phone_otp_challenges')->value('state'));
        $this->assertStringNotContainsString($code, json_encode(DB::table('phone_otp_challenges')->sole()));
        $this->assertTrue($this->service()->verify($this->owner, $this->merchant, $id, $code));
        $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $id, $code));
        $this->assertSame(1, DB::table('contact_verification_proofs')->count());
        $this->assertSame('simulated', DB::table('contact_verification_proofs')->value('provenance'));
        $this->signIn()->get($this->url())->assertSee('Simulated test proof only; not real verification')->assertDontSee($code);
        $this->environment->time += 1800000;
        $this->assertSame('Not verified or proof expired', $this->service()->status($this->owner, $this->merchant)['phoneProof']);
    }

    public function test_invalid_codes_count_and_expired_codes_do_not_compare_or_succeed(): void
    {
        $this->capture();
        $id = $this->issue();
        $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $id, $this->wrongCode()));
        $this->assertSame(1, DB::table('phone_otp_challenges')->value('failed_attempts'));
        $this->environment->time += 300000;
        $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $id, $this->environment->lastCode));
        $this->assertSame('expired', DB::table('phone_otp_challenges')->value('state'));
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
    }

    public function test_request_uuid_cooldown_and_rolling_send_limits_are_persistent(): void
    {
        $this->capture();
        $request = (string) Str::uuid();
        $id = $this->service()->requestCode($this->owner, $this->merchant, $request, '127.0.0.1');
        $this->assertNotNull($id);
        $this->assertNull($this->service()->requestCode($this->owner, $this->merchant, $request, '127.0.0.1'));
        $this->assertNull($this->service()->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1'));
        $this->assertSame(1, $this->environment->sends);
        for ($i = 0; $i < 2; $i++) {
            $this->environment->time += 60000;
            $this->assertNotNull($this->issue());
        }
        $this->environment->time += 60000;
        $this->assertNull($this->service()->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1'));
        $this->assertSame(3, $this->environment->sends);
        $this->assertSame(3, DB::table('otp_delivery_intents')->count());
    }

    public function test_five_bad_attempts_are_durable_when_audit_is_down_and_pending_never_expires(): void
    {
        $this->capture();
        $id = $this->issue();
        DB::unprepared("CREATE TRIGGER fixture_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'Fixture outage'); END");
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $id, $this->wrongCode()));
        }
        $limit = DB::table('verification_limit_state')->sole();
        $this->assertNotNull($limit->pending_audit_event_id);
        $this->assertSame(5, DB::table('phone_otp_challenges')->value('failed_attempts'));
        $this->assertSame('exhausted', DB::table('phone_otp_challenges')->value('state'));
        $before = $this->fingerprint();
        $this->environment->time += 3600000;
        $this->assertNull($this->service()->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1'));
        $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $id, $this->environment->lastCode));
        $this->assertSame($before, $this->fingerprint());
        DB::statement('DROP TRIGGER fixture_audit_outage');
        $this->assertTrue(app(VerificationAuditReconciler::class)->reconcile($limit->scope_key, $limit->scope_key_version, $limit->pending_audit_event_id));
        $this->assertFalse(app(VerificationAuditReconciler::class)->reconcile($limit->scope_key, $limit->scope_key_version, $limit->pending_audit_event_id));
        $this->assertNull(DB::table('verification_limit_state')->value('pending_audit_event_id'));
        $audit = DB::table('audit_logs')->where('id', $limit->pending_audit_event_id)->sole();
        $this->assertSame('{"reason_code":"exhausted"}', $audit->metadata);
        $this->assertSame($limit->pending_audit_occurred_at, $audit->occurred_at);
    }

    public function test_ten_failures_across_contact_versions_do_not_reset_the_scope(): void
    {
        $this->capture();
        for ($round = 0; $round < 2; $round++) {
            $id = $this->issue();
            for ($i = 0; $i < 5; $i++) {
                $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $id, $this->wrongCode()));
            }
            $this->environment->time += 60000;
            if ($round === 0) {
                $this->capture();
            }
        }
        $row = DB::table('verification_limit_state')->sole();
        $this->assertNotNull($row->failure_10_at);
        $this->assertSame(1, DB::table('verification_limit_state')->count());
        $this->assertNull($this->service()->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1'));
        $this->assertTrue(DB::table('audit_logs')->where('metadata', '{"reason_code":"aggregate_exhausted"}')->exists());
    }

    public function test_contact_change_revokes_proof_and_old_code_without_exposing_phone(): void
    {
        $this->capture();
        $id = $this->issue();
        $code = $this->environment->lastCode;
        $this->assertTrue($this->service()->verify($this->owner, $this->merchant, $id, $code));
        $this->capture('+255712345679');
        $this->assertSame('revoked', DB::table('contact_verification_proofs')->value('state'));
        $this->assertSame('retired', DB::table('merchant_contact_versions')->orderBy('version')->value('state'));
        $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $id, $code));
        $this->signIn()->get($this->url())->assertDontSee('712345678')->assertDontSee('712345679');
    }

    public static function deliveryFailures(): array
    {
        return [['unknown'], ['rejected'], ['budget'], ['revoked'], ['lease_expired'], ['suspended']];
    }

    #[DataProvider('deliveryFailures')]
    public function test_delivery_ambiguity_or_authority_loss_never_creates_proof(string $outcome): void
    {
        $this->capture();
        if ($outcome === 'budget') {
            $this->environment->budget = false;
        } elseif ($outcome === 'revoked') {
            $this->environment->duringDelivery = fn () => DB::table('merchant_memberships')->where('user_id', $this->owner->id)->update(['status' => 'revoked']);
        } elseif ($outcome === 'lease_expired') {
            $this->environment->duringDelivery = function (): void {
                $this->environment->time += 30000;
            };
        } elseif ($outcome === 'suspended') {
            $this->environment->duringDelivery = fn () => DB::table('merchants')->where('id', $this->merchant->id)->update(['operational_status' => 'suspended']);
        } else {
            $this->environment->outcome = $outcome;
        }
        $this->issue();
        $this->assertNotSame('active', DB::table('phone_otp_challenges')->value('state'));
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
        $this->assertSame($outcome === 'budget' ? 0 : 1, $this->environment->sends);
    }

    public function test_crypto_contract_is_bidirectionally_compatible_and_context_bound(): void
    {
        $keys = new SyntheticKeys;
        foreach (['nin', 'phone', 'otp', 'contact'] as $domain) {
            $this->environment->keys[$domain] = $keys->resolve($domain, $domain.'.v1');
        }
        $id = (string) Str::ulid();
        $binding = $this->service()->binding($this->merchant->id, $id, 'identity_declaration', 1, $this->owner->id);
        $cipher = app(VerificationCipher::class);
        $legacy = new SyntheticCipher($keys);
        $envelope = $cipher->encrypt('nin', 'FIXTURE-ABCD1234', $binding, 'nin.v1');
        $this->assertSame('FIXTURE-ABCD1234', $legacy->decrypt('nin', $envelope, $binding, 'nin.v1')->forCryptoAssertion());
        $this->assertSame('FIXTURE-ABCD1234', $cipher->decrypt('nin', $legacy->encrypt('nin', 'FIXTURE-ABCD1234', $binding, 'nin.v1'), $binding, 'nin.v1'));
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Sensitive value unavailable.');
        $cipher->decrypt('nin', $envelope, [...$binding, 'actor' => $this->owner->id + 1], 'nin.v1');
    }

    public function test_debug_errors_and_validation_never_echo_or_flash_sensitive_input(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        $this->signIn()->post($this->url('identity'), ['nin' => 'SECRET-FIXTURE', 'password' => 'secret', 'raw_identity' => 'NESTED-SECRET'])->assertRedirect($this->url());
        $this->assertEmpty($this->app['session']->get('_old_input', []));
        $this->environment->keys['nin'] = 'bad';
        $this->postJson($this->url('identity'), ['nin' => 'FIXTURE-ABCD1234', 'password' => 'fixture-password', 'subject_claim' => 'individual_owner', 'declaration' => '1', 'context_version' => 0])->assertStatus(503)->assertDontSee('FIXTURE-ABCD1234')->assertDontSee('fixture-password');
    }

    public function test_post_endpoints_require_csrf_and_other_methods_are_unavailable(): void
    {
        $this->signIn();
        $this->app->instance('env', 'local');
        $this->post($this->url('request'), ['request_id' => (string) Str::uuid()])->assertStatus(419);
        $this->app->instance('env', 'testing');
        foreach (['contact', 'identity', 'request', 'code'] as $action) {
            $this->get($this->url($action))->assertStatus(405);
        }
        $this->assertSame(0, $this->environment->sends);
    }

    public function test_responsive_view_has_write_only_inputs_safe_headers_and_canonical_assets(): void
    {
        $this->environment->enabled = false;
        $response = $this->signIn()->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Frame-Options', 'DENY');
        $doc = new DOMDocument;
        @$doc->loadHTML($response->getContent(), LIBXML_NONET);
        $xpath = new DOMXPath($doc);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(0, $xpath->query('//script | //iframe')->length);
        $this->assertSame(0, $xpath->query('//input[@name="nin"]/@value | //input[@name="phone"]/@value | //input[@name="code"]/@value')->length);
        $this->assertSame(3, $xpath->query('//fieldset[@disabled]')->length);
        $this->assertSame(file_get_contents(base_path('../frontend/assets/xlipa-verification.css')), file_get_contents(public_path('assets/xlipa-verification.css')));
        if (getenv('XLIPA_PD03_VISUAL') === '1') {
            $directory = storage_path('framework/testing/pd03-visual-'.bin2hex(random_bytes(10)));
            mkdir($directory, 0700, true);
            foreach (['blocked' => false, 'ready' => true] as $name => $enabled) {
                $this->environment->enabled = $enabled;
                $html = $this->get($this->url())->assertOk()->getContent();
                foreach (['xlipa-site.css', 'xlipa-dashboard.css', 'xlipa-verification.css'] as $css) {
                    $html = str_replace('<link rel="stylesheet" href="'.asset('assets/'.$css).'">', '<style>'.file_get_contents(base_path('../frontend/assets/'.$css)).'</style>', $html);
                }
                $html = str_replace(asset('assets/xlipa-favicon.svg'), 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(base_path('../frontend/assets/xlipa-favicon.svg'))), $html);
                file_put_contents($directory.'/'.$name.'.html', $html);
            }
            file_put_contents($directory.'/fixture.json', json_encode(['synthetic' => true, 'feature' => 'PD-03'], JSON_THROW_ON_ERROR));
            fwrite(STDOUT, 'PD03_VISUAL='.$directory.PHP_EOL);
        }
    }

    public function test_http_request_and_code_submission_produce_only_a_simulated_bound_proof(): void
    {
        $this->capture();
        $this->signIn()->post($this->url('request'), ['request_id' => (string) Str::uuid()])->assertRedirect($this->url());
        $challenge = DB::table('phone_otp_challenges')->value('id');
        $this->post($this->url('code'), ['challenge' => $challenge, 'code' => $this->environment->lastCode])->assertRedirect($this->url());
        $this->get($this->url())->assertOk()->assertSee('Simulated test proof only; not real verification');
        $this->post($this->url('code'), ['challenge' => $challenge, 'code' => $this->environment->lastCode])->assertRedirect($this->url());
        $this->assertSame(1, DB::table('contact_verification_proofs')->count());
        $this->assertSame('unverified', DB::table('merchants')->value('verification_status'));
    }

    public function test_request_flood_is_throttled_without_sends_or_capture(): void
    {
        $this->signIn();
        for ($i = 0; $i < 20; $i++) {
            $this->postJson($this->url('request'), [])->assertUnprocessable();
        }
        $this->postJson($this->url('request'), [])->assertStatus(429);
        $this->assertSame(0, $this->environment->sends);
        $this->assertSame(0, DB::table('phone_otp_challenges')->count());
    }

    public function test_hourly_resend_quota_and_clock_rollback_cannot_release_limits(): void
    {
        $this->capture();
        for ($i = 0; $i < 3; $i++) {
            $this->assertNotNull($this->issue());
            $this->environment->time += 60000;
        }
        $this->environment->time += 900000;
        for ($i = 0; $i < 2; $i++) {
            $this->assertNotNull($this->issue());
            $this->environment->time += 60000;
        }
        $this->assertNull($this->service()->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1'));
        $lastObserved = DB::table('verification_limit_state')->value('last_observed_at');
        $this->environment->time -= 1800000;
        $this->assertNull($this->service()->requestCode($this->owner, $this->merchant, (string) Str::uuid(), '127.0.0.1'));
        $this->assertSame($lastObserved, DB::table('verification_limit_state')->value('last_observed_at'));
        $this->assertSame(5, $this->environment->sends);
    }

    public function test_pending_audit_blocks_capture_and_exact_conflicting_audit_cannot_discharge(): void
    {
        $this->capture();
        $id = $this->issue();
        DB::unprepared("CREATE TRIGGER fixture_audit_outage BEFORE INSERT ON audit_logs WHEN NEW.event_type='otp.changed' BEGIN SELECT RAISE(ABORT,'Fixture outage'); END");
        for ($i = 0; $i < 5; $i++) {
            $this->service()->verify($this->owner, $this->merchant, $id, $this->wrongCode());
        }
        $before = $this->fingerprint();
        $version = DB::table('merchant_onboarding_contexts')->value('context_version');
        $this->signIn()->postJson($this->url('contact'), ['phone' => '+255712345679', 'password' => 'fixture-password', 'context_version' => $version])->assertConflict();
        $this->assertSame($before, $this->fingerprint());
        DB::statement('DROP TRIGGER fixture_audit_outage');
        $row = DB::table('verification_limit_state')->sole();
        DB::table('audit_logs')->insert(['id' => $row->pending_audit_event_id, 'event_type' => 'otp.changed', 'actor_user_id' => $this->owner->id, 'merchant_id' => $this->merchant->id, 'request_id' => $row->pending_audit_request_id, 'metadata' => '{"reason_code":"wrong"}', 'occurred_at' => $row->pending_audit_occurred_at]);
        try {
            app(VerificationAuditReconciler::class)->reconcile($row->scope_key, $row->scope_key_version, $row->pending_audit_event_id);
            $this->fail('Conflicting audit must deny discharge.');
        } catch (\LogicException $error) {
            $this->assertSame('Verification audit conflict.', $error->getMessage());
        }
        $this->assertSame($row->pending_audit_event_id, DB::table('verification_limit_state')->value('pending_audit_event_id'));
    }

    public function test_key_domain_aliasing_and_epoch_substitution_fail_closed(): void
    {
        $this->environment->keys['nin'] = $this->environment->keys['phone'];
        $this->assertFalse(app(VerificationReadiness::class)->ready());
        $this->environment->keys['nin'] = random_bytes(32);
        $this->capture();
        $this->environment->keys['contact'] = random_bytes(32);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Verification unavailable.');
        $this->issue();
    }

    public function test_nested_foreign_challenge_cannot_verify_the_current_tenant(): void
    {
        $this->capture();
        $own = $this->issue();
        $other = Merchant::factory()->create(['created_by_user_id' => $this->owner->id]);
        $this->service()->capture($this->owner, $other, 'contact', ['phone' => '+255712345679', 'context_version' => 0]);
        $foreign = $this->service()->requestCode($this->owner, $other, (string) Str::uuid(), '127.0.0.1');
        $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $foreign, $this->environment->lastCode));
        $this->assertSame('active', DB::table('phone_otp_challenges')->where('id', $own)->value('state'));
        $this->assertSame('active', DB::table('phone_otp_challenges')->where('id', $foreign)->value('state'));
        $this->assertSame(0, DB::table('contact_verification_proofs')->count());
    }

    public function test_unknown_delivery_is_not_retried_by_replaying_the_request_uuid(): void
    {
        $this->capture();
        $request = (string) Str::uuid();
        $this->environment->duringDelivery = function (): void {
            throw new \RuntimeException('SENSITIVE-TRANSPORT-DETAIL');
        };
        $id = $this->service()->requestCode($this->owner, $this->merchant, $request, '127.0.0.1');
        $this->assertSame('unknown', DB::table('otp_delivery_intents')->value('state'));
        $this->assertSame('delivery_unknown', DB::table('phone_otp_challenges')->value('state'));
        $this->environment->time += 60000;
        $this->assertNull($this->service()->requestCode($this->owner, $this->merchant, $request, '127.0.0.1'));
        $this->assertSame(1, $this->environment->sends);
        $this->assertFalse($this->service()->verify($this->owner, $this->merchant, $id, $this->environment->lastCode));
        $this->signIn()->get($this->url())->assertOk()->assertDontSee('SENSITIVE-TRANSPORT-DETAIL');
    }

    public function test_contact_ciphertext_tampering_denies_without_dispatch_or_plaintext_error(): void
    {
        $this->capture();
        DB::table('merchant_contact_versions')->update(['phone_ciphertext' => '{"tampered":true}', 'envelope_revision' => 2, 'row_version' => 2]);
        $before = $this->fingerprint();
        $this->signIn()->postJson($this->url('request'), ['request_id' => (string) Str::uuid()])->assertStatus(503)->assertDontSee('tampered');
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(0, $this->environment->sends);
    }

    public function test_writer_transaction_is_framework_tracked_and_dispatch_is_outside_it(): void
    {
        $store = app(VerificationStore::class);
        $before = $this->fingerprint();
        try {
            $store->run(function (): void {
                $this->assertSame(1, DB::connection()->transactionLevel());
                DB::table('merchant_onboarding_contexts')->insert(['merchant_id' => $this->merchant->id, 'updated_at' => $this->environment->time]);
                throw new \RuntimeException('Fixture rollback');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('Fixture rollback', $error->getMessage());
        }
        $this->assertSame(0, DB::connection()->transactionLevel());
        $this->assertSame($before, $this->fingerprint());
        $this->capture();
        $this->environment->duringDelivery = function (): void {
            $this->assertSame(0, DB::connection()->transactionLevel());
        };
        $this->issue();
        $this->assertSame('active', DB::table('phone_otp_challenges')->value('state'));
    }

    public function test_writer_is_reserved_before_callback_on_a_generated_disposable_database(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlipa-pd03-');
        $this->assertNotFalse($path);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($path)));
        unlink($path);
        $other = null;
        try {
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $path, 'database.connections.sqlite.busy_timeout' => 0]);
            $other = new \PDO('sqlite:'.$path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $other->exec('PRAGMA busy_timeout=0');
            app(VerificationStore::class)->run(function () use ($other): void {
                $this->assertSame(1, DB::connection()->transactionLevel());
                try {
                    $other->exec('BEGIN IMMEDIATE');
                    $this->fail('Second writer must be refused before the operation callback.');
                } catch (\PDOException $error) {
                    $this->assertContains($error->errorInfo[1], [5, 6]);
                }
            });
            $other->exec('BEGIN IMMEDIATE');
            $other->exec('ROLLBACK');
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
}
