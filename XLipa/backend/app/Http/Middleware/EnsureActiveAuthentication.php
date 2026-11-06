<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::query()->find($request->user()->getAuthIdentifier());
        if (! $user || $user->account_status !== AccountStatus::Active || $request->session()->get('auth_version') !== $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Please sign in again.']);
        }
        Auth::setUser($user);

        return $next($request);
    }
}
