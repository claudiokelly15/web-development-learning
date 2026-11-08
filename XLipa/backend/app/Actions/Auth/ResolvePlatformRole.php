<?php

namespace App\Actions\Auth;

use App\Enums\PlatformRole;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** Resolve current authority only; cached relationships and request claims are not grants. */
final class ResolvePlatformRole
{
    public function execute(User $user): ?PlatformRole
    {
        if (! Schema::hasColumns('platform_role_assignments', ['user_id', 'role', 'granted_at', 'revoked_at'])) {
            return null;
        }
        $assignment = PlatformRoleAssignment::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('granted_at')->where('granted_at', '<=', now())->whereNull('revoked_at')
            ->whereHas('user', fn (Builder $query) => $query->where('account_status', 'active')->whereNotNull('email_verified_at'))
            ->first(['role']);

        return $assignment === null ? null : PlatformRole::tryFrom((string) $assignment->getRawOriginal('role'));
    }
}
