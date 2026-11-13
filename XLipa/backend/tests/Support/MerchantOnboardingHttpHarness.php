<?php

namespace Tests\Support;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\BusinessNameCompletionHttpInput;
use App\Actions\Merchants\BusinessNameCompletionOutcomeUnknown;
use App\Actions\Merchants\BusinessNameCompletionResolver;
use App\Actions\Merchants\BusinessNameCompletionService;
use App\Http\Middleware\AuthenticationResponseHeaders;
use App\Http\Middleware\EnsureActiveAuthentication;
use App\Http\Middleware\EnsureAuthenticationReady;
use App\Http\Middleware\ResolveMerchantContext;
use App\Models\Merchant;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Explicitly registered by one feature suite only; not an application controller or adapter. */
final class MerchantOnboardingHttpHarness
{
    public int $commandCalls = 0;

    public int $resolutionCalls = 0;

    public bool $resolutionApproved = false;

    private FakeBusinessNameCompletionRequestContext $contexts;

    private FakeBusinessNameCompletionAdmission $admission;

    public function __construct(private readonly PDO $ownedPdo)
    {
        $this->guard();
        $this->contexts = new FakeBusinessNameCompletionRequestContext(fn (): bool => $this->resolutionApproved);
        $this->admission = new FakeBusinessNameCompletionAdmission;
        $this->admission->allow = false;
        $this->admission->inspect = function (): void {
            $this->guard();
        };
    }

    public function register(): void
    {
        $this->guard();
        app()->instance(self::class, $this);
        app()->instance(BusinessNameCompletionAdmission::class, $this->admission);
        app()->instance(PreventRequestForgery::class, new class(app(), app('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        Route::middleware([self::class, 'web', AuthenticationResponseHeaders::class, 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
            ->prefix('/merchants/{merchant}/__isolated-completion')->whereUlid('merchant')->group(function (): void {
                Route::post('/', fn (Request $request): Response => $this->submit($request));
                Route::post('/resolve', fn (Request $request): Response => $this->submit($request, true));
            });
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->guard();

        return $next($request);
    }

    /** Server-custodied fixture preparation, not a public preparation endpoint or browser form. */
    public function prepare(User $actor, Merchant $merchant, #[\SensitiveParameter] string $name, string $session): string
    {
        $this->guard();
        Gate::forUser($actor)->authorize('manageVerification', $merchant);
        $actor = $actor->fresh();
        $draft = $merchant->applications()->where('current_slot', 1)->sole();
        $membership = $merchant->memberships()->where('user_id', $actor->id)->where('role', 'owner')->sole();
        $expected = ['application_id' => $draft->id, 'attempt_number' => $draft->attempt_number,
            'merchant_profile_version' => $merchant->fresh()->profile_version, 'draft_profile_version' => $draft->profile_version,
            'membership_id' => $membership->id, 'authority_version' => $membership->authority_version,
            'auth_version' => $actor->auth_version, 'email_binding_version' => $actor->email_binding_version,
            'completion_request_id' => (string) Str::uuid()];
        $time = $this->admission->time;

        return $this->contexts->prepare($this->binding($actor, $merchant, $session), $expected, (new BusinessNameCompletionHttpInput)->name($name), $time, $time + 60000, $time + 120000);
    }

    private function binding(User $actor, Merchant $merchant, string $session): array
    {
        return ['actor_id' => $actor->id, 'merchant_id' => $merchant->id, 'session_id' => $session, 'generation' => 'fictional-owned-memory-1'];
    }

    private function submit(Request $request, bool $resolving = false): Response
    {
        $this->guard();
        Gate::authorize('manageVerification', $request->route('merchant'));
        abort_if(in_array($request->route('merchant')->operational_status->value, ['suspended', 'closed'], true), 409);
        abort_unless($request->header('Origin') === null || $request->header('Origin') === config('app.url'), 403);
        try {
            $input = (new BusinessNameCompletionHttpInput)->parse((string) $request->header('Content-Type'), $request->getContent(), (string) $request->server('QUERY_STRING', ''), $resolving);
        } catch (InvalidArgumentException) {
            return response()->json(['status' => 'invalid'], 422);
        }
        abort_unless(hash_equals($request->session()->token(), $input['_token']), 419);
        $actor = $request->user();
        $merchant = $request->route('merchant');
        $binding = $this->binding($actor, $merchant, $request->session()->getId());
        $time = $this->admission->time;
        try {
            if ($resolving) {
                abort_unless($this->resolutionApproved, 403);
                $private = $this->contexts->beginResolution($input['context_ref'], $binding, $time);
            } else {
                $private = $this->contexts->dispatch($input['context_ref'], $binding, $time);
                if ($private['name'] !== $input['business_name']) {
                    $this->contexts->unresolved($input['context_ref'], $binding, $private['token'], $time);

                    return response()->json(['status' => 'conflict'], 409);
                }
            }
        } catch (HttpExceptionInterface $error) {
            return response()->json(['status' => 'denied'], $error->getStatusCode());
        } catch (RuntimeException) {
            return response()->json(['status' => 'conflict'], 409);
        }
        $this->admission->allow = true;
        try {
            if ($resolving) {
                $this->resolutionCalls++;
                $result = app(BusinessNameCompletionResolver::class)->resolve($actor, $merchant->id, $private['expected'], $private['name']);
                $safe = $this->contexts->finishResolution($input['context_ref'], $binding, $private['token'], $result, $time);
            } else {
                $this->commandCalls++;
                $result = app(BusinessNameCompletionService::class)->complete($actor, $merchant->id, $private['expected'], $private['name']);
                $safe = $this->contexts->finish($input['context_ref'], $binding, $private['token'], $result, $time);
            }

            return response()->json(['status' => $safe['state']]);
        } catch (BusinessNameCompletionOutcomeUnknown) {
            if (! $resolving) {
                $this->contexts->unresolved($input['context_ref'], $binding, $private['token'], $time);
            }

            return response()->json(['status' => 'unresolved'], 202);
        } catch (HttpExceptionInterface $error) {
            if (! $resolving) {
                $this->contexts->unresolved($input['context_ref'], $binding, $private['token'], $time);
            }

            return response()->json(['status' => 'unresolved'], $error->getStatusCode());
        } finally {
            $this->admission->allow = false;
        }
    }

    private function guard(): void
    {
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url') || DB::connection()->getDatabaseName() !== ':memory:' || DB::connection()->getRawPdo() !== $this->ownedPdo) {
            throw new LogicException('Isolated HTTP fixture is unavailable.');
        }
    }
}
