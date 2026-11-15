<?php

namespace Tests\Support;

use App\Actions\Payments\FictionalMockProvider;
use App\Actions\Payments\PaymentAdmission;
use App\Actions\Payments\PaymentEngine;
use App\Actions\Payments\PaymentOutcomeUnknown;
use App\Actions\Payments\PaymentStore;
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

final class PaymentHttpHarness
{
    public PaymentEngine $engine;

    public function __construct(PaymentStore $store, FictionalMockProvider $provider, PaymentAdmission $admission)
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
        $this->engine = new PaymentEngine($store, $provider, $admission);
    }

    public function register(): void
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
        Route::middleware([MerchantOnboardingHttpHarness::class, 'web', AuthenticationResponseHeaders::class, 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
            ->prefix('/merchants/{merchant}/__isolated-payments')->whereUlid('merchant')->group(function (): void {
                Route::post('/', function (Request $request): Response {
                    abort_unless(array_diff(array_keys($request->except('_token')), ['amount_tzs', 'currency', 'idempotency_key', 'order_reference']) === [] && $request->query() === []
                        && is_string($request->input('currency')) && is_string($request->input('idempotency_key')) && ($request->input('order_reference') === null || is_string($request->input('order_reference'))), 422);
                    abort_unless($request->header('Origin') === null || $request->header('Origin') === config('app.url'), 403);

                    return $this->respond(fn (): array => $this->engine->create($request->user(), $request->route('merchant')->id, $request->input('amount_tzs'), $request->input('currency'), $request->input('idempotency_key'), $request->input('order_reference')));
                });
                Route::get('/{payment}', fn (Request $request): Response => $this->respond(fn (): array => $this->engine->read($request->user(), $request->route('merchant')->id, $request->route('payment'))))->whereUuid('payment');
                Route::post('/{payment}/query', fn (Request $request): Response => $this->respond(fn (): array => $this->engine->query($request->user(), $request->route('merchant')->id, $request->route('payment'))))->whereUuid('payment');
            });
        Route::middleware([MerchantOnboardingHttpHarness::class])->post('/__isolated-provider-events', fn (Request $request): Response => $this->respond(fn (): array => $this->engine->receive($request->getContent(), (string) $request->header('X-Fictional-Signature'))));
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
