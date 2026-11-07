<?php

namespace Tests\Support;

use PDO;
use Throwable;

/** Classify only; never repair, adopt ledger rows, or execute a migration. */
final class OfflineStateClassifier
{
    public function __construct(private readonly array $foundationObjects, private readonly array $foundationLedger, private readonly array $finalObjects, private readonly string $activationName) {}

    public function classify(PDO $pdo): string
    {
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' || (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1 || (int) $pdo->query('PRAGMA query_only')->fetchColumn() !== 1) {
                return 'quarantine:connection';
            }
            $pdo->exec('BEGIN');
            try {
                if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $pdo->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
                    return 'quarantine:integrity';
                }
                $objects = [];
                foreach ($pdo->query("SELECT type,name,sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY type,name")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $objects[$row['type'].':'.$row['name']] = hash('sha256', $row['sql']);
                }
                $ledger = $pdo->query('SELECT migration,batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
                if ($objects === $this->foundationObjects && $ledger === $this->foundationLedger) {
                    return 'foundation';
                }
                $activation = array_values(array_filter($ledger, fn (array $row): bool => $row['migration'] === $this->activationName));
                $old = array_values(array_filter($ledger, fn (array $row): bool => $row['migration'] !== $this->activationName));
                if ($objects === $this->finalObjects && $old === $this->foundationLedger && $activation === []) {
                    return 'quarantine:schema_without_ledger';
                }
                if ($objects === $this->finalObjects && $old === $this->foundationLedger && $activation === [['migration' => $this->activationName, 'batch' => 3]]) {
                    foreach (['merchant_identity_records', 'merchant_contact_versions', 'merchant_onboarding_contexts', 'phone_otp_challenges', 'otp_delivery_intents', 'contact_verification_proofs', 'verification_limit_state'] as $table) {
                        if ((int) $pdo->query('SELECT count(*) FROM '.$table)->fetchColumn() !== 0) {
                            return 'quarantine:nonempty_security_tables';
                        }
                    }

                    return 'completed';
                }

                return $activation !== [] ? 'quarantine:ledger_schema_mismatch' : 'quarantine:drift_or_partial';
            } finally {
                $pdo->exec('ROLLBACK');
            }
        } catch (Throwable) {
            return 'quarantine:ambiguous';
        }
    }
}
