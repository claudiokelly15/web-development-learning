<?php

namespace Tests\Support;

use Closure;
use LogicException;
use PDO;

/** Keyless internal fixture writer. Never registered as an endpoint or job. */
final class SyntheticAuditReconciler
{
    private readonly SyntheticCoordinator $coordinator;

    public function __construct(private readonly PDO $pdo, private readonly ?Closure $afterInsert = null)
    {
        $this->coordinator = new SyntheticCoordinator($pdo);
    }

    public static function occurredAt(int $milliseconds): string
    {
        if ($milliseconds < 0 || $milliseconds > 253402300799999) {
            throw new LogicException('R06 clock outside representable UTC range.');
        }

        return gmdate('Y-m-d H:i:s', intdiv($milliseconds, 1000)).sprintf('.%03d', $milliseconds % 1000);
    }

    public function reconcile(array $scope, string $eventId): bool
    {
        return $this->coordinator->run(function () use ($scope, $eventId): bool {
            $statement = $this->pdo->prepare('SELECT * FROM verification_limit_state WHERE scope_key=? AND scope_key_version=? AND policy_rule=?');
            $statement->execute([$scope['scope_key'], $scope['scope_key_version'], $scope['policy_rule']]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (! $row || $row['pending_audit_event_id'] !== $eventId) {
                return false;
            }
            $lineage = $this->pdo->prepare("SELECT 1 FROM phone_otp_challenges c JOIN otp_delivery_intents d ON d.challenge_id=c.id WHERE c.id=? AND c.merchant_id=? AND c.actor_user_id=? AND c.purpose='application_phone' AND d.merchant_id=c.merchant_id AND d.actor_user_id=c.actor_user_id AND d.request_id=?");
            $lineage->execute([$row['pending_audit_challenge_id'], $row['merchant_id'], $row['actor_user_id'], $row['pending_audit_request_id']]);
            if (! $lineage->fetchColumn() || ! in_array($row['pending_audit_reason_code'], ['exhausted', 'aggregate_exhausted'], true)) {
                throw new LogicException('R06 invalid obligation lineage.');
            }
            $expected = ['id' => $eventId, 'event_type' => 'otp.changed', 'actor_user_id' => (int) $row['actor_user_id'], 'merchant_id' => $row['merchant_id'], 'target_user_id' => null, 'application_id' => null,
                'request_id' => $row['pending_audit_request_id'], 'metadata' => json_encode(['reason_code' => $row['pending_audit_reason_code']], JSON_THROW_ON_ERROR), 'occurred_at' => $row['pending_audit_occurred_at']];
            $statement = $this->pdo->prepare('SELECT * FROM audit_logs WHERE id=?');
            $statement->execute([$eventId]);
            $existing = $statement->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                foreach ($expected as $field => $value) {
                    if ($existing[$field] !== $value) {
                        throw new LogicException('R06 audit payload conflict.');
                    }
                }
            } else {
                $insert = $this->pdo->prepare('INSERT INTO audit_logs ('.implode(',', array_keys($expected)).') VALUES ('.implode(',', array_fill(0, count($expected), '?')).')');
                $insert->execute(array_values($expected));
            }
            if ($this->afterInsert !== null) {
                ($this->afterInsert)();
            }
            $clear = $this->pdo->prepare('UPDATE verification_limit_state SET pending_audit_event_id=NULL,pending_audit_challenge_id=NULL,pending_audit_reason_code=NULL,pending_audit_request_id=NULL,pending_audit_occurred_at=NULL,state_version=state_version+1 WHERE scope_key=? AND scope_key_version=? AND policy_rule=? AND state_version=? AND pending_audit_event_id=?');
            $clear->execute([$row['scope_key'], $row['scope_key_version'], $row['policy_rule'], $row['state_version'], $eventId]);
            if ($clear->rowCount() !== 1) {
                throw new LogicException('R06 stale discharge.');
            }

            return true;
        });
    }
}
