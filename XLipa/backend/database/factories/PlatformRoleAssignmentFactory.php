<?php

namespace Database\Factories;

use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlatformRoleAssignment> */
class PlatformRoleAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => 'admin',
            'granted_by_user_id' => User::factory(),
            'granted_at' => now(),
        ];
    }
}
