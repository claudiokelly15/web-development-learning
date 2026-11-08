<?php

namespace App\Policies;

use App\Actions\Auth\ResolvePlatformRole;
use App\Enums\PlatformRole;
use App\Models\User;

/** Action-specific workspace access only. No wildcard/before or management grant. */
class PlatformRoleAssignmentPolicy
{
    public function __construct(private readonly ResolvePlatformRole $roles) {}

    public function viewChiefWorkspace(User $user): bool
    {
        return $this->roles->execute($user) === PlatformRole::ChiefAdmin;
    }

    public function viewApplicationReviewQueue(User $user): bool
    {
        return $this->roles->execute($user) === PlatformRole::ChiefAdmin;
    }

    public function viewStaffWorkspace(User $user): bool
    {
        return $this->roles->execute($user) === PlatformRole::Admin;
    }

    public function viewOwnWorkspaceAccess(User $user): bool
    {
        return in_array($this->roles->execute($user), [PlatformRole::ChiefAdmin, PlatformRole::Admin], true);
    }
}
