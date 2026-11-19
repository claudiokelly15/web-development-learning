<?php

namespace Tests\Support;

use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\PaymentOutcomeUnknown;
use App\Http\Middleware\AuthenticationResponseHeaders;
use App\Http\Middleware\EnsureActiveAuthentication;
use App\Http\Middleware\EnsureAuthenticationReady;
use App\Http\Middleware\ResolveMerchantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Explicitly registered in the P5 feature suite only. */
final class LedgerHttpHarness
{
    public function __construct(public MockPaymentLedger $ledger)
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
    }

    public function register(): void
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
        Route::middleware([MerchantOnboardingHttpHarness::class, 'web', AuthenticationResponseHeaders::class, 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
            ->prefix('/merchants/{merchant}/__isolated-ledger')->whereUlid('merchant')->group(function (): void {
                Route::post('/', function (Request $request): Response {
                    abort_unless(array_diff(array_keys($request->except('_token')), ['confirmation_id', 'payment_reference', 'amount_tzs', 'currency']) === [] && $request->query() === []
                        && is_string($request->input('confirmation_id')) && is_string($request->input('payment_reference')) && is_string($request->input('currency')), 422);
                    abort_unless($request->header('Origin') === null || $request->header('Origin') === config('app.url'), 403);

                    return $this->respond(fn (): array => $this->ledger->post($request->user(), $request->route('merchant')->id, $request->input('confirmation_id'), $request->input('payment_reference'), $request->input('amount_tzs'), $request->input('currency')));
                });
                Route::get('/', fn (Request $request): Response => $this->respond(fn (): array => $this->ledger->position($request->user(), $request->route('merchant')->id)));
                Route::get('/{confirmation}/reconciliation', fn (Request $request): Response => $this->respond(fn (): array => $this->ledger->reconcile($request->user(), $request->route('merchant')->id, $request->route('confirmation'))))->whereUuid('confirmation');
            });
    }

    private function respond(Closure $work): Response
    {
        try {
            return response()->json($work())->header('Cache-Control', 'no-store, private');
        } catch (PaymentOutcomeUnknown) {
            return response()->json(['status' => 'unresolved'], 202);
        } catch (HttpExceptionInterface $error) {
            return response()->json(['status' => 'denied'], $error->getStatusCode());
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
