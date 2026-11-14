<?php

namespace Tests\Support;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\BusinessNameCompletionSchema;
use App\Actions\Merchants\DeniedBusinessNameCompletionAdmission;
use App\Actions\Merchants\SubmissionEvidenceSchema;
use App\Models\Merchant;
use App\Models\User;
use App\Notifications\VersionedVerifyEmail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PDO;

trait MerchantOnboardingJourney
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

        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId());

        return $this->call('POST', $this->url($merchant, $resolving).$query, $parameters, $this->prepareCookiesForRequest(), [], ['CONTENT_TYPE' => $type, 'HTTP_ACCEPT' => 'application/json', ...$headers], $body);
    }

    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }
}
