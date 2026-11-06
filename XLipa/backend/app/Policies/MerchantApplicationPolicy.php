<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\MerchantApplication;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class MerchantApplicationPolicy
{
    public function view(User $user, MerchantApplication $application, Merchant $merchant): bool
    {
        return Gate::forUser($user)->allows('viewSummary', $merchant)
            && $merchant->memberships()->where('user_id', $user->getKey())->where('status', 'active')->whereIn('role', ['owner', 'manager'])->exists()
            && $merchant->applications()->whereKey($application->getKey())->where('current_slot', 1)->exists();
    }
}
