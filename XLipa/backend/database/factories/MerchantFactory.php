<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantApplication;
use App\Models\MerchantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Merchant> */
class MerchantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'created_by_user_id' => User::factory(),
            'operational_status' => 'onboarding',
            'verification_status' => 'unverified',
            'profile_version' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Merchant $merchant): void {
            MerchantMembership::forceCreate(['merchant_id' => $merchant->id, 'user_id' => $merchant->created_by_user_id, 'created_by_user_id' => $merchant->created_by_user_id, 'role' => 'owner', 'status' => 'active']);
            MerchantApplication::forceCreate(['merchant_id' => $merchant->id, 'attempt_number' => 1, 'current_slot' => 1, 'profile_version' => 1, 'status' => 'draft']);
        });
    }
}
