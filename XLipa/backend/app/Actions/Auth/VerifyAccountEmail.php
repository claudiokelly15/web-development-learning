<?php

namespace App\Actions\Auth;

use App\Actions\Merchants\LifecycleMutation;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

final class VerifyAccountEmail
{
    public function execute(User $expected, int $version): void
    {
        $mutations = app(LifecycleMutation::class);
        abort_unless($mutations->versioned(), 503, 'Versioned verification is unavailable.');
        $changed = $mutations->run(function () use ($expected, $version): bool {
            $current = User::query()->findOrFail($expected->id);
            abort_unless($current->getRawOriginal('account_status') === 'active' && $current->auth_version === $expected->auth_version && $current->email === $expected->email && $current->email_binding_version === $version, 409, 'The verification request is stale.');
            if ($current->email_verified_at !== null) {
                return false;
            }
            abort_unless(DB::table('users')->where('id', $current->id)->where('auth_version', $current->auth_version)->where('email_binding_version', $version)->whereNull('email_verified_at')->update(['email_verified_at' => now(), 'email_binding_version' => LifecycleMutation::next($version)]) === 1, 409, 'The verification request is stale.');

            return true;
        });
        if ($changed) {
            event(new Verified($expected->fresh()));
        }
    }
}
