<?php

namespace App\Actions\Merchants;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Existing lifecycle operations only; partial SE profiles never fall back to legacy writes. */
final class LifecycleMutation
{
    public function versioned(): bool
    {
        $present = Schema::hasColumn('users', 'email_binding_version') || Schema::hasColumn('merchant_memberships', 'authority_version') || Schema::hasTable(SubmissionEvidenceSchema::TABLE);
        abort_if($present && ! SubmissionEvidenceSchema::ready(), 503, 'Account changes are unavailable.');

        return $present;
    }

    public function run(Closure $operation): mixed
    {
        return $this->versioned() ? app(VerificationStore::class)->run($operation) : DB::transaction($operation, 1);
    }

    public static function next(int $version): int
    {
        abort_if($version < 1 || $version === PHP_INT_MAX, 409, 'The account state requires review.');

        return $version + 1;
    }

    /** Revoke readiness, retaining immutable consumption and pending audit history. Caller owns writer. */
    public function invalidate(int $subject, int $actor, string $reason, ?string $merchant = null): void
    {
        $proofs = DB::table('contact_verification_proofs')->where('actor_user_id', $subject)->whereIn('state', ['available', 'consumed']);
        $challenges = DB::table('phone_otp_challenges')->where('actor_user_id', $subject)->where('active_slot', 1);
        if ($merchant !== null) {
            $proofs->where('merchant_id', $merchant);
            $challenges->where('merchant_id', $merchant);
        }
        $now = max((int) now()->getTimestampMs(), (int) (clone $proofs)->max('verified_at'), (int) (clone $proofs)->max('consumed_at'), (int) (clone $challenges)->max('created_at'), (int) (clone $challenges)->max('digest_created_at'));
        $merchants = (clone $proofs)->pluck('merchant_id')->merge((clone $challenges)->pluck('merchant_id'))->unique();
        $proofs->update(['state' => 'revoked', 'state_version' => DB::raw('state_version+1'), 'revoked_at' => $now, 'reason_code' => $reason]);
        $challenges->update(['state' => 'revoked', 'state_version' => DB::raw('state_version+1'), 'active_slot' => null, 'terminal_at' => $now]);
        foreach ($merchants as $id) {
            DB::table('audit_logs')->insert(['id' => (string) Str::ulid(), 'event_type' => 'contact.proof_changed', 'actor_user_id' => $actor, 'target_user_id' => $subject, 'merchant_id' => $id, 'request_id' => (string) Str::uuid(), 'metadata' => json_encode(['reason_code' => $reason], JSON_THROW_ON_ERROR), 'occurred_at' => VerificationAuditReconciler::occurredAt($now)]);
        }
    }
}
