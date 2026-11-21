<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use PDO;

/** Additive evidence only; existing payment and ledger contracts are unchanged. */
final class AnalyticsStoreFixture
{
    public static function install(PDO $pdo): void
    {
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || count($databases) !== 1 || $databases[0]['file'] !== '' || $pdo === DB::connection()->getPdo()
            || (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE name IN ('payment_confirmations','ledger_seals','checkout_attempts')")->fetchColumn() !== 3) {
            throw new LogicException('Analytics requires the distinct owned P4-P7 memory store.');
        }
        $pdo->exec('CREATE TABLE analytics_attribution(payment_reference TEXT PRIMARY KEY NOT NULL REFERENCES payment_intents(reference),merchant_id TEXT NOT NULL,source TEXT NOT NULL,provenance TEXT NOT NULL,campaign TEXT,link_id TEXT REFERENCES payment_links(id),captured_at INTEGER NOT NULL,source_hash TEXT NOT NULL,claims_body TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE analytics_methods(payment_reference TEXT PRIMARY KEY NOT NULL REFERENCES payment_intents(reference),merchant_id TEXT NOT NULL,confirmation_id TEXT NOT NULL UNIQUE REFERENCES payment_confirmations(id),event_id TEXT NOT NULL UNIQUE,method TEXT NOT NULL,body TEXT NOT NULL,signature TEXT NOT NULL,body_hash TEXT NOT NULL,received_at INTEGER NOT NULL)');
        $pdo->exec('CREATE INDEX analytics_attribution_merchant ON analytics_attribution(merchant_id,payment_reference)');
        foreach (['analytics_attribution', 'analytics_methods'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $pdo->exec("CREATE TRIGGER {$table}_".strtolower($operation)." BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT,'Analytics evidence is immutable'); END");
            }
            $pdo->exec("CREATE TRIGGER {$table}_replace BEFORE INSERT ON $table WHEN EXISTS(SELECT 1 FROM $table WHERE payment_reference=NEW.payment_reference) BEGIN SELECT RAISE(ABORT,'Analytics replacement denied'); END");
            $pdo->exec("CREATE TRIGGER {$table}_merchant BEFORE INSERT ON $table WHEN NOT EXISTS(SELECT 1 FROM payment_intents WHERE reference=NEW.payment_reference AND merchant_id=NEW.merchant_id) BEGIN SELECT RAISE(ABORT,'Merchant payment mismatch'); END");
        }
        $pdo->exec("CREATE TRIGGER analytics_method_confirmation BEFORE INSERT ON analytics_methods WHEN NOT EXISTS(SELECT 1 FROM payment_confirmations WHERE id=NEW.confirmation_id AND payment_reference=NEW.payment_reference AND merchant_id=NEW.merchant_id) BEGIN SELECT RAISE(ABORT,'Confirmation mismatch'); END");
    }
}
