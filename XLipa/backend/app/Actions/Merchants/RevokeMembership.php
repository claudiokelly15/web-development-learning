<?php

namespace App\Actions\Merchants;

use App\Enums\AccountStatus;
use App\Enums\AuditEvent;
use App\Enums\MembershipStatus;
use App\Enums\MerchantRole;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class RevokeMembership
{
    public function execute(User $actor, Merchant $merchant, MerchantMembership $membership): void
    {
        DB::transaction(function () use ($actor, $merchant, $membership): void {
            $merchant = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);
            $actor = User::query()->findOrFail($actor->id);
            if ($actor->account_status !== AccountStatus::Active || ! $merchant->memberships()->where('user_id', $actor->id)->where('role', 'owner')->where('status', 'active')->exists()) {
                throw new AuthorizationException('Active owner membership is required.');
            }
            $target = $merchant->memberships()->lockForUpdate()->findOrFail($membership->id);
            if ($target->status === MembershipStatus::Revoked) {
                return;
            }
            if ($target->role === MerchantRole::Owner && $merchant->memberships()->where('role', 'owner')->where('status', 'active')->count() <= 1) {
                throw new LogicException('The last active owner cannot be revoked.');
            }
            $target->forceFill(['status' => MembershipStatus::Revoked, 'revoked_at' => now()])->save();
            AuditLog::forceCreate(['event_type' => AuditEvent::MembershipRevoked, 'actor_user_id' => $actor->id, 'target_user_id' => $target->user_id, 'merchant_id' => $merchant->id, 'request_id' => (string) Str::uuid(), 'metadata' => ['role' => $target->role->value], 'occurred_at' => now()]);
        }, 3);
    }
}
