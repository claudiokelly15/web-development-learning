<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['nin', 'code']);
        $middleware->trustHosts(
            at: fn (): array => ['^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '/').'$'],
            subdomains: false,
        );
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'confirmPassword', 'current_password', 'token', '_token', 'nin', 'phone', 'code', 'declaration', 'subject_claim']);
        $exceptions->render(function (Throwable $error, Request $request): ?Response {
            if ($error instanceof AuthenticationException) {
                return null;
            }
            if (! $request->is('merchants/*/verification', 'merchants/*/verification/*', 'merchants/*/applications', 'merchants/*/applications/*', 'admin/applications')) {
                return null;
            }
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;
            if ($status < 400 || $status > 599) {
                $status = 503;
            }
            $message = 'Verification is unavailable or the request could not be accepted. No sensitive values are displayed.';
            if ($request->is('merchants/*/applications', 'merchants/*/applications/*', 'admin/applications')) {
                $message = 'Application access is unavailable or the request could not be accepted. No application was changed.';
            }

            return $request->expectsJson() ? response()->json(['message' => $message], $status) : response($message, $status);
        });
        $exceptions->respond(function (Response $response): Response {
            if (request()->is('register', 'login', 'logout', 'forgot-password', 'reset-password*', 'email/*', 'account', 'merchants', 'merchants/*', 'admin', 'admin/*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('Referrer-Policy', 'no-referrer');
                if (request()->is('merchants', 'merchants/*', 'admin', 'admin/*')) {
                    $response->headers->set('X-Content-Type-Options', 'nosniff');
                    $response->headers->set('X-Frame-Options', 'DENY');
                }
            }

            return $response;
        });
        $exceptions->report(function (Throwable $exception): ?bool {
            if (request()->is('register', 'login', 'logout', 'forgot-password', 'reset-password*', 'email/*', 'account', 'merchants', 'merchants/*', 'admin', 'admin/*')) {
                $category = request()->is('admin', 'admin/*') ? 'Administrative request failed.' : (request()->is('merchants', 'merchants/*') ? 'Merchant request failed.' : 'Authentication request failed.');
                Log::error($category, ['exception_type' => $exception::class]);

                return false;
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
