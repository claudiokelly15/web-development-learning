<?php

namespace Tests\Support;

use Closure;
use Illuminate\Support\Str;
use LogicException;
use PDO;
use Throwable;

/** Test-only contract exerciser. No production registration, transport or route. */
final class SyntheticVerification
{
    private readonly SyntheticCoordinator $coordinator;

    private int $codeComparisons = 0;

    public function __construct(private readonly PDO $pdo, private readonly SyntheticKeys $keys, private readonly ?Closure $afterCountCommit = null, private readonly bool $storageReady = true, private readonly ?Closure $beforeCountCommit = null)
    {
        $this->coordinator = new SyntheticCoordinator($pdo);
    }

    private function row(string $sql, array $values): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($values);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function write(string $sql, array $values): void
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($values);
        if ($statement->rowCount() !== 1) {
            throw new LogicException('Stale synthetic state.');
        }
    }

    public function insert(string $table, array $data): void
    {
        $this->write('INSERT INTO '.$table.' ('.implode(',', array_keys($data)).') VALUES ('.implode(',', array_fill(0, count($data), '?')).')', array_values($data));
    }

    private function authorized(array $binding): array
    {
        if (! $this->storageReady) {
            throw new LogicException('Synthetic storage or restore hold.');
        }
        $contact = $this->row("SELECT c.* FROM merchant_contact_versions c JOIN merchant_onboarding_contexts x ON x.current_contact_id=c.id AND x.merchant_id=c.merchant_id AND x.contact_actor_user_id=c.actor_user_id AND x.contact_purpose=c.purpose JOIN users u ON u.id=c.actor_user_id JOIN merchants m ON m.id=c.merchant_id JOIN merchant_memberships mm ON mm.merchant_id=c.merchant_id AND mm.user_id=c.actor_user_id WHERE c.id=? AND c.merchant_id=? AND c.actor_user_id=? AND c.purpose=? AND c.state='current' AND u.account_status='active' AND u.email_verified_at IS NOT NULL AND mm.status='active' AND mm.role='owner' AND m.operational_status NOT IN ('suspended','closed')", [$binding['contact'], $binding['merchant'], $binding['actor'], $binding['purpose']]);
        if ($contact === []) {
            throw new LogicException('Synthetic authority unavailable.');
        }

        return $contact;
    }

    private function limit(array $contact, int $now): array
    {
        $target = (new SyntheticCipher($this->keys))->decrypt('phone', $contact['phone_ciphertext'], ['namespace' => 'synthetic', 'merchant' => $contact['merchant_id'], 'record' => $contact['id'], 'purpose' => 'application_phone', 'version' => (int) $contact['version'], 'actor' => (int) $contact['actor_user_id']], $contact['phone_key_ref'], (int) $contact['envelope_revision'])->forCryptoAssertion();
        $scope = hash_hmac('sha256', SyntheticCipher::lp('xlipa-otp-fail-scope-v1', $contact['merchant_id'], (string) $contact['actor_user_id'], $target), $this->keys->resolve('contact', $contact['fingerprint_key_ref']));
        $row = $this->row('SELECT * FROM verification_limit_state WHERE scope_key=? AND scope_key_version=? AND policy_rule=?', [$scope, $contact['fingerprint_key_ref'], 'otp.fail.rolling30.v1']);
        if ($row === []) {
            $this->insert('verification_limit_state', ['scope_key' => $scope, 'scope_key_version' => $contact['fingerprint_key_ref'], 'policy_rule' => 'otp.fail.rolling30.v1', 'merchant_id' => $contact['merchant_id'], 'actor_user_id' => $contact['actor_user_id'], 'last_observed_at' => $now, 'updated_at' => $now]);
            $row = $this->row('SELECT * FROM verification_limit_state WHERE scope_key=?', [$scope]);
        }
        if ($row['merchant_id'] !== $contact['merchant_id'] || (int) $row['actor_user_id'] !== (int) $contact['actor_user_id']) {
            throw new LogicException('Synthetic scope tenant mismatch.');
        }
        $now = max($now, (int) $row['last_observed_at']);
        $failures = [];
        for ($i = 1; $i <= 10; $i++) {
            $value = $row[sprintf('failure_%02d_at', $i)];
            if ($value !== null && (int) $value > $now - 1800000) {
                $failures[] = (int) $value;
            }
        }

        return [$row, $now, $failures];
    }

    public function codeComparisons(): int
    {
        return $this->codeComparisons;
    }

    private function persistLimit(array $row, int $now, array $failures, ?int $lastSend, array $pending = []): void
    {
        $fields = ['last_observed_at=?', 'updated_at=?', 'last_send_at=?', 'state_version=state_version+1'];
        $values = [$now, $now, $lastSend];
        for ($i = 1; $i <= 10; $i++) {
            $fields[] = sprintf('failure_%02d_at=?', $i);
            $values[] = $failures[$i - 1] ?? null;
        }
        foreach ($pending as $field => $value) {
            $fields[] = $field.'=?';
            $values[] = $value;
        }
        $this->write('UPDATE verification_limit_state SET '.implode(',', $fields).' WHERE scope_key=? AND scope_key_version=? AND policy_rule=? AND state_version=?', [...$values, $row['scope_key'], $row['scope_key_version'], $row['policy_rule'], $row['state_version']]);
    }

    private function audit(array $binding, string $event, string $reason, string $request): void
    {
        $this->insert('audit_logs', ['id' => (string) Str::ulid(), 'event_type' => $event, 'actor_user_id' => $binding['actor'], 'merchant_id' => $binding['merchant'], 'request_id' => $request, 'metadata' => json_encode(['reason_code' => $reason], JSON_THROW_ON_ERROR), 'occurred_at' => '2026-11-06 00:00:00']);
    }

    public function issue(array $binding, int $now, string $request): ?string
    {
        return $this->coordinator->run(function () use ($binding, $now, $request): ?string {
            $contact = $this->authorized($binding);
            [$row, $now, $failures] = $this->limit($contact, $now);
            if ($row['pending_audit_event_id'] !== null) {
                return null;
            }
            $existing = $this->row('SELECT * FROM otp_delivery_intents WHERE merchant_id=? AND actor_user_id=? AND request_id=?', [$binding['merchant'], $binding['actor'], $request]);
            if ($existing !== []) {
                if ($existing['contact_id'] !== $binding['contact'] || $existing['purpose'] !== $binding['purpose']) {
                    throw new LogicException('Idempotency binding conflict.');
                }

                return $existing['challenge_id'];
            }
            $allowed = count($failures) < 10 && ($row['last_send_at'] === null || $now >= (int) $row['last_send_at'] + 60000);
            $this->persistLimit($row, $now, $failures, $allowed ? $now : $row['last_send_at']);
            if (! $allowed) {
                return null;
            }
            $old = $this->row('SELECT * FROM phone_otp_challenges WHERE merchant_id=? AND actor_user_id=? AND contact_id=? AND purpose=? AND active_slot=1', [$binding['merchant'], $binding['actor'], $binding['contact'], $binding['purpose']]);
            if ($old !== []) {
                $this->write('UPDATE phone_otp_challenges SET state=?,active_slot=NULL,terminal_at=?,state_version=state_version+1 WHERE id=? AND state_version=?', [$now >= $old['expires_at'] ? 'expired' : 'revoked', $now, $old['id'], $old['state_version']]);
            }
            $id = (string) Str::ulid();
            $base = ['merchant_id' => $binding['merchant'], 'actor_user_id' => $binding['actor'], 'contact_id' => $binding['contact'], 'purpose' => $binding['purpose'], 'transport_mode' => 'simulated'];
            $this->insert('phone_otp_challenges', ['id' => $id, ...$base, 'active_slot' => 1, 'created_at' => $now, 'expires_at' => $now + 300000]);
            $this->insert('otp_delivery_intents', ['id' => (string) Str::ulid(), ...$base, 'challenge_id' => $id, 'request_id' => $request, 'requested_at' => $now]);
            $this->audit($binding, 'otp.requested', $old === [] ? 'initial' : 'resend', $request);

            return $id;
        });
    }

    private function digest(array $challenge, array $contact, string $code): string
    {
        return hash_hmac('sha256', SyntheticCipher::lp('xlipa-otp-code-v1', $challenge['id'], $challenge['merchant_id'], (string) $challenge['actor_user_id'], $contact['id'], (string) $contact['version'], $contact['purpose'], $contact['target_fingerprint'], $contact['fingerprint_key_ref'], $code), $this->keys->resolve('otp', $challenge['digest_key_ref'] ?? 'otp.v1'));
    }

    /** Returns no real delivery evidence; code is a public fixture constant. */
    public function deliver(array $binding, string $id, int $now, string $outcome = 'accepted'): void
    {
        if (! in_array($outcome, ['accepted', 'rejected', 'unknown'], true)) {
            throw new LogicException('Invalid fake outcome.');
        }
        $claimed = $this->coordinator->run(function () use ($binding, $id, $now): bool {
            $contact = $this->authorized($binding);
            $challenge = $this->row('SELECT * FROM phone_otp_challenges WHERE id=? AND merchant_id=? AND actor_user_id=? AND contact_id=? AND purpose=?', [$id, $binding['merchant'], $binding['actor'], $binding['contact'], $binding['purpose']]);
            if ($challenge === [] || $challenge['state'] !== 'queued' || $now >= $challenge['expires_at']) {
                return false;
            }
            $this->write("UPDATE phone_otp_challenges SET state='dispatching',code_digest=?,digest_key_ref='otp.v1',digest_created_at=?,state_version=state_version+1 WHERE id=? AND state='queued'", [$this->digest($challenge, $contact, '000123'), $now, $id]);
            $this->write("UPDATE otp_delivery_intents SET state='claimed',claimed_at=?,lease_expires_at=?,claim_version=claim_version+1 WHERE challenge_id=? AND state='queued'", [$now, min($now + 30000, $challenge['expires_at']), $id]);

            return true;
        });
        if (! $claimed) {
            return;
        }
        // The fake outcome is in memory; there is deliberately no network/queue adapter.
        $this->coordinator->run(function () use ($binding, $id, $now, $outcome): void {
            $challenge = $this->row('SELECT * FROM phone_otp_challenges WHERE id=?', [$id]);
            $intent = $this->row('SELECT * FROM otp_delivery_intents WHERE challenge_id=?', [$id]);
            if ($intent['state'] !== 'claimed') {
                return;
            }
            $failure = match ($outcome) {
                'accepted' => null, 'rejected' => 'provider_rejected', default => 'ambiguous_timeout'
            };
            $this->write('UPDATE otp_delivery_intents SET state=?,failure_code=?,completed_at=?,claim_version=claim_version+1 WHERE id=? AND claim_version=?', [$outcome, $failure, $now, $intent['id'], $intent['claim_version']]);
            if ($challenge['state'] === 'dispatching') {
                $state = match ($outcome) {
                    'accepted' => 'active', 'rejected' => 'delivery_failed', default => 'delivery_unknown'
                };
                $this->write('UPDATE phone_otp_challenges SET state=?,active_slot=?,terminal_at=?,state_version=state_version+1 WHERE id=? AND state_version=?', [$state, $state === 'delivery_failed' ? null : 1, $state === 'delivery_failed' ? $now : null, $id, $challenge['state_version']]);
            }
            $this->audit($binding, 'otp.changed', match ($outcome) {
                'accepted' => 'dispatch_accepted', 'rejected' => 'dispatch_rejected', default => 'delivery_unknown'
            }, $intent['request_id']);
        });
    }

    public function verify(array $binding, string $id, string $code, int $now): ?string
    {
        $obligation = null;
        $result = $this->coordinator->run(function () use ($binding, $id, $code, $now, &$obligation): ?string {
            $contact = $this->authorized($binding);
            [$row, $now, $failures] = $this->limit($contact, $now);
            if ($row['pending_audit_event_id'] !== null) {
                return null;
            }
            $challenge = $this->row("SELECT * FROM phone_otp_challenges WHERE id=? AND merchant_id=? AND actor_user_id=? AND contact_id=? AND purpose=? AND state='active'", [$id, $binding['merchant'], $binding['actor'], $binding['contact'], $binding['purpose']]);
            if ($challenge === [] || $now >= $challenge['expires_at'] || count($failures) >= 10) {
                $this->persistLimit($row, $now, $failures, $row['last_send_at']);

                return null;
            }
            $intent = $this->row('SELECT * FROM otp_delivery_intents WHERE challenge_id=?', [$id]);
            $this->codeComparisons++;
            $matched = preg_match('/^[0-9]{6}$/D', $code) && hash_equals($challenge['code_digest'], $this->digest($challenge, $contact, $code));
            if (! $matched) {
                $failures[] = $now;
                $count = (int) $challenge['failed_attempts'] + 1;
                $this->write('UPDATE phone_otp_challenges SET failed_attempts=?,state=?,active_slot=?,terminal_at=?,state_version=state_version+1 WHERE id=? AND state_version=?', [$count, $count === 5 ? 'exhausted' : 'active', $count === 5 ? null : 1, $count === 5 ? $now : null, $id, $challenge['state_version']]);
                $pending = [];
                if ($count === 5 || count($failures) === 10) {
                    $pending = ['pending_audit_event_id' => (string) Str::ulid(), 'pending_audit_challenge_id' => $id, 'pending_audit_reason_code' => count($failures) === 10 ? 'aggregate_exhausted' : 'exhausted', 'pending_audit_request_id' => $intent['request_id'], 'pending_audit_occurred_at' => SyntheticAuditReconciler::occurredAt($now)];
                    $obligation = [$row, $pending['pending_audit_event_id']];
                }
                $this->persistLimit($row, $now, $failures, $row['last_send_at'], $pending);
                if ($this->beforeCountCommit !== null) {
                    ($this->beforeCountCommit)();
                }

                return null;
            }
            $this->persistLimit($row, $now, $failures, $row['last_send_at']);
            $this->write("UPDATE phone_otp_challenges SET state='consumed',active_slot=NULL,terminal_at=?,state_version=state_version+1 WHERE id=? AND state_version=?", [$now, $id, $challenge['state_version']]);
            $proof = (string) Str::ulid();
            $this->insert('contact_verification_proofs', ['id' => $proof, 'merchant_id' => $binding['merchant'], 'actor_user_id' => $binding['actor'], 'contact_id' => $binding['contact'], 'purpose' => $binding['purpose'], 'challenge_id' => $id, 'channel' => 'sms', 'provenance' => 'simulated', 'policy_version' => 'application-phone-proof-v1', 'verified_at' => $now, 'expires_at' => $now + 1800000]);
            $this->audit($binding, 'otp.verified', 'code_matched', $intent['request_id']);

            return $proof;
        });
        if ($obligation !== null) {
            if ($this->afterCountCommit !== null) {
                ($this->afterCountCommit)();
            }
            try {
                (new SyntheticAuditReconciler($this->pdo))->reconcile(...$obligation);
            } catch (Throwable) {
                // Only exhaustion audit is delayed. The committed obligation gates future use.
            }
        }

        return $result;
    }

    public function consume(array $binding, string $proof, string $application, int $now): bool
    {
        return $this->coordinator->run(function () use ($binding, $proof, $application, $now): bool {
            $contact = $this->authorized($binding);
            [$limit, $now, $failures] = $this->limit($contact, $now);
            if ($limit['pending_audit_event_id'] !== null) {
                return false;
            }
            $this->persistLimit($limit, $now, $failures, $limit['last_send_at']);
            $row = $this->row("SELECT * FROM contact_verification_proofs WHERE id=? AND merchant_id=? AND actor_user_id=? AND contact_id=? AND purpose=? AND state='available' AND verified_at<=? AND expires_at>?", [$proof, $binding['merchant'], $binding['actor'], $binding['contact'], $binding['purpose'], $now, $now]);
            if ($row === []) {
                return false;
            }
            $this->write("UPDATE contact_verification_proofs SET state='consumed',consumed_at=?,consumed_application_id=?,state_version=state_version+1 WHERE id=? AND state_version=?", [$now, $application, $proof, $row['state_version']]);
            $this->audit($binding, 'contact.proof_changed', 'consumed', (string) Str::uuid());

            return true;
        });
    }

    public function revoke(array $binding, int $now, string $reason): void
    {
        $this->coordinator->run(function () use ($binding, $now, $reason): void {
            if (! in_array($reason, ['contact_changed', 'owner_changed', 'authority_lost', 'security_incident', 'policy_retired', 'operator_revoked'], true)) {
                throw new LogicException('Invalid reason.');
            }
            $statement = $this->pdo->prepare("UPDATE contact_verification_proofs SET state='revoked',revoked_at=?,reason_code=?,state_version=state_version+1 WHERE merchant_id=? AND actor_user_id=? AND contact_id=? AND state IN ('available','consumed')");
            $statement->execute([$now, $reason, $binding['merchant'], $binding['actor'], $binding['contact']]);
            $changed = $statement->rowCount();
            $statement = $this->pdo->prepare("UPDATE phone_otp_challenges SET state='revoked',active_slot=NULL,terminal_at=?,state_version=state_version+1 WHERE merchant_id=? AND actor_user_id=? AND contact_id=? AND active_slot=1");
            $statement->execute([$now, $binding['merchant'], $binding['actor'], $binding['contact']]);
            if ($changed + $statement->rowCount() > 0) {
                $this->audit($binding, 'contact.proof_changed', $reason, (string) Str::uuid());
            }
        });
    }

    public function eligibleForLive(string $proof): bool
    {
        // This fixture service cannot certify real delivery or create live eligibility.
        return false;
    }
}
