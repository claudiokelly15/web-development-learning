<?php

namespace Tests\Feature;

use App\Actions\Auth\VerifyAccountEmail;
use App\Actions\Merchants\LifecycleMutation;
use App\Actions\Merchants\MerchantVerification;
use App\Actions\Merchants\RevokeMembership;
use App\Actions\Merchants\SubmissionEvidenceSchema;
use App\Actions\Merchants\VerificationEnvironment;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\User;
use App\Notifications\VersionedVerifyEmail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\B32CRehearsal;
use Tests\Support\FakeMerchantVerificationEnvironment;
use Tests\TestCase;

class SubmissionLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        Notification::fake();
        DB::statement('PRAGMA recursive_triggers=ON');
        config(['se1.selected' => realpath(SubmissionEvidenceSchema::artifact())]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SubmissionEvidenceSchema::artifact()], '--realpath' => true, '--no-interaction' => true]));
        $this->assertTrue(SubmissionEvidenceSchema::ready());
    }

    private function signed(User $user, int $version): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $user->id, 'hash' => sha1($user->email), 'binding_version' => $version]);
    }

    public function test_minimal_signup_preserves_se1_readiness_epochs_and_unnamed_dashboard_access(): void
    {
        $this->post('/register', ['fullName' => 'Fictional Owner', 'email' => 'minimal@fixture.test', 'phone' => '0712345678', 'password' => 'FictionalPassword123', 'password_confirmation' => 'FictionalPassword123', 'terms' => '1'])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $user = User::sole();
        $merchant = Merchant::sole();
        $this->assertSame('', $merchant->business_name);
        $this->assertSame(1, $merchant->profile_version);
        $this->assertSame(1, $merchant->applications()->sole()->profile_version);
        $this->assertSame(1, $merchant->memberships()->sole()->authority_version);
        $this->assertSame(1, $user->email_binding_version);
        Notification::assertSentTo($user, VersionedVerifyEmail::class);
        $this->signIn($user)->get($this->signed($user, 1))->assertRedirect(route('account'));
        $this->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertOk()->assertSee('Business name not provided');
        $this->get(route('merchants.applications', ['merchant' => $merchant->id]))->assertOk()->assertSee('An available merchant with a business name is required.');
        $this->postJson(route('merchants.applications.submit', ['merchant' => $merchant->id]), ['application_id' => $merchant->applications()->sole()->id, 'profile_version' => 1, 'context_version' => 0])->assertStatus(503);
        foreach (['merchant_identity_records', 'merchant_contact_versions', 'contact_verification_proofs', 'merchant_application_submission_evidence'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertTrue(SubmissionEvidenceSchema::ready());
        $this->assertSame(1, $merchant->fresh()->profile_version);
        $this->assertSame('draft', $merchant->applications()->sole()->status->value);
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function denied(\Closure $operation, int $status = 409): void
    {
        try {
            $operation();
            $this->fail('Lifecycle mutation should be denied.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_signed_verification_advances_once_and_rejects_stale_links(): void
    {
        $user = User::factory()->unverified()->create();
        $this->assertSame(1, $user->fresh()->email_binding_version);
        $user->sendEmailVerificationNotification();
        Notification::assertSentTo($user, VersionedVerifyEmail::class, function (VersionedVerifyEmail $notification) use ($user): bool {
            $this->assertStringContainsString('binding_version=1', $notification->toMail($user->fresh())->actionUrl);

            return true;
        });
        $this->signIn($user)->get($this->signed($user, 1))->assertRedirect(route('account'));
        $fresh = $user->fresh();
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertSame(2, $fresh->email_binding_version);
        app(VerifyAccountEmail::class)->execute($fresh, 2);
        $this->assertSame(2, $user->fresh()->email_binding_version);
        $this->get($this->signed($user, 1))->assertStatus(409);
        $this->assertSame(2, $user->fresh()->email_binding_version);
    }

    public function test_email_change_away_back_rejects_original_link_and_reverifies_current_epoch(): void
    {
        $user = User::factory()->unverified()->create();
        $old = $this->signed($user, 1);
        DB::table('users')->where('id', $user->id)->where('email_binding_version', 1)->update(['email' => 'changed@fixture.test', 'email_verified_at' => null, 'email_binding_version' => 2]);
        DB::table('users')->where('id', $user->id)->where('email_binding_version', 2)->update(['email' => $user->email, 'email_verified_at' => null, 'email_binding_version' => 3]);
        $this->signIn($user->fresh())->get($old)->assertStatus(409);
        $this->get($this->signed($user->fresh(), 3))->assertRedirect(route('account'));
        $this->assertSame(4, $user->fresh()->email_binding_version);
        $this->assertNull(app('router')->getRoutes()->getByName('email.change'));
    }

    public function test_verification_rechecks_account_identity_and_bound_versions(): void
    {
        $user = User::factory()->unverified()->create();
        $foreign = User::factory()->unverified()->create();
        $this->signIn($foreign)->get($this->signed($user, 1))->assertForbidden();
        $expected = $user->fresh();
        DB::table('users')->where('id', $user->id)->update(['email' => 'other@fixture.test', 'email_binding_version' => 2]);
        $this->denied(fn () => app(VerifyAccountEmail::class)->execute($expected, 1));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->denied(fn () => LifecycleMutation::next(PHP_INT_MAX));
        $this->assertSame(2, $user->fresh()->email_binding_version);
    }

    public function test_registration_creates_consistent_initial_profile_and_versioned_notification(): void
    {
        $this->post('/register', ['fullName' => 'Fixture Owner', 'businessName' => 'Fixture Store', 'email' => 'registration@fixture.test', 'phone' => '0712345678', 'password' => 'FictionalPassword123', 'password_confirmation' => 'FictionalPassword123', 'terms' => '1'])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $user = User::query()->where('email', 'registration@fixture.test')->sole();
        $merchant = Merchant::query()->sole();
        $this->assertSame('+255712345678', $merchant->business_phone);
        $this->assertSame(1, $merchant->profile_version);
        $this->assertSame(1, $merchant->applications()->sole()->profile_version);
        $this->assertSame(1, $merchant->memberships()->sole()->authority_version);
        Notification::assertSentTo($user, VersionedVerifyEmail::class);
        $before = $merchant->getRawOriginal();
        DB::table('merchants')->where('id', $merchant->id)->where('profile_version', 1)->update(['business_phone' => $merchant->business_phone]);
        $this->assertSame($before, $merchant->fresh()->getRawOriginal());
        try {
            DB::table('merchants')->where('id', $merchant->id)->update(['business_name' => 'unversioned']);
            $this->fail('Unversioned edit must fail.');
        } catch (QueryException) {
            $this->assertSame('Fixture Store', $merchant->fresh()->business_name);
        }
    }

    private function ownership(): array
    {
        $merchant = Merchant::factory()->create();
        $second = User::factory()->create();
        $membership = MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $second->id, 'role' => 'owner']);

        return [$merchant, $merchant->creator, $second, $membership];
    }

    private function phoneProof(User $actor, Merchant $merchant): void
    {
        $environment = new FakeMerchantVerificationEnvironment;
        $this->app->instance(VerificationEnvironment::class, $environment);
        $verification = app(MerchantVerification::class);
        $verification->capture($actor, $merchant, 'contact', ['phone' => '+255712345678', 'context_version' => 0]);
        $challenge = $verification->requestCode($actor, $merchant, (string) Str::uuid(), '127.0.0.1');
        $this->assertTrue($verification->verify($actor, $merchant, $challenge, $environment->lastCode));
    }

    public function test_owner_revocation_advances_authority_invalidates_proof_and_preserves_noop(): void
    {
        [$merchant,$owner,$second,$membership] = $this->ownership();
        $this->phoneProof($second, $merchant);
        app(RevokeMembership::class)->execute($owner, $merchant, $membership, 1);
        $this->assertSame(2, $membership->fresh()->authority_version);
        $this->assertSame('revoked', $membership->fresh()->status->value);
        $this->assertSame('revoked', DB::table('contact_verification_proofs')->value('state'));
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'membership.revoked')->count());
        app(RevokeMembership::class)->execute($owner, $merchant, $membership->fresh(), 2);
        $this->assertSame(2, $membership->fresh()->authority_version);
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'membership.revoked')->count());
        $this->denied(fn () => app(RevokeMembership::class)->execute($owner, $merchant, $membership, 1));
        $this->signIn($second)->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertNotFound();
    }

    public function test_cross_tenant_stale_actor_and_last_owner_denials_remain(): void
    {
        [$merchant,$owner,$second,$membership] = $this->ownership();
        $foreign = Merchant::factory()->create();
        try {
            app(RevokeMembership::class)->execute($owner, $foreign, $foreign->memberships()->sole(), 1);
            $this->fail('Foreign merchant authority was accepted.');
        } catch (AuthorizationException) {
            $this->assertSame(1, $membership->fresh()->authority_version);
        }
        $this->denied(fn () => app(RevokeMembership::class)->execute($owner, $merchant, $membership, 9));
        app(RevokeMembership::class)->execute($owner, $merchant, $membership, 1);
        try {
            app(RevokeMembership::class)->execute($owner, $merchant, $merchant->memberships()->where('user_id', $owner->id)->sole(), 1);
            $this->fail('The last owner was revoked.');
        } catch (LogicException) {
            $this->assertSame(1, $merchant->memberships()->where('status', 'active')->count());
        }
    }

    public function test_audit_failure_rolls_back_membership_epoch_and_proof_revocation(): void
    {
        [$merchant,$owner,$second,$membership] = $this->ownership();
        $this->phoneProof($second, $merchant);
        DB::listen(function ($query): void {
            if (str_starts_with($query->sql, 'insert into "audit_logs"') && in_array('membership.revoked', $query->bindings, true)) {
                throw new RuntimeException('Fixture audit acknowledgement failure');
            }
        });
        try {
            app(RevokeMembership::class)->execute($owner, $merchant, $membership, 1);
            $this->fail('Audit failure must propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture audit acknowledgement failure', $error->getMessage());
        }
        $this->assertSame(1, $membership->fresh()->authority_version);
        $this->assertSame('active', $membership->fresh()->status->value);
        $this->assertSame('available', DB::table('contact_verification_proofs')->value('state'));
        $this->assertSame(0, DB::table('audit_logs')->where('event_type', 'membership.revoked')->count());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_password_reset_stays_held_without_mutating_credentials_tokens_or_proofs(): void
    {
        $merchant = Merchant::factory()->create();
        $user = $merchant->creator;
        $this->phoneProof($user, $merchant);
        $token = Password::createToken($user);
        $before = $user->getRawOriginal();
        $proof = (array) DB::table('contact_verification_proofs')->sole();
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'ReplacementFixture123', 'password_confirmation' => 'ReplacementFixture123'])->assertStatus(503);
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertSame($proof, (array) DB::table('contact_verification_proofs')->sole());
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
        $this->assertSame(0, DB::connection()->transactionLevel());
        try {
            DB::table('users')->where('id', $user->id)->update(['password' => Hash::make('ReplacementFixture123'), 'remember_token' => Str::random(60), 'auth_version' => 2]);
            $this->fail('Raw reset must retain the approved lifecycle hold.');
        } catch (QueryException) {
            $this->assertSame($before, $user->fresh()->getRawOriginal());
        }
    }

    public function test_partial_schema_never_falls_back_to_unversioned_writes(): void
    {
        DB::unprepared('DROP TRIGGER se1_email_version');
        $this->denied(fn () => app(LifecycleMutation::class)->versioned(), 503);
    }

    public function test_generated_database_writer_contention_and_stale_verification(): void
    {
        $user = User::factory()->unverified()->create();
        $path = tempnam(sys_get_temp_dir(), 'xlipa-b2-');
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
            try {
                app(VerifyAccountEmail::class)->execute($user, 1);
                $this->fail('Competing writer must fail.');
            } catch (QueryException $error) {
                $this->assertContains($error->errorInfo[1], [5, 6]);
            }
            $this->assertNull($user->fresh()->email_verified_at);
            $other->exec('ROLLBACK');
            app(VerifyAccountEmail::class)->execute($user, 1);
            $this->assertSame(2, $user->fresh()->email_binding_version);
            $this->denied(fn () => app(VerifyAccountEmail::class)->execute($user, 1));
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
