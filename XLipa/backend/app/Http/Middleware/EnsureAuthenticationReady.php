<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticationReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $ready = Schema::hasColumns('users', ['account_status', 'auth_version', 'terms_version', 'terms_accepted_at']);
        foreach (['merchants', 'merchant_memberships', 'merchant_applications', 'audit_logs', 'password_reset_tokens'] as $table) {
            $ready = $ready && Schema::hasTable($table);
        }
        abort_unless($ready && app()->environment(['local', 'testing']) && in_array(config('mail.default'), ['log', 'array'], true), 503, 'Account access is temporarily unavailable. Please try again later.');

        return $next($request);
    }
}
