<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_type' => 'account.status_changed',
            'actor_user_id' => User::factory(),
            'target_user_id' => User::factory(),
            'request_id' => (string) Str::uuid(),
            'metadata' => ['from' => 'active', 'to' => 'suspended'],
            'occurred_at' => now(),
        ];
    }
}
