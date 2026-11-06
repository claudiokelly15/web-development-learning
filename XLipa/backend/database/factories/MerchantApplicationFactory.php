<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MerchantApplication> */
class MerchantApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'attempt_number' => 2,
            'current_slot' => null,
            'status' => 'draft',
            'profile_version' => 1,
        ];
    }
}
