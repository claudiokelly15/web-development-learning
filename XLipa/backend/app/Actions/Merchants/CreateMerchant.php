<?php

namespace App\Actions\Merchants;

use App\Enums\AccountStatus;
use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateMerchant
{
    public function execute(User $owner, string $businessName): Merchant
    {
        $businessName = trim($businessName);
        if ($businessName === '' || mb_strlen($businessName) > 200) {
            throw new InvalidArgumentException('A bounded business name is required.');
        }

        return DB::transaction(function () use ($owner, $businessName): Merchant {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->getKey());
            if ($owner->account_status !== AccountStatus::Active) {
                throw new AuthorizationException('An active owner is required.');
            }

            $merchant = Merchant::forceCreate(['business_name' => $businessName, 'created_by_user_id' => $owner->id]);
            $merchant->memberships()->forceCreate(['user_id' => $owner->id, 'created_by_user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
            $application = $merchant->applications()->forceCreate(['attempt_number' => 1, 'current_slot' => 1, 'profile_version' => 1, 'status' => 'draft']);
            AuditLog::forceCreate(['event_type' => AuditEvent::MerchantCreated, 'actor_user_id' => $owner->id, 'target_user_id' => $owner->id, 'merchant_id' => $merchant->id, 'application_id' => $application->id, 'request_id' => (string) Str::uuid(), 'occurred_at' => now()]);

            return $merchant->refresh();
        }, 3);
    }
}
