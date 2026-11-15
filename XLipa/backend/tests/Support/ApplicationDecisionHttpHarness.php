<?php

namespace Tests\Support;

use App\Actions\Merchants\ApplicationDecisionAdmission;
use App\Actions\Merchants\ApplicationDecisionService;
use App\Actions\Merchants\SubmissionAdmission;
use App\Actions\Merchants\SubmissionEvidenceEnvironment;
use App\Actions\Merchants\SubmissionOutcomeUnknown;
use App\Actions\Merchants\VerificationEnvironment;
use App\Http\Middleware\AuthenticationResponseHeaders;
use App\Http\Middleware\EnsureActiveAuthentication;
use App\Http\Middleware\EnsureAuthenticationReady;
use App\Models\MerchantApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApplicationDecisionHttpHarness
{
    public int $calls = 0;

    public bool $useFictionalAdmission = true;

    public FakeApplicationDecisionAdmission $admission;

    private array $contexts = [];

    public function __construct(private readonly MerchantOnboardingHttpHarness $onboarding, private readonly ApplicationSubmissionHttpHarness $submission)
    {
        $this->onboarding->guard();
        $this->admission = new FakeApplicationDecisionAdmission;
        $this->submission->custody->time = $this->submission->verification->time = $this->admission->time;
    }

    public function register(): void
    {
        $this->onboarding->guard();
        Route::middleware([MerchantOnboardingHttpHarness::class, 'web', AuthenticationResponseHeaders::class, 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified'])
            ->post('/__isolated-decisions/{merchant}/{application}', fn (Request $request): Response => $this->decide($request))->whereUlid(['merchant', 'application']);
    }

    public function prepare(User $actor, MerchantApplication $application, string $session, string $outcome, ?string $reason = null, array $overrides = []): string
    {
        $this->onboarding->guard();
        foreach ($this->contexts as $context) {
            abort_if($context['application'] === $application->id && $context['state'] === 'unresolved', 409);
        }
        $actor = $actor->fresh();
        $evidence = DB::table('merchant_application_submission_evidence')->where('application_id', $application->id)->sole();
        $expected = ['attempt_number' => $application->attempt_number, 'profile_version' => $application->profile_version,
            'reviewer_auth_version' => $actor->auth_version, 'reviewer_email_binding_version' => $actor->email_binding_version,
            'reviewer_authority_version' => $this->admission->version, 'submission_request_id' => $evidence->submission_request_id, 'decision_request_id' => (string) Str::uuid(), ...$overrides];
        $reference = bin2hex(random_bytes(32));
        $this->contexts[$reference] = ['actor' => $actor->id, 'session' => $session, 'merchant' => $application->merchant_id,
            'application' => $application->id, 'expected' => $expected, 'outcome' => $outcome, 'reason' => $reason, 'state' => 'prepared'];
        $this->admission->grant($actor->id, $application->merchant_id, $application->id, $expected, $outcome, $reason);

        return $reference;
    }

    private function decide(Request $request): Response
    {
        $this->onboarding->guard();
        abort_unless($request->query() === [] && count($request->except('_token')) === 1 && is_string($request->input('context_ref')), 422);
        abort_unless($request->header('Origin') === null || $request->header('Origin') === config('app.url'), 403);
        $ref = $request->input('context_ref');
        $context = $this->contexts[$ref] ?? null;
        abort_unless($context && $context['actor'] === $request->user()->id && $context['session'] === $request->session()->getId()
            && $context['merchant'] === $request->route('merchant') && $context['application'] === $request->route('application') && $context['state'] === 'prepared', 409);
        foreach ($this->contexts as $other) {
            abort_if($other['application'] === $context['application'] && $other['state'] === 'unresolved', 409);
        }
        $this->contexts[$ref]['state'] = 'unresolved';
        $bindings = [ApplicationDecisionAdmission::class => $this->useFictionalAdmission ? $this->admission : new ApplicationDecisionAdmission,
            SubmissionAdmission::class => $this->submission->admission, SubmissionEvidenceEnvironment::class => $this->submission->custody, VerificationEnvironment::class => $this->submission->verification];
        $previous = [];
        foreach ($bindings as $contract => $fixture) {
            $previous[$contract] = app($contract);
            app()->instance($contract, $fixture);
        }
        try {
            $this->calls++;
            $receipt = app(ApplicationDecisionService::class)->decide($request->user(), $context['merchant'], $context['application'], $context['expected'], $context['outcome'], $context['reason']);
            $this->contexts[$ref]['state'] = $receipt['status'];

            return response()->json(['status' => $receipt['status']]);
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
