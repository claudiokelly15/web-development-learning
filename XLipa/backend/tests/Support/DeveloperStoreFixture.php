<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use PDO;

final class DeveloperStoreFixture
{
    public static function install(PDO $pdo): void
    {
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || count($databases) !== 1 || $databases[0]['file'] !== '' || $pdo === DB::connection()->getPdo()
            || (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE name='payment_links'")->fetchColumn() !== 1) {
            throw new LogicException('P9 requires the separate owned P6 memory fixture.');
        }
        $pdo->exec('CREATE TABLE api_credentials(id TEXT PRIMARY KEY,merchant_id TEXT NOT NULL,authority TEXT NOT NULL,token_hash TEXT NOT NULL UNIQUE,scopes TEXT NOT NULL,created_at INTEGER NOT NULL,expires_at INTEGER NOT NULL,last_four TEXT NOT NULL,rotated_from TEXT REFERENCES api_credentials(id),revoked_at INTEGER)');
        $pdo->exec('CREATE TABLE api_rate_windows(credential_id TEXT PRIMARY KEY REFERENCES api_credentials(id),window INTEGER NOT NULL,count INTEGER NOT NULL CHECK(count BETWEEN 1 AND 120))');
        $pdo->exec("CREATE TRIGGER credential_identity BEFORE UPDATE ON api_credentials WHEN NOT(OLD.revoked_at IS NULL AND NEW.revoked_at>=OLD.created_at AND NEW.id IS OLD.id AND NEW.merchant_id IS OLD.merchant_id AND NEW.authority IS OLD.authority AND NEW.token_hash IS OLD.token_hash AND NEW.scopes IS OLD.scopes AND NEW.created_at IS OLD.created_at AND NEW.expires_at IS OLD.expires_at AND NEW.last_four IS OLD.last_four AND NEW.rotated_from IS OLD.rotated_from) BEGIN SELECT RAISE(ABORT,'Credential identity is immutable'); END");
        $pdo->exec("CREATE TABLE checkout_widgets(id TEXT PRIMARY KEY,merchant_id TEXT NOT NULL,link_id TEXT NOT NULL REFERENCES payment_links(id),kind TEXT NOT NULL CHECK(kind IN ('qr','button')),mode TEXT NOT NULL CHECK(mode IN ('dynamic','reusable')),UNIQUE(link_id,kind,mode))");
        $pdo->exec('CREATE TABLE merchant_webhook_endpoints(id TEXT PRIMARY KEY,merchant_id TEXT NOT NULL,url TEXT NOT NULL,encrypted_secret TEXT NOT NULL,enabled INTEGER NOT NULL CHECK(enabled IN (0,1)),created_at INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE merchant_webhook_events(id TEXT PRIMARY KEY,merchant_id TEXT NOT NULL,confirmation_id TEXT NOT NULL UNIQUE,body TEXT NOT NULL,created_at INTEGER NOT NULL)');
        $pdo->exec("CREATE TABLE merchant_webhook_deliveries(id TEXT PRIMARY KEY,merchant_id TEXT NOT NULL,event_id TEXT NOT NULL REFERENCES merchant_webhook_events(id),endpoint_id TEXT NOT NULL REFERENCES merchant_webhook_endpoints(id),status TEXT NOT NULL CHECK(status IN ('pending','sending','retry','delivered','exhausted','disabled')),attempts INTEGER NOT NULL CHECK(attempts BETWEEN 0 AND 3),next_at INTEGER NOT NULL,http_status INTEGER,claim TEXT,UNIQUE(event_id,endpoint_id))");
        $pdo->exec('CREATE TABLE merchant_webhook_attempts(id TEXT PRIMARY KEY,delivery_id TEXT NOT NULL REFERENCES merchant_webhook_deliveries(id),number INTEGER NOT NULL,created_at INTEGER NOT NULL,http_status INTEGER,UNIQUE(delivery_id,number))');
        $pdo->exec('CREATE TABLE merchant_webhook_receipts(event_id TEXT PRIMARY KEY,body_hash TEXT NOT NULL,received_at INTEGER NOT NULL)');
        $pdo->exec("CREATE TRIGGER widget_merchant BEFORE INSERT ON checkout_widgets WHEN NOT EXISTS(SELECT 1 FROM payment_links WHERE id=NEW.link_id AND merchant_id=NEW.merchant_id) BEGIN SELECT RAISE(ABORT,'Widget merchant mismatch'); END");
        $pdo->exec("CREATE TRIGGER webhook_event_source BEFORE INSERT ON merchant_webhook_events WHEN NOT EXISTS(SELECT 1 FROM payment_confirmations WHERE id=NEW.confirmation_id AND merchant_id=NEW.merchant_id) BEGIN SELECT RAISE(ABORT,'Webhook source mismatch'); END");
        $pdo->exec("CREATE TRIGGER webhook_delivery_tenant BEFORE INSERT ON merchant_webhook_deliveries WHEN NOT EXISTS(SELECT 1 FROM merchant_webhook_events v JOIN merchant_webhook_endpoints e ON e.id=NEW.endpoint_id WHERE v.id=NEW.event_id AND v.merchant_id=NEW.merchant_id AND e.merchant_id=NEW.merchant_id) BEGIN SELECT RAISE(ABORT,'Webhook tenant mismatch'); END");
        foreach (['checkout_widgets', 'merchant_webhook_events', 'merchant_webhook_receipts'] as $table) {
            $pdo->exec("CREATE TRIGGER {$table}_update BEFORE UPDATE ON $table BEGIN SELECT RAISE(ABORT,'P9 immutable history'); END");
        }
        foreach (['api_credentials', 'checkout_widgets', 'merchant_webhook_endpoints', 'merchant_webhook_events', 'merchant_webhook_deliveries', 'merchant_webhook_attempts', 'merchant_webhook_receipts'] as $table) {
            $pdo->exec("CREATE TRIGGER {$table}_delete BEFORE DELETE ON $table BEGIN SELECT RAISE(ABORT,'P9 history cannot be deleted'); END");
        }
    }
}
