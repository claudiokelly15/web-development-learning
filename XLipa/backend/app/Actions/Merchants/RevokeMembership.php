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
    public function execute(User $actor, Merchant $merchant, MerchantMembership $membership, ?int $expectedAuthority = null): void
    {
        $mutations = app(LifecycleMutation::class);
        $versioned = $mutations->versioned();
        $mutations->run(function () use ($actor, $merchant, $membership, $expectedAuthority, $versioned, $mutations): void {
            $actorVersion = $actor->auth_version;
            $merchant = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);
            $actor = User::query()->findOrFail($actor->id);
            if ($actor->account_status !== AccountStatus::Active || $actor->auth_version !== $actorVersion || ! $merchant->memberships()->where('user_id', $actor->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->exists()) {
                throw new AuthorizationException('Active owner membership is required.');
            }
            $target = $merchant->memberships()->lockForUpdate()->findOrFail($membership->id);
            if ($versioned) {
                abort_unless($expectedAuthority !== null && $target->authority_version === $expectedAuthority, 409, 'The membership state changed.');
            }
            if ($target->status === MembershipStatus::Revoked) {
                return;
            }
            if ($target->role === MerchantRole::Owner && $merchant->memberships()->where('role', 'owner')->where('status', 'active')->count() <= 1) {
                throw new LogicException('The last active owner cannot be revoked.');
            }
            $changes = ['status' => MembershipStatus::Revoked, 'revoked_at' => now()];
            if ($versioned) {
                $changes['authority_version'] = LifecycleMutation::next($expectedAuthority);
            }
            abort_unless(DB::table('merchant_memberships')->where('id', $target->id)->when($versioned, fn ($query) => $query->where('authority_version', $expectedAuthority))->update($changes) === 1, 409, 'The membership state changed.');
            if ($versioned) {
                $mutations->invalidate($target->user_id, $actor->id, 'authority_lost', $merchant->id);
            }
            AuditLog::forceCreate(['event_type' => AuditEvent::MembershipRevoked, 'actor_user_id' => $actor->id, 'target_user_id' => $target->user_id, 'merchant_id' => $merchant->id, 'request_id' => (string) Str::uuid(), 'metadata' => ['role' => $target->role->value], 'occurred_at' => now()]);
        });
    }
}
