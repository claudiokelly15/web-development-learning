<?php

namespace Tests\Feature;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\BusinessNameCompletionSchema;
use App\Actions\Merchants\DeniedBusinessNameCompletionAdmission;
use App\Actions\Merchants\SubmissionEvidenceSchema;
use App\Actions\Merchants\SubmissionTransaction;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Notifications\VersionedVerifyEmail;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\B32CRehearsal;
use Tests\Support\MerchantOnboardingHttpHarness;
use Tests\TestCase;

class FunctionalMerchantOnboardingTest extends TestCase
{
    private MerchantOnboardingHttpHarness $harness;

    private PDO $ownedPdo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        config(['se1.selected' => realpath(SubmissionEvidenceSchema::artifact())]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SubmissionEvidenceSchema::artifact()], '--realpath' => true, '--no-interaction' => true]));
        config(['bnc1.selected' => realpath(BusinessNameCompletionSchema::artifact())]);
        BusinessNameCompletionSchema::install();
        Notification::fake();
        $this->assertInstanceOf(DeniedBusinessNameCompletionAdmission::class, app(BusinessNameCompletionAdmission::class));
        $this->ownedPdo = DB::connection()->getPdo();
        $this->harness = new MerchantOnboardingHttpHarness($this->ownedPdo);
        $this->harness->register();
    }

    private function url(Merchant $merchant, bool $resolving = false): string
    {
        return '/merchants/'.$merchant->id.'/__isolated-completion'.($resolving ? '/resolve' : '');
    }

    private function journey(): array
    {
        $this->get(route('register'))->assertOk();
        $this->post('/register', ['fullName' => 'Fictional Journey Owner', 'email' => 'journey@fixture.test', 'phone' => '0675760723',
            'password' => 'FictionalPassword12', 'password_confirmation' => 'FictionalPassword12', 'terms' => '1', '_token' => session()->token()])
            ->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertGuest();
        $owner = User::sole();
        $merchant = Merchant::sole();
        $this->assertSame('', $merchant->business_name);
        $this->assertNull($owner->email_verified_at);
        $verificationUrl = null;
        Notification::assertSentTo($owner, VersionedVerifyEmail::class, function (VersionedVerifyEmail $notification) use ($owner, &$verificationUrl): bool {
            $verificationUrl = $notification->toMail($owner)->actionUrl;

            return true;
        });
        $this->post('/login', ['email' => $owner->email, 'password' => 'FictionalPassword12', '_token' => session()->token()])->assertRedirect(route('verification.notice'));
        $this->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertRedirect(route('verification.notice'));
        $this->get($verificationUrl)->assertRedirect(route('account'));
        $this->assertNotNull($owner->fresh()->email_verified_at);
        $this->assertSame(2, $owner->fresh()->email_binding_version);
        $this->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertOk()->assertSee('Business name not provided');
        $this->get(route('merchants.onboarding', ['merchant' => $merchant->id]))->assertOk()->assertSee('data-completion-preview', false);

        return [$owner->fresh(), $merchant->fresh()];
    }

    private function prepare(User $owner, Merchant $merchant, string $name = 'Kijani Studio'): string
    {
        return $this->harness->prepare($owner, $merchant, $name, session()->getId());
    }

    private function body(string $reference, ?string $name = 'Kijani Studio'): string
    {
        $body = '_token='.rawurlencode(session()->token()).'&context_ref='.$reference;

        return $name === null ? $body : $body.'&business_name='.rawurlencode($name);
    }

    private function raw(Merchant $merchant, string $body, bool $resolving = false, string $type = 'application/x-www-form-urlencoded', string $query = '', array $headers = []): TestResponse
    {
        parse_str($body, $parameters);

        return $this->call('POST', $this->url($merchant, $resolving).$query, $parameters, [], [], ['CONTENT_TYPE' => $type, 'HTTP_ACCEPT' => 'application/json', ...$headers], $body);
    }

    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    public function test_real_http_journey_saves_name_once_and_refreshes_existing_dashboard_and_onboarding(): void
    {
        [$owner, $merchant] = $this->journey();
        $original = "  Kijani e\u{0301}  🌿 & Partners  ";
        $name = trim($original, ' ');
        $reference = $this->prepare($owner, $merchant, $original);
        $response = $this->raw($merchant, $this->body($reference, $original));
        $response->assertOk()->assertExactJson(['status' => 'completed'])->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(1, $this->harness->commandCalls);
        $this->assertSame($name, $merchant->fresh()->business_name);
        $this->assertSame(2, $merchant->fresh()->profile_version);
        $draft = $merchant->applications()->sole();
        $this->assertSame(2, $draft->profile_version);
        $this->assertSame('draft', $draft->status->value);
        $this->assertNull($draft->profile_snapshot);
        $this->assertNull($draft->submitted_at);
        $record = DB::table('merchant_business_name_completions')->sole();
        $this->assertSame($record->occurred_at, $merchant->fresh()->getRawOriginal('updated_at'));
        $this->assertSame($record->occurred_at, $draft->getRawOriginal('updated_at'));
        $this->assertSame(1, DB::table('merchant_business_name_completion_witnesses')->where('completion_id', $record->id)->count());
        $this->assertStringNotContainsString($reference, $response->getContent());
        $this->assertStringNotContainsString($name, $response->getContent());
        $this->get(route('merchants.dashboard', ['merchant' => $merchant->id]))->assertOk()->assertSee($name)->assertDontSee('Business name not provided');
        $this->get(route('merchants.onboarding', ['merchant' => $merchant->id]))->assertOk()->assertSee($name)->assertDontSee('data-completion-preview', false);
        $this->raw($merchant, $this->body($reference, $original))->assertStatus(409);
        $this->assertSame(1, $this->harness->commandCalls);
        $second = $this->prepare($owner, $merchant, 'Unapproved rename');
        $this->raw($merchant, $this->body($second, 'Unapproved rename'))->assertStatus(409);
        $this->assertSame($name, $merchant->fresh()->business_name);
        $this->assertSame(1, DB::table('merchant_business_name_completions')->count());
        $this->post(route('merchants.applications.submit', ['merchant' => $merchant->id]), ['_token' => session()->token(), 'application_id' => $draft->id, 'profile_version' => 2, 'context_version' => 0])->assertStatus(503);
    }

    public static function unauthorizedActors(): array
    {
        return [['guest'], ['unverified'], ['manager'], ['staff'], ['foreign'], ['chief'], ['revoked'], ['stale-session'], ['suspended']];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_unauthorized_requests_never_reach_completion_or_leak_context(string $case): void
    {
        [$owner, $merchant] = $this->journey();
        $reference = $this->prepare($owner, $merchant);
        if ($case === 'guest') {
            auth()->logout();
            $expected = 401;
        } elseif ($case === 'unverified') {
            $this->signIn(User::factory()->unverified()->create());
            $expected = 403;
        } elseif (in_array($case, ['manager', 'staff'], true)) {
            $other = User::factory()->create();
            MerchantMembership::factory()->create(['merchant_id' => $merchant->id, 'user_id' => $other->id, 'role' => $case]);
            $this->signIn($other);
            $expected = 403;
        } elseif (in_array($case, ['foreign', 'chief'], true)) {
            $other = User::factory()->create();
            if ($case === 'chief') {
                PlatformRoleAssignment::factory()->create(['user_id' => $other->id, 'role' => 'chief_admin']);
            }
            $this->signIn($other);
            $expected = 404;
        } elseif ($case === 'revoked') {
            DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['status' => 'revoked', 'revoked_at' => '2026-11-13 00:00:00', 'authority_version' => 2]);
            $expected = 404;
        } elseif ($case === 'stale-session') {
            $this->withSession(['auth_version' => 999]);
            $expected = 302;
        } else {
            DB::table('merchants')->where('id', $merchant->id)->update(['operational_status' => 'suspended']);
            $expected = 409;
        }
        $this->raw($merchant, $this->body($reference))->assertStatus($expected)->assertDontSee($reference)->assertDontSee('Kijani Studio')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(0, $this->harness->commandCalls);
        $this->assertSame('', $merchant->fresh()->business_name);
        $this->assertSame(0, DB::table('merchant_business_name_completions')->count());
    }

    public function test_csrf_really_runs_and_rejects_missing_or_wrong_tokens_and_foreign_origin(): void
    {
        [$owner, $merchant] = $this->journey();
        $ref = $this->prepare($owner, $merchant);
        $this->raw($merchant, 'context_ref='.$ref.'&business_name=Kijani')->assertStatus(419);
        $this->raw($merchant, '_token=wrong&context_ref='.$ref.'&business_name=Kijani')->assertStatus(419);
        $this->raw($merchant, $this->body($ref), headers: ['HTTP_ORIGIN' => 'https://foreign.fixture.test'])->assertStatus(403);
        $this->assertSame(0, $this->harness->commandCalls);
        $this->raw($merchant, $this->body($ref))->assertOk();
    }

    public static function invalidInputs(): array
    {
        return [['duplicate'], ['alias'], ['array'], ['unknown'], ['utf8'], ['percent'], ['tab'], ['format'], ['too-long'], ['oversize'], ['json'], ['query'], ['method']];
    }

    #[DataProvider('invalidInputs')]
    public function test_strict_validator_sees_original_raw_http_bytes_before_parameter_collapse(string $case): void
    {
        [$owner, $merchant] = $this->journey();
        $ref = $this->prepare($owner, $merchant);
        $body = $this->body($ref);
        $type = 'application/x-www-form-urlencoded';
        $query = '';
        $expected = 422;
        if ($case === 'duplicate') {
            $body .= '&business_name=Other';
        } elseif ($case === 'alias') {
            $body = str_replace('business_name=', 'business%5Fname=', $body);
        } elseif ($case === 'array') {
            $body = str_replace('business_name=', 'business_name%5B%5D=', $body);
        } elseif ($case === 'unknown') {
            $body .= '&merchant_id=foreign';
        } elseif ($case === 'utf8') {
            $body = str_replace('Kijani%20Studio', '%C0%AF', $body);
        } elseif ($case === 'percent') {
            $body .= '%GG';
        } elseif ($case === 'tab') {
            $body = $this->body($ref, "\tKijani Studio\n");
        } elseif ($case === 'format') {
            $body = $this->body($ref, "Kijani\u{200B} Studio");
        } elseif ($case === 'too-long') {
            $body = $this->body($ref, str_repeat('x', 201));
        } elseif ($case === 'oversize') {
            $body = $this->body($ref, str_repeat('x', 4097));
        } elseif ($case === 'json') {
            $type = 'application/json';
        } elseif ($case === 'query') {
            $query = '?role=owner';
        } else {
            $body .= '&_method=PUT';
            $expected = 405;
        }
        $this->raw($merchant, $body, type: $type, query: $query)->assertStatus($expected)->assertDontSee($ref)->assertSessionMissing('_old_input.business_name');
        $this->assertSame(0, $this->harness->commandCalls);
        $this->assertSame('', $merchant->fresh()->business_name);
    }

    public function test_context_is_bound_to_actor_tenant_session_and_exact_original_name(): void
    {
        [$owner, $merchant] = $this->journey();
        $ref = $this->prepare($owner, $merchant);
        $foreign = Merchant::factory()->create(['created_by_user_id' => $owner->id, 'business_name' => '']);
        $this->raw($foreign, $this->body($ref))->assertStatus(409);
        $oldSession = session()->getId();
        session()->setId(str_repeat('r', 40));
        $this->raw($merchant, $this->body($ref))->assertStatus(409);
        session()->setId($oldSession);
        $this->raw($merchant, $this->body($ref, 'Different desired name'))->assertStatus(409);
        $this->assertSame(0, $this->harness->commandCalls);
        $this->assertSame('', $merchant->fresh()->business_name);
    }

    public function test_two_prepared_requests_have_one_real_winner_and_no_stale_rename(): void
    {
        [$owner, $merchant] = $this->journey();
        $first = $this->prepare($owner, $merchant, 'First winner');
        $second = $this->prepare($owner, $merchant, 'Stale loser');
        $this->raw($merchant, $this->body($first, 'First winner'))->assertOk();
        $this->raw($merchant, $this->body($second, 'Stale loser'))->assertStatus(409);
        $this->assertSame('First winner', $merchant->fresh()->business_name);
        $this->assertSame(1, DB::table('merchant_business_name_completions')->count());
    }

    public function test_service_rechecks_authority_after_http_policy_check(): void
    {
        [$owner, $merchant] = $this->journey();
        $ref = $this->prepare($owner, $merchant);
        app()->instance(SubmissionTransaction::class, new class extends SubmissionTransaction
        {
            public function run(Closure $operation): mixed
            {
                DB::table('merchant_memberships')->update(['authority_version' => 2]);

                return parent::run($operation);
            }
        });
        $this->raw($merchant, $this->body($ref))->assertStatus(403);
        $this->assertSame(1, $this->harness->commandCalls);
        $this->assertSame('', $merchant->fresh()->business_name);
        $this->assertSame(0, DB::table('merchant_business_name_completions')->count());
    }

    public static function lostAcknowledgements(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('lostAcknowledgements')]
    public function test_unknown_http_outcome_never_retries_or_claims_success_and_real_explicit_resolution_stays_held(bool $committed): void
    {
        [$owner, $merchant] = $this->journey();
        $ref = $this->prepare($owner, $merchant);
        $transaction = new class($committed) extends SubmissionTransaction
        {
            public int $attempts = 0;

            public function __construct(private readonly bool $committed) {}

            protected function commit(Connection $connection): void
            {
                $this->attempts++;
                if ($this->committed) {
                    parent::commit($connection);
                }
                throw new RuntimeException('PRIVATE fictional acknowledgement loss');
            }
        };
        app()->instance(SubmissionTransaction::class, $transaction);
        $this->raw($merchant, $this->body($ref))->assertStatus(202)->assertExactJson(['status' => 'unresolved'])->assertDontSee('PRIVATE');
        $this->assertSame(1, $transaction->attempts);
        $this->assertSame(1, $this->harness->commandCalls);
        $this->assertSame($committed ? 1 : 0, (int) $this->ownedPdo->query('SELECT count(*) FROM merchant_business_name_completions')->fetchColumn());
        $this->assertSame($committed ? 'Kijani Studio' : '', $this->ownedPdo->query('SELECT business_name FROM merchants')->fetchColumn());
        DB::connection()->setPdo($this->ownedPdo);
        $this->raw($merchant, $this->body($ref))->assertStatus(409);
        $this->raw($merchant, $this->body($ref, null), true)->assertStatus(403);
        $this->harness->resolutionApproved = true;
        app()->instance(SubmissionTransaction::class, new SubmissionTransaction);
        $this->raw($merchant, $this->body($ref, null), true)->assertStatus(503)->assertExactJson(['status' => 'unresolved']);
        $this->assertSame(1, $this->harness->resolutionCalls);
        $this->assertNotSame($this->ownedPdo, DB::connection()->getRawPdo());
        $this->assertSame(1, $this->harness->commandCalls);
        $this->assertSame(1, $transaction->attempts);
    }

    public function test_ordinary_application_does_not_register_the_fixture_routes_or_admission(): void
    {
        $other = $this->createApplication();
        $this->assertInstanceOf(DeniedBusinessNameCompletionAdmission::class, $other->make(BusinessNameCompletionAdmission::class));
        foreach ($other->make('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('__isolated-completion', $route->uri());
        }
    }
}
