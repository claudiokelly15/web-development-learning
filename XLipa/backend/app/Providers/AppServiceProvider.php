<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));
        URL::forceScheme(parse_url(config('app.url'), PHP_URL_SCHEME));
        $identityKey = function (Request $request): string {
            $email = $request->input('email');

            return hash('sha256', is_string($email) ? strtolower(trim($email)) : '').'|'.$request->ip();
        };
        RateLimiter::for('auth-login', fn (Request $request) => [Limit::perMinute(5)->by('login:'.$identityKey($request)), Limit::perMinute(30)->by('login-ip:'.$request->ip())]);
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perHour(5)->by('register:'.$request->ip()));
        RateLimiter::for('auth-reset-link', fn (Request $request) => [Limit::perMinute(1)->by('reset:'.$identityKey($request)), Limit::perHour(10)->by('reset-ip:'.$request->ip())]);
        RateLimiter::for('auth-reset', fn (Request $request) => Limit::perMinute(5)->by('reset-attempt:'.$identityKey($request)));
        RateLimiter::for('auth-verify', fn (Request $request) => Limit::perMinute(6)->by('verify:'.$request->user()->getAuthIdentifier()));
    }
}
