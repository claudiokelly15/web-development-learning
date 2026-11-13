<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new LogicException('Authentication tests require disposable in-memory SQLite.');
        }
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        B32CRehearsal::foundation();
        Notification::fake();
    }

    private function registration(array $overrides = []): array
    {
        return array_replace(['fullName' => 'Synthetic Owner', 'businessName' => 'Synthetic Business', 'email' => 'owner@example.test', 'phone' => '0675760723', 'password' => 'SyntheticPassword12', 'password_confirmation' => 'SyntheticPassword12', 'terms' => '1'], $overrides);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_replace(['password' => Hash::make('SyntheticPassword12')], $attributes));
    }

    private function signedVerification(User $user, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), ['id' => $user->id, 'hash' => sha1($user->email)]);
    }

    private function loginAs(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    public function test_registration_atomically_creates_only_draft_records_and_no_login(): void
    {
        $this->post('/register', $this->registration(['email' => ' OWNER@EXAMPLE.TEST ']))->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $user = User::sole();
        $this->assertSame('owner@example.test', $user->email);
        $this->assertTrue(Hash::check('SyntheticPassword12', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertSame('active', $user->account_status->value);
        $this->assertSame(1, $user->auth_version);
        $this->assertNotNull($user->terms_accepted_at);
        $merchant = Merchant::sole();
        $this->assertSame('+255675760723', $merchant->business_phone);
        $this->assertNull($merchant->registration_number);
        $this->assertNull($merchant->business_email);
        $this->assertSame('onboarding', $merchant->operational_status->value);
        $this->assertSame('unverified', $merchant->verification_status->value);
        $this->assertSame('owner', $merchant->memberships()->sole()->role->value);
        $this->assertSame('draft', $merchant->applications()->sole()->status->value);
        $this->assertSame('merchant.created', AuditLog::sole()->event_type->value);
        $this->assertSame(0, DB::table('platform_role_assignments')->count());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_duplicate_normalized_email_has_identical_guest_outcome_and_no_new_records(): void
    {
        $first = $this->post('/register', $this->registration());
        $status = session('status');
        $second = $this->post('/register', $this->registration(['email' => 'OWNER@EXAMPLE.TEST']));
        $this->assertSame($first->getStatusCode(), $second->getStatusCode());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertSame($status, session('status'));
        $this->assertSame(1, User::count());
        $this->assertSame(1, Merchant::count());
        $this->assertSame(1, AuditLog::count());
        $this->assertGuest();
        Notification::assertSentTimes(VerifyEmail::class, 1);
    }

    public function test_invalid_registration_does_not_persist_or_flash_passwords(): void
    {
        $this->from('/xlipacreateaccount.html')->post('/register', $this->registration(['password' => 'short', 'password_confirmation' => 'different', 'terms' => '0', 'phone' => 'not-a-phone']))
            ->assertSessionHasErrors(['password', 'terms', 'phone'])->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertSame(0, User::count());
        Notification::assertNothingSent();
    }

    public function test_overlong_bcrypt_input_and_privilege_escalation_are_rejected(): void
    {
        $password = str_repeat('x', 73);
        $this->post('/register', $this->registration(['password' => $password, 'password_confirmation' => $password, 'role' => 'chief_admin', 'is_admin' => true, 'account_status' => 'active', 'merchant_id' => 'forged']))->assertSessionHasErrors(['password', 'role', 'is_admin', 'account_status', 'merchant_id']);
        $this->assertSame(0, User::count());
    }

    public function test_registration_rolls_back_user_and_draft_graph_when_audit_fails(): void
    {
        DB::connection()->beforeExecuting(function (string $query): void {
            if (str_starts_with($query, 'insert into "audit_logs"')) {
                throw new \RuntimeException('Fictional audit outage');
            }
        });
        $this->post('/register', $this->registration())->assertStatus(500);
        foreach (['users', 'merchants', 'merchant_memberships', 'merchant_applications', 'audit_logs'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        Notification::assertNothingSent();
    }

    public function test_login_regenerates_session_and_does_not_enable_remember_me(): void
    {
        $user = $this->user();
        $this->get('/xlipasignin.html');
        $before = session()->getId();
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'SyntheticPassword12', 'remember' => '1'])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertSame(1, session('auth_version'));
        $this->assertFalse(auth()->viaRemember());
    }

    public function test_login_failure_and_suspended_account_use_the_same_message(): void
    {
        $user = $this->user(['account_status' => 'suspended']);
        $this->post('/login', ['email' => $user->email, 'password' => 'SyntheticPassword12'])->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'SyntheticPassword12'])->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_for_existing_and_missing_addresses(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['email' => 'MISSING@EXAMPLE.TEST', 'password' => 'wrong'])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(302);
    }

    public function test_registration_is_rate_limited_even_for_duplicate_addresses(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->registration())->assertStatus(302);
        }
        $this->post('/register', $this->registration())->assertStatus(429);
        $this->assertSame(1, User::count());
    }

    public function test_invalid_registration_attempts_exhaust_ip_limit_before_writes_and_expire(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/register', [])->assertUnprocessable();
        }
        $this->postJson('/register', $this->registration())->assertStatus(429)->assertHeader('Retry-After');
        foreach (['users', 'merchants', 'merchant_memberships', 'merchant_applications', 'audit_logs'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->get('/xlipacreateaccount.html')->assertOk();
        $this->get('/xlipasignin.html')->assertOk();
        Notification::assertNothingSent();
        $this->travel(3601)->seconds();
        $response = $this->post('/register', $this->registration())->assertRedirect(route('login'));
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertSame(1, User::count());
        $this->assertSame(1, Merchant::count());
    }

    public function test_logout_invalidates_session_csrf_and_private_access(): void
    {
        $user = $this->user();
        $this->loginAs($user)->get('/account')->assertOk();
        session()->put('private_probe', 'synthetic');
        $oldId = session()->getId();
        $oldToken = session()->token();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertNull(session('private_probe'));
        $this->get('/account')->assertRedirect(route('login'));
        $this->get('/logout')->assertStatus(405);
    }

    public function test_suspension_auth_version_changes_and_missing_versions_invalidate_sessions(): void
    {
        $user = $this->user();
        $this->loginAs($user)->get('/account')->assertOk();
        DB::table('users')->where('id', $user->id)->update(['auth_version' => 2]);
        $this->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->loginAs($user->fresh())->get('/account')->assertOk();
        DB::table('users')->where('id', $user->id)->update(['account_status' => 'suspended']);
        $this->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
        DB::table('users')->where('id', $user->id)->update(['account_status' => 'active']);
        $this->actingAs($user->fresh())->withSession(['auth_version' => null])->get('/account')->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_restricted_to_verification_and_can_logout(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $this->post('/login', ['email' => $user->email, 'password' => 'SyntheticPassword12'])->assertRedirect(route('verification.notice'));
        $this->get('/account')->assertRedirect(route('verification.notice'));
        $this->get('/email/verify')->assertOk();
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_signed_verification_is_bound_to_user_and_does_not_approve_merchant(): void
    {
        $this->post('/register', $this->registration());
        $user = User::sole();
        $url = $this->signedVerification($user);
        $this->loginAs($user)->get($url)->assertRedirect(route('account'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('unverified', Merchant::sole()->verification_status->value);
        $this->assertSame('onboarding', Merchant::sole()->operational_status->value);
    }

    public function test_tampered_expired_and_other_user_verification_links_are_denied(): void
    {
        $a = $this->user(['email_verified_at' => null]);
        $b = $this->user(['email_verified_at' => null]);
        $this->loginAs($a)->get($this->signedVerification($b))->assertForbidden();
        $this->get($this->signedVerification($a, -1))->assertForbidden();
        $this->get($this->signedVerification($a).'tampered')->assertForbidden();
        $this->assertNull($a->fresh()->email_verified_at);
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $this->loginAs($user);
        for ($i = 0; $i < 6; $i++) {
            $this->post('/email/verification-notification')->assertStatus(302);
        }
        $this->post('/email/verification-notification')->assertStatus(429);
    }

    public function test_reset_requests_are_generic_and_tokens_are_hashed(): void
    {
        $user = $this->user();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('password.request'));
        $status = session('status');
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

            return Hash::check($notification->token, $stored);
        });
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertSessionHas('status', $status);
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertStatus(429);
    }

    public function test_password_reset_is_single_use_and_revokes_old_auth_versions(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);
        $oldRemember = $user->remember_token;
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'NewSyntheticPassword12', 'password_confirmation' => 'NewSyntheticPassword12'];
        $this->post('/reset-password', $data)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewSyntheticPassword12', $fresh->password));
        $this->assertSame(2, $fresh->auth_version);
        $this->assertNotSame($oldRemember, $fresh->remember_token);
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->post('/reset-password', $data)->assertSessionHasErrors('email')->assertSessionMissing('_old_input.token')->assertSessionMissing('_old_input.password');
        $this->actingAs($fresh)->withSession(['auth_version' => 1])->get('/account')->assertRedirect(route('login'));
    }

    public function test_expired_invalid_and_suspended_password_reset_is_denied(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(61)]);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'NewSyntheticPassword12', 'password_confirmation' => 'NewSyntheticPassword12'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->post('/reset-password', [...$data, 'token' => 'invalid-synthetic-token'])->assertSessionHasErrors('email');
        $token = Password::createToken($user);
        DB::table('users')->where('id', $user->id)->update(['account_status' => 'suspended']);
        $this->post('/reset-password', [...$data, 'token' => $token])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('SyntheticPassword12', $user->fresh()->password));
    }

    public function test_account_lists_only_authorized_merchant_memberships(): void
    {
        $a = Merchant::factory()->create(['business_name' => 'OWNED BUSINESS']);
        $b = Merchant::factory()->create(['business_name' => 'PRIVATE OTHER BUSINESS']);
        $this->loginAs($a->creator)->get('/account?merchant_id='.$b->id)->assertOk()->assertSee('OWNED BUSINESS')->assertDontSee('PRIVATE OTHER BUSINESS')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/merchants/'.$b->id)->assertNotFound();
        $this->get('/admin')->assertNotFound();
    }

    public function test_guest_access_and_logout_are_protected(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
        $this->post('/logout')->assertRedirect(route('login'));
        $this->get('/email/verify')->assertRedirect(route('login'));
    }

    public function test_schema_readiness_and_unsafe_mail_configuration_fail_closed(): void
    {
        config(['mail.default' => 'smtp']);
        $this->post('/register', $this->registration())->assertStatus(503);
        $this->assertSame(0, User::count());
        config(['mail.default' => 'array']);
        Schema::shouldReceive('hasColumns')->once()->andReturn(false);
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'SyntheticPassword12'])->assertStatus(503);
    }

    public function test_auth_posts_require_csrf_outside_the_framework_test_bypass(): void
    {
        $middleware = new class(app(), app('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $this->app->instance(PreventRequestForgery::class, $middleware);
        $this->post('/register', $this->registration())->assertStatus(419);
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'SyntheticPassword12'])->assertStatus(419);
        $this->get('/xlipasignin.html')->assertOk();
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'SyntheticPassword12', '_token' => session()->token()])->assertStatus(302);
    }

    public function test_guest_verification_link_continues_safely_after_login(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $url = $this->signedVerification($user);
        $this->get($url)->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'SyntheticPassword12'])->assertRedirect($url);
        $this->get($url)->assertRedirect(route('account'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_external_intended_redirect_and_external_referrer_are_not_used(): void
    {
        $user = $this->user();
        $this->withSession(['url.intended' => 'https://untrusted.example/collect'])->post('/login', ['email' => $user->email, 'password' => 'SyntheticPassword12'])->assertRedirect(route('account'));
        $this->post('/logout');
        $this->from('https://untrusted.example/collect')->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('password.request'));
    }

    public function test_auth_forms_render_real_actions_and_escape_old_input(): void
    {
        $this->get('/xlipasignin.html')->assertOk()->assertSee('action="'.route('login.store').'"', false)->assertDontSee('not connected');
        $this->withSession(['_old_input' => ['fullName' => '<img src=x onerror=alert(1)>']])->get('/xlipacreateaccount.html')
            ->assertOk()->assertSee('action="'.route('register.store').'"', false)->assertSee('name="password_confirmation"', false)
            ->assertSee('name="terms"', false)->assertDontSee('<img src=x onerror=alert(1)>', false)->assertDontSee('Connect this form');
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/synthetic-token?email=owner@example.test')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_null_byte_password_is_a_validation_error_not_a_hashing_exception(): void
    {
        $password = "Synthetic\0Password12";
        $this->post('/register', $this->registration(['password' => $password, 'password_confirmation' => $password]))->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
    }

    public function test_notification_urls_use_canonical_host_even_with_an_untrusted_host_header(): void
    {
        $user = $this->user();
        $this->withServerVariables(['HTTP_HOST' => 'untrusted.example'])->post('/forgot-password', ['email' => $user->email])->assertStatus(302);
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            return parse_url($notification->toMail($user)->actionUrl, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST);
        });
    }

    public function test_login_rejects_bcrypt_truncation_suffixes(): void
    {
        $password = str_repeat('x', 72);
        $user = $this->user(['password' => Hash::make($password)]);
        $this->post('/login', ['email' => $user->email, 'password' => $password.'y'])->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect(route('account'));
    }

    public static function absentBusinessNames(): array
    {
        return ['omitted' => [[]], 'null' => [['businessName' => null]], 'empty' => [['businessName' => '']], 'whitespace' => [['businessName' => " \t\r\n "]]];
    }

    #[DataProvider('absentBusinessNames')]
    public function test_minimal_signup_preserves_the_graph_and_email_only_dashboard_gate(array $name): void
    {
        $input = $this->registration();
        unset($input['businessName']);
        $this->post('/register', [...$input, ...$name])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $user = User::sole();
        $merchant = Merchant::sole();
        $membership = $merchant->memberships()->sole();
        $draft = $merchant->applications()->sole();
        $this->assertSame('', $merchant->business_name);
        $this->assertSame(1, $merchant->profile_version);
        $this->assertSame('+255675760723', $merchant->business_phone);
        $this->assertSame($user->id, $merchant->created_by_user_id);
        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame('owner', $membership->role->value);
        $this->assertSame('active', $membership->status->value);
        $this->assertSame(1, $draft->attempt_number);
        $this->assertSame(1, $draft->current_slot);
        $this->assertSame(1, $draft->profile_version);
        $this->assertSame('draft', $draft->status->value);
        $this->assertNull($draft->submitted_at);
        $this->assertNull($draft->getRawOriginal('profile_snapshot'));
        $this->assertSame('2026-11-06', $user->terms_version);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame('merchant.created', AuditLog::sole()->event_type->value);
        $this->assertSame($draft->id, AuditLog::sole()->application_id);
        $this->assertSame(0, DB::table('platform_role_assignments')->count());
        $this->post('/login', ['email' => $user->email, 'password' => 'SyntheticPassword12'])->assertRedirect(route('verification.notice'));
        $dashboard = route('merchants.dashboard', ['merchant' => $merchant->id]);
        $this->get($dashboard)->assertRedirect(route('verification.notice'));
        $this->get($this->signedVerification($user))->assertRedirect(route('account'));
        foreach ([route('account'), $dashboard, route('merchants.onboarding', ['merchant' => $merchant->id])] as $url) {
            $this->get($url)->assertOk()->assertSee('Business name not provided')->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->assertSame('', $merchant->fresh()->business_name);
        $this->assertSame('onboarding', $merchant->fresh()->operational_status->value);
        $this->assertSame('unverified', $merchant->fresh()->verification_status->value);
    }

    public static function invalidBusinessNames(): array
    {
        return [[['array']], [[]], [['name' => 'forged']], [false], [true], [123], [12.5], [str_repeat('a', 201)]];
    }

    #[DataProvider('invalidBusinessNames')]
    public function test_minimal_signup_rejects_malformed_or_oversize_names(mixed $name): void
    {
        $this->postJson('/register', $this->registration(['businessName' => $name]))->assertUnprocessable()->assertJsonValidationErrors('businessName');
        $this->assertSame(0, User::count());
        $this->assertSame(0, Merchant::count());
        Notification::assertNothingSent();
    }

    public function test_minimal_signup_trims_valid_names_and_preserves_duplicate_privacy(): void
    {
        $input = $this->registration(['businessName' => '  Fictional Store  ']);
        $first = $this->post('/register', $input)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertSame('Fictional Store', Merchant::sole()->business_name);
        $message = session('status');
        unset($input['businessName']);
        $second = $this->post('/register', $input)->assertRedirect(route('login'))->assertSessionHas('status', $message);
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertSame(1, User::count());
        $this->assertSame(1, Merchant::count());
        $this->assertSame(1, AuditLog::count());
        Notification::assertSentTimes(VerifyEmail::class, 1);
        $this->assertGuest();
    }

    public function test_minimal_signup_rolls_back_unnamed_bundle_on_audit_failure(): void
    {
        DB::connection()->beforeExecuting(function (string $query): void {
            if (str_starts_with($query, 'insert into "audit_logs"')) {
                throw new \RuntimeException('Fictional audit outage');
            }
        });
        $input = $this->registration();
        unset($input['businessName']);
        $this->post('/register', $input)->assertStatus(500);
        foreach (['users', 'merchants', 'merchant_memberships', 'merchant_applications', 'audit_logs'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        Notification::assertNothingSent();
    }

    public function test_minimal_signup_form_keeps_other_required_inputs_and_canonical_copy(): void
    {
        $html = $this->get(route('register'))->assertOk()->assertSee('Business name (optional)')->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@name="businessName" and @required]')->length);
        foreach (['fullName', 'email', 'phone', 'password', 'password_confirmation', 'terms'] as $field) {
            $this->assertSame(1, $xpath->query('//input[@name="'.$field.'" and @required]')->length, $field);
        }
        $this->assertSame(file_get_contents(base_path('../frontend/xlipacreateaccount.html')), file_get_contents(resource_path('views/xlipa/xlipacreateaccount.blade.php')));
    }

    public static function requiredMinimalFields(): array
    {
        return [['fullName', 'fullName'], ['email', 'email'], ['phone', 'phone'], ['password', 'password'], ['password_confirmation', 'password'], ['terms', 'terms']];
    }

    #[DataProvider('requiredMinimalFields')]
    public function test_minimal_signup_preserves_required_account_fields(string $field, string $error): void
    {
        $input = $this->registration();
        unset($input['businessName'], $input[$field]);
        $this->postJson('/register', $input)->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertSame(0, User::count());
        Notification::assertNothingSent();
    }
}
