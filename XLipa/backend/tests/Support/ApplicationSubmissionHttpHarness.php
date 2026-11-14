<?php

namespace Tests\Support;

use App\Actions\Merchants\ApplicationSubmissionService;
use App\Actions\Merchants\SubmissionAdmission;
use App\Actions\Merchants\SubmissionEvidenceEnvironment;
use App\Actions\Merchants\SubmissionOutcomeUnknown;
use App\Actions\Merchants\VerificationCipher;
use App\Actions\Merchants\VerificationEnvironment;
use App\Http\Middleware\AuthenticationResponseHeaders;
use App\Http\Middleware\EnsureActiveAuthentication;
use App\Http\Middleware\EnsureAuthenticationReady;
use App\Http\Middleware\ResolveMerchantContext;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Single-process fictional custody, explicitly registered by the P2 test suite only. */
final class ApplicationSubmissionHttpHarness
{
    public int $commandCalls = 0;

    public FakeSubmissionAdmission $admission;

    public FakeSubmissionVerificationEnvironment $verification;

    public FakeSubmissionEvidenceEnvironment $custody;

    private array $requests = [];

    private int $time = 1794614400000;

    public function __construct(private readonly MerchantOnboardingHttpHarness $onboarding)
    {
        $this->onboarding->guard();
        $this->admission = new FakeSubmissionAdmission;
        $this->verification = new FakeSubmissionVerificationEnvironment;
        $this->custody = new FakeSubmissionEvidenceEnvironment;
        $this->admission->time = $this->verification->time = $this->custody->time = $this->time + 100;
    }

