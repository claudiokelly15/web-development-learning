<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;

class MerchantPolicy
{
    public function viewSummary(User $user, Merchant $merchant): bool
    {
        return User::query()->whereKey($user->getKey())->where('account_status', 'active')->whereNotNull('email_verified_at')->exists()
            && Merchant::forMember($user)->whereKey($merchant->getKey())->exists();
    }
}
