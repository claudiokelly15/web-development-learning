<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use PDO;

/** Additive payment-only schema on a distinct, explicitly owned fixture PDO. */
final class PaymentStoreFixture
{
    public static function install(PDO $pdo): void
    {
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || count($databases) !== 1 || $databases[0]['file'] !== ''
            || $pdo === DB::connection()->getPdo() || $pdo->query("SELECT count(*) FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'")->fetchColumn() != 0) {
            throw new LogicException('Payment schema requires a fresh distinct memory fixture.');
        }
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec("CREATE TABLE payment_intents (
            reference TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,actor_id INTEGER NOT NULL,auth_version INTEGER NOT NULL,membership_id INTEGER NOT NULL,authority_version INTEGER NOT NULL,application_id TEXT NOT NULL,
            provider TEXT NOT NULL,amount_tzs INTEGER NOT NULL CHECK(typeof(amount_tzs)='integer' AND amount_tzs BETWEEN 1 AND 1000000000000),currency TEXT NOT NULL CHECK(currency='TZS'),
            idempotency_key TEXT NOT NULL CHECK(length(idempotency_key) BETWEEN 16 AND 80),order_reference TEXT,
            status TEXT NOT NULL CHECK(status IN ('pending','unknown','confirmed','failed')),dispatch_state TEXT NOT NULL CHECK(dispatch_state IN ('unattempted','attempted')),
            provider_reference TEXT,created_at INTEGER NOT NULL,updated_at INTEGER NOT NULL,version INTEGER NOT NULL CHECK(version>=1),last_sequence INTEGER NOT NULL CHECK(last_sequence>=0),
            UNIQUE(merchant_id,provider,idempotency_key),UNIQUE(provider,provider_reference))");
        $pdo->exec("CREATE TABLE payment_events(id TEXT PRIMARY KEY NOT NULL,provider TEXT NOT NULL,provider_event_id TEXT NOT NULL,payment_reference TEXT NOT NULL REFERENCES payment_intents(reference),body_hash TEXT NOT NULL,
            raw_body TEXT CHECK(raw_body IS NULL OR length(CAST(raw_body AS BLOB))<=4096),received_at INTEGER NOT NULL,retain_until INTEGER NOT NULL CHECK(retain_until>received_at),
            normalized_status TEXT NOT NULL CHECK(normalized_status IN ('pending','unknown','confirmed','failed')),sequence INTEGER NOT NULL CHECK(sequence>0),outcome TEXT NOT NULL CHECK(outcome IN ('applied','duplicate_outcome','out_of_order','conflict')),occurred_at INTEGER NOT NULL CHECK(occurred_at>=0),
            UNIQUE(provider,provider_event_id))");
        $pdo->exec('CREATE INDEX payment_events_payment ON payment_events(payment_reference,received_at)');
        $pdo->exec("CREATE TABLE payment_confirmations(id TEXT PRIMARY KEY NOT NULL,payment_reference TEXT NOT NULL UNIQUE REFERENCES payment_intents(reference),event_id TEXT NOT NULL UNIQUE REFERENCES payment_events(id),merchant_id TEXT NOT NULL,
            amount_tzs INTEGER NOT NULL CHECK(typeof(amount_tzs)='integer' AND amount_tzs>0),currency TEXT NOT NULL CHECK(currency='TZS'),confirmed_at INTEGER NOT NULL)");
        $pdo->exec("CREATE TRIGGER payment_identity BEFORE UPDATE ON payment_intents WHEN NEW.reference IS NOT OLD.reference OR NEW.merchant_id IS NOT OLD.merchant_id OR NEW.actor_id IS NOT OLD.actor_id
            OR NEW.auth_version IS NOT OLD.auth_version OR NEW.membership_id IS NOT OLD.membership_id OR NEW.authority_version IS NOT OLD.authority_version OR NEW.application_id IS NOT OLD.application_id
            OR NEW.provider IS NOT OLD.provider OR NEW.amount_tzs IS NOT OLD.amount_tzs OR NEW.currency IS NOT OLD.currency OR NEW.idempotency_key IS NOT OLD.idempotency_key OR NEW.order_reference IS NOT OLD.order_reference
            OR NEW.created_at IS NOT OLD.created_at OR NEW.updated_at<OLD.updated_at OR NEW.version!=OLD.version+1 OR NEW.last_sequence<OLD.last_sequence OR (OLD.provider_reference IS NOT NULL AND NEW.provider_reference IS NOT OLD.provider_reference)
            OR (OLD.status IN ('confirmed','failed') AND NEW.status!=OLD.status) OR (OLD.dispatch_state='attempted' AND NEW.dispatch_state!='attempted') BEGIN SELECT RAISE(ABORT,'Payment invariant denied'); END");
        foreach (['payment_intents', 'payment_events', 'payment_confirmations'] as $table) {
            $pdo->exec("CREATE TRIGGER {$table}_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT,'Payment history is immutable'); END");
            $pdo->exec("CREATE TRIGGER {$table}_replace BEFORE INSERT ON {$table} WHEN EXISTS(SELECT 1 FROM {$table} WHERE ".($table === 'payment_intents' ? 'reference=NEW.reference' : 'id=NEW.id').") BEGIN SELECT RAISE(ABORT,'Payment replacement denied'); END");
        }
        $pdo->exec("CREATE TRIGGER payment_confirmations_update BEFORE UPDATE ON payment_confirmations BEGIN SELECT RAISE(ABORT,'Payment evidence is immutable'); END");
        $pdo->exec("CREATE TRIGGER payment_events_update BEFORE UPDATE ON payment_events WHEN NOT(OLD.raw_body IS NOT NULL AND NEW.raw_body IS NULL AND NEW.id IS OLD.id AND NEW.provider IS OLD.provider AND NEW.provider_event_id IS OLD.provider_event_id
            AND NEW.payment_reference IS OLD.payment_reference AND NEW.body_hash IS OLD.body_hash AND NEW.received_at IS OLD.received_at AND NEW.retain_until IS OLD.retain_until AND NEW.normalized_status IS OLD.normalized_status AND NEW.sequence IS OLD.sequence AND NEW.outcome IS OLD.outcome AND NEW.occurred_at IS OLD.occurred_at) BEGIN SELECT RAISE(ABORT,'Payment evidence is immutable'); END");
    }
}
