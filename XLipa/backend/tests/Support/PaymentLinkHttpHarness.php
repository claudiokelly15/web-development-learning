<?php

namespace Tests\Support;

use App\Actions\Payments\PaymentLinkService;
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

/** No application service provider registers these routes. */
final class PaymentLinkHttpHarness
{
    public function __construct(public PaymentLinkService $links)
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
    }

    public function register(): void
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
        Route::middleware([MerchantOnboardingHttpHarness::class, 'web', AuthenticationResponseHeaders::class, 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
            ->prefix('/merchants/{merchant}/__isolated-links')->whereUlid('merchant')->group(function (): void {
                Route::get('/', function (Request $request): Response {
                    return $this->respond(function () use ($request): Response {
                        $data = $this->links->history($request->user(), $request->route('merchant')->id);
                        if ($request->expectsJson()) {
                            return response()->json($data);
                        }
                        $key = session('link_request_key') ?? bin2hex(random_bytes(16));
                        session(['link_request_key' => $key]);

                        return response()->view('merchants.payment-links', [...$data, 'merchantId' => $request->route('merchant')->id, 'requestKey' => $key]);
                    });
                })->name('isolated.links.index');
                Route::post('/', function (Request $request): Response {
                    return $this->respond(function () use ($request): Response {
                        $this->claims($request, ['amount_tzs', 'currency', 'description', 'request_key', 'expires_at']);
                        $amount = $request->input('amount_tzs');
                        $expiry = $request->input('expires_at');
                        if (! $request->isJson()) {
                            $amount = is_string($amount) && preg_match('/\A[1-9][0-9]{0,12}\z/D', $amount) === 1 ? (int) $amount : null;
                            $expiry = $expiry === null || $expiry === '' ? null : (is_string($expiry) && preg_match('/\A[1-9][0-9]{0,14}\z/D', $expiry) === 1 ? (int) $expiry : false);
                        }
                        abort_unless(is_string($request->input('currency')) && is_string($request->input('description')) && is_string($request->input('request_key')) && ($expiry === null || is_int($expiry)), 422);
                        $result = $this->links->create($request->user(), $request->route('merchant')->id, $amount, $request->input('currency'), $request->input('description'), $request->input('request_key'), $expiry);
                        if (session('link_request_key') === $request->input('request_key')) {
                            session()->forget('link_request_key');
                        }

                        return $request->expectsJson() ? response()->json($result) : redirect()->route('isolated.links.index', ['merchant' => $request->route('merchant')->id], 303);
                    });
                })->name('isolated.links.create');
                Route::post('/{link}/disable', function (Request $request): Response {
                    return $this->respond(function () use ($request): Response {
                        $this->claims($request, []);
                        $result = $this->links->disable($request->user(), $request->route('merchant')->id, $request->route('link'));

                        return $request->expectsJson() ? response()->json($result) : redirect()->route('isolated.links.index', ['merchant' => $request->route('merchant')->id], 303);
                    });
                })->whereUuid('link')->name('isolated.links.disable');
                foreach (['consume', 'reconcile'] as $operation) {
                    Route::post('/attempts/{attempt}/'.$operation, function (Request $request) use ($operation): Response {
                        return $this->respond(function () use ($request, $operation): Response {
                            $this->claims($request, []);
                            $result = $this->links->consume($request->user(), $request->route('merchant')->id, $request->route('attempt'), $operation === 'reconcile');

                            return $request->expectsJson() ? response()->json($result) : redirect()->route('isolated.links.index', ['merchant' => $request->route('merchant')->id], 303);
                        });
                    })->whereUuid('attempt')->name('isolated.links.'.$operation);
                }
            });
        Route::middleware([MerchantOnboardingHttpHarness::class, 'web', 'guest'])->prefix('/__isolated-checkout/{token}')->where(['token' => '[a-f0-9]{64}'])->group(function (): void {
            Route::get('/', function (Request $request): Response {
                return $this->respond(function () use ($request): Response {
                    $token = $request->route('token');
                    $link = $this->links->open($token);
                    $nonce = session('checkout.nonces.'.$token) ?? bin2hex(random_bytes(32));
                    session(['checkout.nonces.'.$token => $nonce]);

                    return response()->view('payments.checkout', ['link' => $link, 'nonce' => $nonce, 'result' => null]);
                });
            })->name('isolated.checkout.open');
            Route::post('/', function (Request $request): Response {
                return $this->respond(function () use ($request): Response {
                    $this->claims($request, ['request_nonce']);
                    abort_unless(is_string($request->input('request_nonce')), 422);
                    $result = $this->links->initiate($request->route('token'), $request->input('request_nonce'));

                    return $request->expectsJson() ? response()->json($result) : redirect()->route('isolated.checkout.status', ['token' => $request->route('token'), 'attempt' => $result['attempt_id']], 303);
                });
            })->name('isolated.checkout.initiate');
            Route::get('/attempts/{attempt}', function (Request $request): Response {
                return $this->respond(function () use ($request): Response {
                    $result = $this->links->customerStatus($request->route('token'), $request->route('attempt'));

                    return $request->expectsJson() ? response()->json($result) : response()->view('payments.checkout', ['link' => $result['link'], 'nonce' => null, 'result' => $result]);
                });
            })->whereUuid('attempt')->name('isolated.checkout.status');
        });
    }

    private function claims(Request $request, array $allowed): void
    {
        abort_unless(array_diff(array_keys($request->except('_token')), $allowed) === [] && $request->query() === [], 422);
        abort_unless($request->header('Origin') === null || $request->header('Origin') === config('app.url'), 403);
    }

    private function respond(Closure $work): Response
    {
        try {
            $response = $work();
        } catch (PaymentOutcomeUnknown) {
            $response = response()->json(['status' => 'unresolved', 'message' => 'The outcome is unresolved. Check status; do not start another payment.'], 202);
        } catch (HttpExceptionInterface $error) {
            $response = response()->json(['status' => 'denied', 'message' => 'This request is unavailable. Check the link or your access.'], $error->getStatusCode());
        } catch (Throwable) {
            $response = response()->json(['status' => 'unavailable', 'message' => 'The outcome could not be retrieved. Check status before taking further action.'], 503);
        }

        return $response->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'");
    }
}
