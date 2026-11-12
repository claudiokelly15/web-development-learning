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
    public function execute(User $owner, ?string $businessName, ?string $businessPhone = null): Merchant
    {
        if ($businessName !== null && (trim($businessName) === '' || mb_strlen(trim($businessName)) > 200)) {
            throw new InvalidArgumentException('A bounded business name is required.');
        }
        $businessName = trim($businessName ?? '');

        if ($businessPhone !== null && ! preg_match('/^\+[1-9][0-9]{7,14}$/D', $businessPhone)) {
            throw new InvalidArgumentException('A valid registration contact is required.');
        }

        return DB::transaction(function () use ($owner, $businessName, $businessPhone): Merchant {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->getKey());
            if ($owner->account_status !== AccountStatus::Active) {
                throw new AuthorizationException('An active owner is required.');
            }

            $merchant = Merchant::forceCreate(['business_name' => $businessName, 'business_phone' => $businessPhone, 'created_by_user_id' => $owner->id]);
            $merchant->memberships()->forceCreate(['user_id' => $owner->id, 'created_by_user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
            $application = $merchant->applications()->forceCreate(['attempt_number' => 1, 'current_slot' => 1, 'profile_version' => 1, 'status' => 'draft']);
            AuditLog::forceCreate(['event_type' => AuditEvent::MerchantCreated, 'actor_user_id' => $owner->id, 'target_user_id' => $owner->id, 'merchant_id' => $merchant->id, 'application_id' => $application->id, 'request_id' => (string) Str::uuid(), 'occurred_at' => now()]);

            return $merchant->refresh();
        }, 1);
    }
}
