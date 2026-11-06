<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt(['email' => $request->validated('email'), 'password' => $request->validated('password'), 'account_status' => 'active'], false)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();
        $request->session()->put('auth_version', $request->user()->auth_version);
        $intended = $request->session()->pull('url.intended');
        $root = parse_url(config('app.url'));
        $target = is_string($intended) ? parse_url($intended) : false;
        if ($target && ($target['host'] ?? '') === ($root['host'] ?? '') && ($target['scheme'] ?? '') === ($root['scheme'] ?? '') && ($target['port'] ?? null) === ($root['port'] ?? null) && preg_match('~^/email/verify/[0-9]+/[a-f0-9]{40}$~', $target['path'] ?? '') && URL::hasValidSignature(Request::create($intended))) {
            return redirect()->to($intended);
        }

        return redirect()->route($request->user()->hasVerifiedEmail() ? 'account' : 'verification.notice');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
