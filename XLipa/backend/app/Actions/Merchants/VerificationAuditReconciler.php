<?php

namespace App\Actions\Merchants;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Keyless internal R06 reconciliation. No route, timer or privileged override. */
final class VerificationAuditReconciler
{
    public function __construct(private readonly VerificationStore $store) {}

    public static function occurredAt(int $milliseconds): string
    {
        if ($milliseconds < 0 || $milliseconds > 253402300799999) {
            throw new LogicException('Verification clock unavailable.');
        }

        return gmdate('Y-m-d H:i:s', intdiv($milliseconds, 1000)).sprintf('.%03d', $milliseconds % 1000);
    }

    public function reconcile(string $scope, string $keyVersion, string $event): bool
    {
        return $this->store->run(function () use ($scope, $keyVersion, $event): bool {
            $where = ['scope_key' => $scope, 'scope_key_version' => $keyVersion, 'policy_rule' => 'otp.fail.rolling30.v1'];
            $row = DB::table('verification_limit_state')->where($where)->first();
            if (! $row || $row->pending_audit_event_id !== $event) {
                return false;
            }
            $lineage = DB::table('phone_otp_challenges as c')->join('otp_delivery_intents as d', 'd.challenge_id', '=', 'c.id')
                ->where('c.id', $row->pending_audit_challenge_id)->where('c.merchant_id', $row->merchant_id)->where('c.actor_user_id', $row->actor_user_id)
                ->where('c.purpose', 'application_phone')->where('d.merchant_id', $row->merchant_id)->where('d.actor_user_id', $row->actor_user_id)
                ->where('d.request_id', $row->pending_audit_request_id)->exists();
            if (! $lineage || ! in_array($row->pending_audit_reason_code, ['exhausted', 'aggregate_exhausted'], true)) {
                throw new LogicException('Verification audit unavailable.');
            }
            $expected = ['id' => $event, 'event_type' => 'otp.changed', 'actor_user_id' => (int) $row->actor_user_id, 'target_user_id' => null, 'merchant_id' => $row->merchant_id, 'application_id' => null, 'request_id' => $row->pending_audit_request_id, 'metadata' => json_encode(['reason_code' => $row->pending_audit_reason_code], JSON_THROW_ON_ERROR), 'occurred_at' => $row->pending_audit_occurred_at];
            $existing = DB::table('audit_logs')->where('id', $event)->first();
            if ($existing) {
                foreach ($expected as $field => $value) {
                    if ($value !== $existing->$field) {
                        throw new LogicException('Verification audit conflict.');
                    }
                }
            } else {
                DB::table('audit_logs')->insert($expected);
            }
            $this->store->change('verification_limit_state', [...$where, 'state_version' => $row->state_version, 'pending_audit_event_id' => $event], [
                'pending_audit_event_id' => null, 'pending_audit_challenge_id' => null, 'pending_audit_reason_code' => null, 'pending_audit_request_id' => null, 'pending_audit_occurred_at' => null, 'state_version' => $row->state_version + 1,
            ]);

            return true;
        });
    }
}