    public function register(): void
    {
        $this->onboarding->guard();
        Route::middleware([MerchantOnboardingHttpHarness::class, 'web', AuthenticationResponseHeaders::class, 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
            ->post('/merchants/{merchant}/__isolated-submission', fn (Request $request): Response => $this->submit($request))->whereUlid('merchant');
    }

    /** Uses the established service-test issuance transitions and fictional issuer registry. */
    public function prepare(User $user, Merchant $merchant, string $session): string
    {
        $this->onboarding->guard();
        Gate::forUser($user)->authorize('manageVerification', $merchant);
        abort_unless(auth()->id() === $user->id && $user->fresh()->email_verified_at !== null, 403);
        abort_if(DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant->id)->exists(), 409);
        $application = $merchant->applications()->where('current_slot', 1)->sole();
        abort_unless($application->status->value === 'draft' && $merchant->fresh()->business_name !== '', 409);
        $transport = 'live';
        $retentionDue = null;
        $previousVerification = app(VerificationEnvironment::class);
        app()->instance(VerificationEnvironment::class, $this->verification);
        try {
            $identity = (string) Str::ulid();
            $contact = (string) Str::ulid();
            $challenge = (string) Str::ulid();
            $proof = (string) Str::ulid();
            DB::table('merchant_identity_records')->insert(['id' => $identity, 'merchant_id' => $merchant->id, 'version' => 1, 'subject_claim' => 'individual_owner', 'nin_ciphertext' => $this->envelope('nin', 'FIXTURE-ABCDEFGH', $merchant->id, $identity, $user->id), 'encryption_key_ref' => 'nin.v1', 'envelope_version' => 1, 'syntax_policy_version' => 'fixture.syntax', 'syntax_checked_at' => $this->time, 'declaration_version' => 'fixture.declaration', 'notice_version' => 'fixture.notice', 'declared_by_user_id' => $user->id, 'declared_at' => $this->time, 'retention_due_at' => $retentionDue]);
            DB::table('merchant_contact_versions')->insert(['id' => $contact, 'merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'purpose' => 'application_phone', 'version' => 1, 'phone_ciphertext' => $this->envelope('phone', '+255712345678', $merchant->id, $contact, $user->id), 'phone_key_ref' => 'phone.v1', 'envelope_version' => 1, 'target_fingerprint' => hash_hmac('sha256', VerificationCipher::lp('xlipa-contact-fp-v1', '+255712345678'), $this->verification->keys['contact']), 'fingerprint_key_ref' => 'contact.v1', 'created_at' => $this->time]);
            DB::table('merchant_onboarding_contexts')->insert(['merchant_id' => $merchant->id, 'current_identity_id' => $identity, 'current_contact_id' => $contact, 'contact_actor_user_id' => $user->id, 'contact_purpose' => 'application_phone', 'updated_at' => $this->time]);
            $base = ['merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'contact_id' => $contact, 'purpose' => 'application_phone'];
            DB::table('phone_otp_challenges')->insert(['id' => $challenge, ...$base, 'active_slot' => 1, 'created_at' => $this->time, 'expires_at' => $this->time + 300000, 'transport_mode' => $transport]);
            DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'dispatching', 'state_version' => 2, 'code_digest' => str_repeat('b', 64), 'digest_key_ref' => 'fixture.otp', 'digest_created_at' => $this->time]);
            DB::table('otp_delivery_intents')->insert(['id' => (string) Str::ulid(), ...$base, 'challenge_id' => $challenge, 'transport_mode' => $transport, 'request_id' => (string) Str::uuid(), 'requested_at' => $this->time]);
            DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->update(['state' => 'claimed', 'claim_version' => 2, 'claimed_at' => $this->time, 'lease_expires_at' => $this->time + 30000]);
            DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->update(['state' => 'accepted', 'claim_version' => 3, 'completed_at' => $this->time + 1]);
            DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'active', 'state_version' => 3]);
            DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'consumed', 'state_version' => 4, 'active_slot' => null, 'terminal_at' => $this->time + 2]);
            DB::table('contact_verification_proofs')->insert(['id' => $proof, ...$base, 'challenge_id' => $challenge, 'channel' => 'sms', 'provenance' => $transport, 'policy_version' => 'application-phone-proof-v1', 'verified_at' => $this->time + 2, 'expires_at' => $this->time + 1800002]);
        } finally {
            app()->instance(VerificationEnvironment::class, $previousVerification);
        }
        $membership = $merchant->memberships()->where('user_id', $user->id)->where('role', 'owner')->sole();
        $context = DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant->id)->sole();
        $expected = ['application_id' => $application->id, 'membership_id' => $membership->id,
            'auth_version' => $user->fresh()->auth_version, 'email_binding_version' => $user->fresh()->email_binding_version,
            'authority_version' => $membership->authority_version, 'attempt_number' => $application->attempt_number,
            'profile_version' => $application->profile_version, 'context_version' => $context->context_version,
            'identity_id' => $identity, 'identity_version' => 1, 'contact_id' => $contact, 'contact_version' => 1,
            'proof_id' => $proof, 'submission_request_id' => (string) Str::uuid()];
        $p = (array) DB::table('contact_verification_proofs')->where('id', $proof)->sole();
        $h = (array) DB::table('phone_otp_challenges')->where('id', $challenge)->sole();
        $d = (array) DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->sole();
        $i = (array) DB::table('merchant_identity_records')->where('id', $identity)->sole();
        $this->admission->issued = [$p['id'], $p['challenge_id'], $p['merchant_id'], $p['actor_user_id'], $p['contact_id'], $p['verified_at'], $p['expires_at'], $d['id'], $d['request_id'], $d['completed_at'], $h['id'], $h['terminal_at'], $i['id'], $i['syntax_policy_version']];
        $reference = bin2hex(random_bytes(32));
        $this->requests[$reference] = ['actor' => $user->id, 'merchant' => $merchant->id, 'session' => $session, 'expected' => $expected, 'state' => 'prepared'];

        return $reference;
    }

    private function envelope(string $domain, string $value, string $merchant, string $record, int $actor, int $revision = 1): string
    {
        return app(VerificationCipher::class)->encrypt($domain, $value, ['namespace' => 'fixture', 'merchant' => $merchant, 'record' => $record, 'purpose' => $domain === 'nin' ? 'identity_declaration' : 'application_phone', 'version' => 1, 'actor' => $actor], $domain.'.v1', $revision);
    }

    private function submit(Request $request): Response
    {
        $this->onboarding->guard();
        $merchant = $request->route('merchant');
        Gate::authorize('manageVerification', $merchant);
        abort_unless($request->header('Origin') === null || $request->header('Origin') === config('app.url'), 403);
        abort_unless($request->query() === [] && count($request->except('_token')) === 1 && is_string($request->input('context_ref')), 422);
        $reference = $request->input('context_ref');
        $private = $this->requests[$reference] ?? null;
        abort_unless($private && $private['actor'] === $request->user()->id && $private['merchant'] === $merchant->id && $private['session'] === $request->session()->getId() && $private['state'] === 'prepared', 409);
        $this->requests[$reference]['state'] = 'unresolved';
        $bindings = [SubmissionAdmission::class => $this->admission, SubmissionEvidenceEnvironment::class => $this->custody, VerificationEnvironment::class => $this->verification];
        $previous = [];
        foreach ($bindings as $contract => $fixture) {
            $previous[$contract] = app($contract);
            app()->instance($contract, $fixture);
        }
        try {
            $this->commandCalls++;
            app(ApplicationSubmissionService::class)->submit($request->user(), $merchant->id, $private['expected']);
            $this->requests[$reference]['state'] = 'submitted';

            return response()->json(['status' => 'submitted']);
        } catch (SubmissionOutcomeUnknown) {
            return response()->json(['status' => 'unresolved'], 202);
        } catch (HttpExceptionInterface $error) {
            return response()->json(['status' => 'unresolved'], $error->getStatusCode());
        } finally {
            foreach ($previous as $contract => $original) {
                app()->instance($contract, $original);
            }
        }
    }
}
