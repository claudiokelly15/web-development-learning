<?php

namespace Tests\Feature;

use App\Actions\Merchants\BusinessNameCompletionSchema as BNC;
use App\Actions\Merchants\ComposedSubmissionSchema;
use App\Actions\Merchants\LifecycleMutation;
use App\Actions\Merchants\RevokeMembership;
use App\Actions\Merchants\SubmissionEvidenceSchema as SE;
use App\Actions\Merchants\VerificationEnvironment;
use App\Actions\Merchants\VerificationReadiness;
use App\Actions\Merchants\WorkflowSchemaReadiness as Workflow;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Notifications\VersionedVerifyEmail;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\B32CRehearsal;
use Tests\Support\FakeMerchantVerificationEnvironment;
use Tests\TestCase;

class WorkflowSchemaReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        Notification::fake();
    }

    private function install(string $profile): void
    {
        if ($profile !== Workflow::FOUNDATION) {
            B32CRehearsal::activate();
        }
        if (in_array($profile, [Workflow::SE, Workflow::BNC], true)) {
            config(['se1.selected' => realpath(SE::artifact())]);
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SE::artifact()], '--realpath' => true, '--no-interaction' => true]));
        }
        if ($profile === Workflow::BNC) {
            config(['bnc1.selected' => realpath(BNC::artifact())]);
            BNC::install();
        }
        config(['workflow_schema.expected_profile' => $profile]);
    }

    private function denied(Closure $operation): void
    {
        try {
            $operation();
            $this->fail('An unadmitted workflow must deny.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame(503, $error->getStatusCode());
            $this->assertStringNotContainsString('SQLSTATE', $error->getMessage());
        }
    }

    public static function profiles(): array
    {
        return [[Workflow::FOUNDATION], [Workflow::C], [Workflow::SE], [Workflow::BNC]];
    }

    #[DataProvider('profiles')]
    public function test_supported_complete_profile_and_capabilities(string $profile): void
    {
        $this->install($profile);
        $this->assertSame($profile, app(Workflow::class)->profile());
        $versioned = in_array($profile, [Workflow::SE, Workflow::BNC], true);
        $this->assertSame($versioned, app(LifecycleMutation::class)->versioned());
        $this->assertSame($profile === Workflow::SE, SE::ready());
        $this->assertSame($profile === Workflow::BNC, ComposedSubmissionSchema::ready());
        if ($versioned) {
            $this->assertSame($profile, app(Workflow::class)->submissionProfile());
        } else {
            $this->denied(fn () => app(Workflow::class)->submissionProfile());
        }
        $calls = 0;
        $this->assertSame('ran', app(LifecycleMutation::class)->run(function () use (&$calls): string {
            $calls++;
            $this->assertSame(1, DB::connection()->transactionLevel());

            return 'ran';
        }));
        $this->assertSame(1, $calls);
        $this->assertFalse(app(VerificationReadiness::class)->ready());
        $this->app->instance(VerificationEnvironment::class, new FakeMerchantVerificationEnvironment);
        $this->assertSame($profile !== Workflow::FOUNDATION, app(VerificationReadiness::class)->ready());
    }

    public static function mismatches(): array
    {
        $cases = [];
        foreach (self::profiles() as [$actual]) {
            foreach (self::profiles() as [$expected]) {
                if ($actual !== $expected) {
                    $cases[] = [$actual, $expected];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('mismatches')]
    public function test_expected_profile_never_downgrades_or_adopts_another_profile(string $actual, string $expected): void
    {
        $this->install($actual);
        config(['workflow_schema.expected_profile' => $expected]);
        $calls = 0;
        $this->denied(fn () => app(Workflow::class)->profile());
        $this->denied(function () use (&$calls): void {
            app(LifecycleMutation::class)->run(function () use (&$calls): void {
                $calls++;
            });
        });
        $this->assertSame(0, $calls);
        $this->assertFalse(app(VerificationReadiness::class)->ready());
    }

    public static function residue(): array
    {
        $cases = [];
        foreach (BNC::objects() as $name => $sql) {
            $residue = match (true) {
                str_starts_with($sql, 'CREATE TABLE') => "CREATE TABLE $name (id TEXT)",
                str_starts_with($sql, 'CREATE UNIQUE INDEX') => "CREATE INDEX $name ON merchants(id)",
                default => "CREATE TRIGGER $name BEFORE UPDATE ON merchants BEGIN SELECT 1; END",
            };
            $cases[$name] = [$residue];
        }

        return [...$cases,
            ['ALTER TABLE users ADD COLUMN email_binding_version INTEGER'],
            ['ALTER TABLE merchant_memberships ADD COLUMN authority_version INTEGER'],
            ['CREATE TABLE merchant_application_submission_evidence(id TEXT)'],
            ["INSERT INTO migrations(migration,batch) VALUES ('".BNC::MIGRATION."',3)"],
            ["INSERT INTO migrations(migration,batch) VALUES ('".SE::MIGRATION."',3)"],
            ['CREATE VIEW unknown_view AS SELECT 1'], ['CREATE TEMP TABLE shadow(id INTEGER)'],
            ["ATTACH DATABASE ':memory:' AS other"], ['ANALYZE'],
            ["INSERT INTO migrations(migration,batch) VALUES ('unknown',3)"],
        ];
    }

    #[DataProvider('residue')]
    public function test_partial_bnc_se_and_unknown_objects_cannot_reach_legacy(string $sql): void
    {
        config(['workflow_schema.expected_profile' => Workflow::FOUNDATION]);
        DB::unprepared($sql);
        $this->denied(fn () => app(LifecycleMutation::class)->versioned());
        $calls = 0;
        $this->denied(function () use (&$calls): void {
            app(LifecycleMutation::class)->run(function () use (&$calls): void {
                $calls++;
            });
        });
        $this->assertSame(0, $calls);
    }

    public static function missingSelections(): array
    {
        return [[null], ['unknown'], [false], [[]]];
    }

    #[DataProvider('missingSelections')]
    public function test_absent_or_untrusted_selection_denies(mixed $selection): void
    {
        config(['workflow_schema.expected_profile' => $selection]);
        $this->denied(fn () => app(Workflow::class)->profile());
    }

    #[DataProvider('profiles')]
    public function test_exact_ledger_rows_and_batches_are_enforced(string $profile): void
    {
        $this->install($profile);
        DB::table('migrations')->where('id', 1)->update(['batch' => 99]);
        $this->denied(fn () => app(Workflow::class)->profile());
    }

    public function test_bnc_profile_is_rechecked_under_reserved_writer_before_callback(): void
    {
        $this->install(Workflow::BNC);
        $calls = 0;
        $changed = false;
        DB::listen(function ($query) use (&$changed): void {
            if (! $changed && str_starts_with($query->sql, 'UPDATE merchant_onboarding_contexts')) {
                $changed = true;
                DB::unprepared('DROP TRIGGER bnc1_witness_insert');
            }
        });
        $this->denied(function () use (&$calls): void {
            app(LifecycleMutation::class)->run(function () use (&$calls): void {
                $calls++;
            });
        });
        $this->assertSame(0, $calls);
        $this->assertSame(0, DB::connection()->transactionLevel());
        $this->assertSame(Workflow::BNC, app(Workflow::class)->profile());
    }

    public function test_profile_selection_is_stable_through_writer_acquisition(): void
    {
        $this->install(Workflow::BNC);
        DB::listen(function ($query): void {
            if (str_starts_with($query->sql, 'UPDATE merchant_onboarding_contexts')) {
                config(['workflow_schema.expected_profile' => Workflow::SE]);
            }
        });
        $this->denied(fn () => app(LifecycleMutation::class)->run(fn () => $this->fail('Callback ran')));
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_registration_email_dashboard_revocation_reset_and_chief_reads_on_bnc(): void
    {
        $this->install(Workflow::BNC);
        $this->post('/register', ['fullName' => 'Fictional Owner', 'email' => 'bnc@fixture.test', 'phone' => '0712345678', 'password' => 'FictionalPassword123', 'password_confirmation' => 'FictionalPassword123', 'terms' => '1'])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $owner = User::sole();
        $merchant = Merchant::sole();
        $this->assertSame('', $merchant->business_name);
        $this->assertSame(1, $merchant->profile_version);
        $this->assertSame(1, $merchant->applications()->sole()->profile_version);
        $this->assertSame(1, $merchant->memberships()->sole()->authority_version);
        Notification::assertSentTo($owner, VersionedVerifyEmail::class);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $owner->id, 'hash' => sha1($owner->email), 'binding_version' => 1]);
        $this->actingAs($owner)->withSession(['auth_version' => 1])->get($url)->assertRedirect(route('account'));
        $this->assertSame(2, $owner->fresh()->email_binding_version);
        $this->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertOk()->assertSee('Business name not provided');
        $second = User::factory()->create();
        $membership = MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $second->id, 'role' => 'owner']);
        app(RevokeMembership::class)->execute($owner->fresh(), $merchant, $membership, 1);
        $this->assertSame(2, $membership->fresh()->authority_version);
        $this->assertSame('revoked', $membership->fresh()->status->value);
        $this->postJson(route('merchants.applications.submit', ['merchant' => $merchant->id]), ['application_id' => $merchant->applications()->sole()->id, 'profile_version' => 1, 'context_version' => 0])->assertStatus(503);
        $chief = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $chief->id, 'role' => 'chief_admin']);
        $this->actingAs($chief)->withSession(['auth_version' => 1])->get(route('admin.applications'))->assertOk();
        $this->post(route('admin.applications'))->assertStatus(405);
        $this->post('/logout');
        $before = (array) DB::table('users')->where('id', $owner->id)->first();
        $this->post('/reset-password', ['token' => 'fictional-invalid-token', 'email' => $owner->email, 'password' => 'NewFictionalPassword123', 'password_confirmation' => 'NewFictionalPassword123'])->assertStatus(503);
        $this->assertSame($before, (array) DB::table('users')->where('id', $owner->id)->first());
        foreach ([BNC::TABLE, BNC::WITNESSES, SE::TABLE, 'merchant_identity_records', 'merchant_contact_versions', 'phone_otp_challenges'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertSame(Workflow::BNC, app(Workflow::class)->profile());
    }

    public function test_partial_profile_blocks_notification_and_registration_without_account_writes(): void
    {
        $owner = User::factory()->unverified()->create();
        DB::unprepared('CREATE TABLE merchant_business_name_completion_witnesses(id TEXT)');
        $this->denied(fn () => $owner->sendEmailVerificationNotification());
        $this->post('/register', ['fullName' => 'Fictional Owner', 'email' => 'blocked@fixture.test', 'phone' => '0712345678', 'password' => 'FictionalPassword123', 'password_confirmation' => 'FictionalPassword123', 'terms' => '1'])->assertStatus(503);
        $this->assertSame(1, User::count());
        $this->assertSame(0, Merchant::count());
        Notification::assertNothingSent();
    }

    public function test_integration_manifest_tampering_denies_without_repair(): void
    {
        $path = Workflow::manifestPath();
        $original = file_get_contents($path);
        try {
            file_put_contents($path, str_replace('BNC-INT-01', 'unknown', $original));
            $this->denied(fn () => app(Workflow::class)->profile());
            $this->assertStringContainsString('unknown', file_get_contents($path));
        } finally {
            file_put_contents($path, $original);
        }
        $this->assertSame(Workflow::FOUNDATION, app(Workflow::class)->profile());
    }
}
