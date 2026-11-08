<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\VerifyAccountEmail;
use App\Actions\Merchants\LifecycleMutation;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail() ? redirect()->route('account') : view('auth.verify');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (app(LifecycleMutation::class)->versioned()) {
            $version = filter_var($request->query('binding_version'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_unless($version !== false, 409, 'Request a current verification link.');
            app(VerifyAccountEmail::class)->execute($request->user(), $version);
        } else {
            $request->fulfill();
        }

        return redirect()->route('account')->with('status', 'Your email address has been verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return redirect()->route('verification.notice')->with('status', 'If verification is needed, a new verification message has been prepared.');
    }
}
