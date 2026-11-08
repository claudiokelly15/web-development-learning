<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Merchants\LifecycleMutation;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function requestLink(Request $request): RedirectResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : $request->input('email')]);
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:254']]);
        Password::sendResetLink(['email' => $data['email'], 'account_status' => 'active']);

        return redirect()->route('password.request')->with('status', 'If an eligible account matches that email, password reset instructions have been prepared.');
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $credentials = [...$request->validated(), 'account_status' => 'active'];
        $resetUser = null;
        $mutations = app(LifecycleMutation::class);
        $versioned = $mutations->versioned();
        abort_if($versioned, 503, 'Password reset is unavailable pending account lifecycle review.');
        $status = $mutations->run(function () use ($credentials, &$resetUser): string {
            DB::table('password_reset_tokens')->where('email', $credentials['email'])->lockForUpdate()->first();

            return Password::reset($credentials, function (User $user, string $password) use (&$resetUser): void {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($user->account_status !== AccountStatus::Active) {
                    throw ValidationException::withMessages(['email' => 'This reset request could not be completed. Request a new link.']);
                }
                $changes = ['password' => Hash::make($password), 'remember_token' => Str::random(60), 'auth_version' => LifecycleMutation::next($user->auth_version)];
                abort_unless(DB::table('users')->where('id', $user->id)->where('auth_version', $user->auth_version)->where('account_status', 'active')->update($changes) === 1, 409, 'The account state changed.');
                $user->refresh();
                $resetUser = $user;
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('password.request')->withErrors(['email' => 'This reset request could not be completed. Request a new link.']);
        }
        event(new PasswordReset($resetUser));
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your password has been reset. Please sign in.');
    }
}
