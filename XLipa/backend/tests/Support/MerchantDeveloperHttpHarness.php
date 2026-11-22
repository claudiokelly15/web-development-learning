<?php

namespace Tests\Support;

use App\Actions\Payments\CheckoutQr;
use App\Actions\Payments\MerchantDeveloperTools;
use App\Actions\Payments\MerchantWebhooks;
use App\Actions\Payments\PaymentStore;
use App\Http\Middleware\EnsureActiveAuthentication;
use App\Http\Middleware\EnsureAuthenticationReady;
use App\Http\Middleware\ResolveMerchantContext;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Explicit registration only; no production route or service provider references this harness. */
final class MerchantDeveloperHttpHarness
{
    public function __construct(public MerchantDeveloperTools $tools, public MerchantWebhooks $webhooks, private readonly PaymentStore $store) {}

    public function register(): void
    {
        app(MerchantOnboardingHttpHarness::class)->guard();
        Route::middleware([MerchantOnboardingHttpHarness::class])->prefix('/__isolated-api/v1/merchants/{merchant}')->whereUlid('merchant')->group(function (): void {
            Route::get('/links', fn (Request $request): Response => $this->api($request, 'links:read', fn (User $actor): array => $this->tools->history($actor, $request->route('merchant'))));
            Route::post('/links', fn (Request $request): Response => $this->api($request, 'links:write', function (User $actor) use ($request): array {
                abort_unless($request->isJson() && strlen($request->getContent()) <= 4096, 415);

                return $this->tools->createLink($actor, $request->route('merchant'), $request->json()->all(), $request->header('Idempotency-Key', ''));
            }));
            Route::get('/payments/{reference}', fn (Request $request): Response => $this->api($request, 'payments:read', fn (User $actor): array => $this->tools->payment($actor, $request->route('merchant'), $request->route('reference'))))->whereUuid('reference');
        });
        Route::middleware([MerchantOnboardingHttpHarness::class, 'web', 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified', ResolveMerchantContext::class])
            ->prefix('/merchants/{merchant}/__isolated-developers')->whereUlid('merchant')->group(function (): void {
                foreach (['api', 'credentials', 'qr', 'buttons', 'webhooks', 'docs'] as $tab) {
                    Route::get('/'.$tab, fn (Request $request): Response => $this->respond(function () use ($request, $tab): Response {
                        $data = $this->tools->overview($request->user(), $request->route('merchant')->id);

                        return response()->view('merchants.developers', [...$data, 'merchantId' => $request->route('merchant')->id, 'tab' => $tab, 'tools' => $this->tools, 'chiefView' => false]);
                    }))->name('isolated.developers.'.$tab);
                }
                Route::post('/credentials', fn (Request $request): Response => $this->respond(function () use ($request): Response {
                    $this->claims($request, ['scopes', 'expires_at', 'rotate']);
                    abort_unless(is_array($request->input('scopes')) && is_int($request->input('expires_at')) && ($request->input('rotate') === null || is_string($request->input('rotate'))), 422);

                    return response()->json($this->tools->issue($request->user(), $request->route('merchant')->id, $request->input('scopes'), $request->input('expires_at'), $request->input('rotate')));
                }));
                Route::post('/credentials/{id}/revoke', fn (Request $request): Response => $this->respond(function () use ($request): Response {
                    $this->claims($request, []);
                    $this->tools->revoke($request->user(), $request->route('merchant')->id, $request->route('id'));

                    return response()->json(['status' => 'revoked']);
                }))->whereUuid('id');
                Route::post('/widgets', fn (Request $request): Response => $this->respond(function () use ($request): Response {
                    $this->claims($request, ['link_id', 'kind', 'mode']);
                    foreach (['link_id', 'kind', 'mode'] as $field) {
                        abort_unless(is_string($request->input($field)), 422);
                    }

                    return response()->json($this->tools->widget($request->user(), $request->route('merchant')->id, $request->input('link_id'), $request->input('kind'), $request->input('mode')));
                }));
                Route::post('/webhooks', fn (Request $request): Response => $this->respond(function () use ($request): Response {
                    $this->claims($request, ['url']);
                    abort_unless(is_string($request->input('url')), 422);

                    return response()->json($this->webhooks->register($request->user(), $request->route('merchant')->id, $request->input('url')));
                }));
                Route::post('/webhooks/{id}/disable', fn (Request $request): Response => $this->respond(function () use ($request): Response {
                    $this->claims($request, []);
                    $this->webhooks->disable($request->user(), $request->route('merchant')->id, $request->route('id'));

                    return response()->json(['status' => 'disabled']);
                }))->whereUuid('id');
            });
        Route::middleware([MerchantOnboardingHttpHarness::class, 'web', 'auth', EnsureAuthenticationReady::class, EnsureActiveAuthentication::class, 'verified'])
            ->get('/admin/__isolated-api-governance/{merchant}', fn (Request $request): Response => $this->respond(function () use ($request): Response {
                abort_unless(Gate::allows('viewChiefWorkspace', PlatformRoleAssignment::class), 403);
                $data = $this->tools->redacted($this->store, $request->route('merchant'));

                return response()->view('merchants.developers', [...$data, 'merchantId' => $request->route('merchant'), 'tab' => 'governance', 'tools' => $this->tools, 'chiefView' => true]);
            }))->whereUlid('merchant')->name('isolated.admin.api-governance');
        Route::middleware([MerchantOnboardingHttpHarness::class])->get('/__isolated-widgets/{id}', fn (Request $request): Response => $this->respond(function () use ($request): Response {
            $link = $this->tools->resolveWidget($request->route('id'));

            return redirect($this->tools->checkoutUrl($link['public_token']), 303);
        }))->whereUuid('id')->name('isolated.widget');
        Route::middleware([MerchantOnboardingHttpHarness::class])->get('/__isolated-widgets/{id}/qr.svg', fn (Request $request): Response => $this->respond(function () use ($request): Response {
            $link = $this->tools->resolveWidget($request->route('id'));

            return response(CheckoutQr::svg($this->tools->checkoutUrl($link['public_token'])), 200, ['Content-Type' => 'image/svg+xml']);
        }))->whereUuid('id')->name('isolated.widget.qr');
    }

    private function api(Request $request, string $scope, Closure $work): Response
    {
        return $this->respond(function () use ($request, $scope, $work): Response {
            abort_unless($request->query() === [] && $request->header('Origin') === null, 422);
            $authorization = $request->header('Authorization', '');
            abort_unless(str_starts_with($authorization, 'Bearer '), 401);

            return response()->json(['api_version' => 'v1', 'data' => $this->tools->api(substr($authorization, 7), $request->route('merchant'), $scope, $work)]);
        });
    }

    private function claims(Request $request, array $allowed): void
    {
        abort_unless(array_diff(array_keys($request->except('_token')), $allowed) === [] && $request->query() === [] && strlen($request->getContent()) <= 4096, 422);
        abort_unless($request->header('Origin') === null || $request->header('Origin') === config('app.url'), 403);
    }

    private function respond(Closure $work): Response
    {
        try {
            $response = $work();
        } catch (HttpExceptionInterface $error) {
            $response = response()->json(['error' => 'request_denied'], $error->getStatusCode());
        } catch (Throwable) {
            $response = response()->json(['error' => 'unavailable_check_status_before_retry'], 503);
        }

        return $response->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'self'; script-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'");
    }
}
