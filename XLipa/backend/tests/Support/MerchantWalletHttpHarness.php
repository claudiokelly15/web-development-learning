<?php

namespace Tests\Support;

use App\Actions\Payments\LedgerLifecycle;
use App\Actions\Payments\MerchantAnalytics;
use App\Actions\Payments\MerchantWallet;
use App\Actions\Payments\PaymentOutcomeUnknown;
use App\Actions\Payments\PaymentStore;
use App\Http\Middleware\AuthenticationResponseHeaders;
use App\Http\Middleware\EnsureActiveAuthentication;
use App\Http\Middleware\EnsureAuthenticationReady;
use App\Http\Middleware\ResolveMerchantContext;
use App\Models\PlatformRoleAssignment;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Explicit test registration only. Diagnostics stay in test memory, never in HTTP responses. */
final class MerchantWalletHttpHarness
{
    public ?Throwable $lastFailure = null;

    public function __construct(public MerchantWallet $wallet, public ?MerchantAnalytics $analytics = null, public ?Closure $analyticsClock = null, public ?LedgerLifecycle $funds = null, public ?PaymentStore $fundsStore = null)
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
    }

    public function register(): void
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
        app()->instance(self::class, $this);
        if ($this->funds !== null && $this->fundsStore !== null) {
            Route::middleware([self::class, MerchantOnboardingHttpHarness::class, 'web', AuthenticationResponseHeaders::class, 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified'])
                ->get('/admin/__isolated-funds/{merchant}', function (Request $request): Response {
                    abort_unless($request->query() === [] && $request->request->all() === [], 422);
                    abort_unless(Gate::allows('viewChiefWorkspace', PlatformRoleAssignment::class), 403);
                    $funds = $this->funds->chiefOverview($request->user(), $request->route('merchant'), $this->fundsStore);

                    return response()->view('admin.funds', ['funds' => $funds]);
                })->whereUlid('merchant')->name('isolated.admin.funds');
        }
        Route::middleware([self::class, MerchantOnboardingHttpHarness::class, 'web', AuthenticationResponseHeaders::class, 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
            ->prefix('/merchants/{merchant}/__isolated-finances')->whereUlid('merchant')->group(function (): void {
                if ($this->funds !== null) {
                    Route::get('/funds', fn (Request $request): Response => $this->respond($request, function () use ($request): array {
                        abort_unless($request->query() === [], 422);

                        return ['funds' => $this->funds->overview($request->user(), $request->route('merchant')->id)];
                    }, 'merchants.funds'))->name('isolated.funds');
                }
                if ($this->analytics !== null && $this->analyticsClock !== null) {
                    Route::get('/analytics', fn (Request $request): Response => $this->respond($request,
                        fn (): array => ['analytics' => $this->analytics->report($request->user(), $request->route('merchant')->id, $request->query(), ($this->analyticsClock)())], 'merchants.analytics'))->name('isolated.analytics');
                }
                Route::get('/wallet', fn (Request $request): Response => $this->respond($request, function () use ($request): array {
                    abort_unless($request->query() === [], 422);

                    $data = $this->wallet->overview($request->user(), $request->route('merchant')->id);
                    if ($this->funds !== null) {
                        $data['funds'] = $this->funds->overview($request->user(), $request->route('merchant')->id);
                    }

                    return $data;
                }, 'merchants.wallet'))->name('isolated.wallet');
                Route::get('/transactions', fn (Request $request): Response => $this->respond($request,
                    fn (): array => $this->wallet->history($request->user(), $request->route('merchant')->id, $request->query()), 'merchants.transactions'))->name('isolated.transactions');
                Route::get('/transactions/{record}', fn (Request $request): Response => $this->respond($request, function () use ($request): array {
                    abort_unless($request->query() === [], 422);

                    return $this->wallet->detail($request->user(), $request->route('merchant')->id, $request->route('record'));
                }, 'merchants.transaction-detail'))->name('isolated.transactions.show');
            });
    }

    public function handle(Request $request, Closure $next): Response
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
        try {
            $response = $next($request);
        } catch (AuthenticationException $error) {
            $this->lastFailure = $error;
            $response = $request->expectsJson() ? response()->json(['status' => 'unauthenticated'], 401) : redirect()->guest(route('login'));
        } catch (HttpExceptionInterface $error) {
            $this->lastFailure = $error;
            $response = response()->json(['status' => 'denied'], $error->getStatusCode());
        } catch (Throwable $error) {
            $this->lastFailure = $error;
            $response = response()->json(['status' => 'unavailable'], 503);
        }

        return $this->headers($response);
    }

    private function respond(Request $request, Closure $read, string $view): Response
    {
        $this->lastFailure = null;
        try {
            abort_unless($request->request->all() === [], 422);
            $data = $read();
            $response = $request->expectsJson() ? response()->json($data) : response()->view($view, [...$data,
                'merchantId' => $request->route('merchant')->id, 'businessName' => $request->route('merchant')->business_name]);
        } catch (PaymentOutcomeUnknown $error) {
            $this->lastFailure = $error;
            $response = response()->json(['status' => 'unresolved', 'message' => 'Financial records could not be verified. No balance or settlement is assumed.'], 202);
        } catch (HttpExceptionInterface $error) {
            $this->lastFailure = $error;
            $response = response()->json(['status' => 'denied', 'message' => 'Financial access or filters could not be accepted.'], $error->getStatusCode());
        } catch (Throwable $error) {
            $this->lastFailure = $error;
            $response = response()->json(['status' => 'unavailable', 'message' => 'Financial records are unavailable. No balance or settlement is assumed.'], 503);
        }

        return $this->headers($response);
    }

    private function headers(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'");

        return $response;
    }
}
