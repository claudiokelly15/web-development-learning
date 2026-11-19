<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use PDO;

final class PaymentLinkStoreFixture
{
    public static function install(PDO $pdo): void
    {
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || count($databases) !== 1 || $databases[0]['file'] !== '' || $pdo === DB::connection()->getPdo()
            || (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name IN ('payment_intents','ledger_seals')")->fetchColumn() !== 2
            || (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE name IN ('payment_links','checkout_attempts')")->fetchColumn() !== 0) {
            throw new LogicException('Links require the owned isolated P4/P5 memory fixture.');
        }
        $pdo->exec("CREATE TABLE payment_links(id TEXT PRIMARY KEY NOT NULL,public_token TEXT NOT NULL UNIQUE CHECK(length(public_token)=64 AND public_token NOT GLOB '*[^0-9a-f]*'),merchant_id TEXT NOT NULL,actor_id INTEGER NOT NULL,auth_version INTEGER NOT NULL,membership_id INTEGER NOT NULL,authority_version INTEGER NOT NULL,application_id TEXT NOT NULL,
            amount_tzs INTEGER NOT NULL CHECK(typeof(amount_tzs)='integer' AND amount_tzs BETWEEN 1 AND 1000000000000),currency TEXT NOT NULL CHECK(currency='TZS'),description TEXT NOT NULL CHECK(length(description) BETWEEN 1 AND 200 AND instr(description,char(0))=0),business_name TEXT NOT NULL,
            request_key TEXT NOT NULL,state TEXT NOT NULL CHECK(state IN ('active','disabled')),created_at INTEGER NOT NULL CHECK(typeof(created_at)='integer' AND created_at>=0),expires_at INTEGER CHECK(expires_at IS NULL OR (typeof(expires_at)='integer' AND expires_at>created_at)),disabled_at INTEGER,
            CHECK((state='active' AND disabled_at IS NULL) OR (state='disabled' AND typeof(disabled_at)='integer' AND disabled_at>=created_at)),UNIQUE(merchant_id,request_key))");
        $pdo->exec("CREATE TABLE checkout_attempts(id TEXT PRIMARY KEY NOT NULL,link_id TEXT NOT NULL REFERENCES payment_links(id),customer_hash TEXT NOT NULL CHECK(length(customer_hash)=64),nonce_hash TEXT NOT NULL UNIQUE CHECK(length(nonce_hash)=64),created_at INTEGER NOT NULL CHECK(typeof(created_at)='integer' AND created_at>=0),payment_reference TEXT UNIQUE REFERENCES payment_intents(reference),UNIQUE(link_id,customer_hash))");
        $pdo->exec("CREATE TRIGGER link_identity BEFORE UPDATE ON payment_links WHEN NOT(OLD.state='active' AND NEW.state='disabled' AND NEW.disabled_at>=OLD.created_at
            AND NEW.id IS OLD.id AND NEW.public_token IS OLD.public_token AND NEW.merchant_id IS OLD.merchant_id AND NEW.actor_id IS OLD.actor_id AND NEW.auth_version IS OLD.auth_version AND NEW.membership_id IS OLD.membership_id AND NEW.authority_version IS OLD.authority_version AND NEW.application_id IS OLD.application_id
            AND NEW.amount_tzs IS OLD.amount_tzs AND NEW.currency IS OLD.currency AND NEW.description IS OLD.description AND NEW.business_name IS OLD.business_name AND NEW.request_key IS OLD.request_key AND NEW.created_at IS OLD.created_at AND NEW.expires_at IS OLD.expires_at)
            BEGIN SELECT RAISE(ABORT,'Link identity is immutable'); END");
        $pdo->exec("CREATE TRIGGER attempt_identity BEFORE UPDATE ON checkout_attempts WHEN NOT(OLD.payment_reference IS NULL AND NEW.payment_reference IS NOT NULL AND NEW.id IS OLD.id AND NEW.link_id IS OLD.link_id AND NEW.customer_hash IS OLD.customer_hash AND NEW.nonce_hash IS OLD.nonce_hash AND NEW.created_at IS OLD.created_at
            AND EXISTS(SELECT 1 FROM payment_links l JOIN payment_intents p ON p.reference=NEW.payment_reference WHERE l.id=NEW.link_id AND p.merchant_id=l.merchant_id AND p.actor_id=l.actor_id AND p.auth_version=l.auth_version AND p.membership_id=l.membership_id AND p.authority_version=l.authority_version AND p.application_id=l.application_id
            AND p.amount_tzs=l.amount_tzs AND p.currency=l.currency AND p.idempotency_key='checkout_'||NEW.id AND p.order_reference=NEW.id))
            BEGIN SELECT RAISE(ABORT,'Checkout association is immutable'); END");
        foreach (['payment_links', 'checkout_attempts'] as $table) {
            $pdo->exec("CREATE TRIGGER {$table}_delete BEFORE DELETE ON $table BEGIN SELECT RAISE(ABORT,'Link history is immutable'); END");
            $identity = $table === 'payment_links' ? 'id=NEW.id OR public_token=NEW.public_token OR (merchant_id=NEW.merchant_id AND request_key=NEW.request_key)' : 'id=NEW.id OR nonce_hash=NEW.nonce_hash OR (link_id=NEW.link_id AND customer_hash=NEW.customer_hash)';
            $pdo->exec("CREATE TRIGGER {$table}_replace BEFORE INSERT ON $table WHEN EXISTS(SELECT 1 FROM $table WHERE $identity) BEGIN SELECT RAISE(ABORT,'Link replacement denied'); END");
        }
        $pdo->exec("CREATE TRIGGER attempt_initial BEFORE INSERT ON checkout_attempts WHEN NEW.payment_reference IS NOT NULL BEGIN SELECT RAISE(ABORT,'Checkout must reserve before payment creation'); END");
    }
}
