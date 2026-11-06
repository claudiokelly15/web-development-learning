<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MerchantMembership> */
class MerchantMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'user_id' => User::factory(),
            'role' => 'staff',
            'status' => 'active',
        ];
    }
}
