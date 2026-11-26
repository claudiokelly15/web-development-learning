<?php

namespace App\Actions\Payments;

/** SQLite DDL shared by the production migration and disposable integration fixtures. */
final class NotificationSchema
{
    /** Additive migration: leave the original P10 tables and their history intact. */
    public static function completionStatements(): array
    {
        $sql = [
            "CREATE TABLE p10_preferences(sequence INTEGER PRIMARY KEY AUTOINCREMENT,merchant_id TEXT NOT NULL,actor_id INTEGER NOT NULL,mode TEXT NOT NULL CHECK(mode IN ('individual','daily','weekly','off')),occurred_at INTEGER NOT NULL)",
            "CREATE TABLE p10_payment_modes(event_id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,mode TEXT NOT NULL CHECK(mode IN ('individual','daily','weekly','off')),due_at INTEGER NOT NULL,FOREIGN KEY(event_id,merchant_id) REFERENCES p10_events(id,merchant_id))",
            "CREATE TABLE p10_batches(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,mode TEXT NOT NULL CHECK(mode IN ('daily','weekly')),due_at INTEGER NOT NULL,delivery_id TEXT NOT NULL UNIQUE,payload TEXT NOT NULL,UNIQUE(merchant_id,mode,due_at),FOREIGN KEY(delivery_id,merchant_id) REFERENCES p10_deliveries(id,merchant_id))",
            'CREATE TABLE p10_batch_members(event_id TEXT PRIMARY KEY NOT NULL,batch_id TEXT NOT NULL REFERENCES p10_batches(id),FOREIGN KEY(event_id) REFERENCES p10_events(id))',
            "CREATE TABLE p10_documents(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,kind TEXT NOT NULL CHECK(kind IN ('advice','payout','payment','settlement')),source_id TEXT NOT NULL,content BLOB NOT NULL,content_hash TEXT NOT NULL,issued_at INTEGER NOT NULL,UNIQUE(merchant_id,kind,source_id))",
            'CREATE INDEX p10_preferences_merchant ON p10_preferences(merchant_id,sequence)',
            'CREATE INDEX p10_payment_due ON p10_payment_modes(merchant_id,mode,due_at)',
        ];
        foreach (['p10_preferences', 'p10_payment_modes', 'p10_batches', 'p10_batch_members', 'p10_documents'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $sql[] = "CREATE TRIGGER {$table}_".strtolower($operation)." BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT,'P10 immutable policy/document history'); END";
            }
        }
        $sql[] = "CREATE TRIGGER p10_documents_replace BEFORE INSERT ON p10_documents WHEN EXISTS(SELECT 1 FROM p10_documents WHERE id=NEW.id OR (merchant_id=NEW.merchant_id AND kind=NEW.kind AND source_id=NEW.source_id)) BEGIN SELECT RAISE(ABORT,'Document replacement denied'); END";

        return $sql;
    }

    public static function statements(): array
    {
        $sql = [
            "CREATE TABLE p10_events(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,kind TEXT NOT NULL CHECK(kind IN ('payment.confirmed','withdrawal.requested','payout.confirmed')),source_id TEXT NOT NULL,payload TEXT NOT NULL,payload_hash TEXT NOT NULL,occurred_at INTEGER NOT NULL,recorded_at INTEGER NOT NULL CHECK(recorded_at>=occurred_at),UNIQUE(merchant_id,kind,source_id),UNIQUE(id,merchant_id))",
            "CREATE TABLE p10_deliveries(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,event_id TEXT NOT NULL,channel TEXT NOT NULL CHECK(channel IN ('email','sms')),recipient TEXT,recipient_hash TEXT,status TEXT NOT NULL CHECK(status IN ('pending','queued','delivered','failed','retry_scheduled','permanently_failed')),attempts INTEGER NOT NULL DEFAULT 0 CHECK(attempts BETWEEN 0 AND 3),next_at INTEGER NOT NULL,claim TEXT,receipt TEXT,FOREIGN KEY(event_id,merchant_id) REFERENCES p10_events(id,merchant_id),UNIQUE(event_id,channel),UNIQUE(id,merchant_id),CHECK((recipient IS NULL AND recipient_hash IS NULL) OR (recipient IS NOT NULL AND recipient_hash IS NOT NULL)))",
            'CREATE TABLE p10_history(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,delivery_id TEXT NOT NULL,attempt INTEGER NOT NULL CHECK(attempt BETWEEN 0 AND 3),status TEXT NOT NULL,reason TEXT NOT NULL,occurred_at INTEGER NOT NULL,FOREIGN KEY(delivery_id,merchant_id) REFERENCES p10_deliveries(id,merchant_id))',
            'CREATE TABLE p10_downloads(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,actor_id INTEGER NOT NULL,document_reference TEXT NOT NULL,content_hash TEXT NOT NULL,occurred_at INTEGER NOT NULL)',
            'CREATE INDEX p10_delivery_due ON p10_deliveries(merchant_id,status,next_at)',
            "CREATE TRIGGER p10_payment_email_only BEFORE INSERT ON p10_deliveries WHEN NEW.channel='sms' AND EXISTS(SELECT 1 FROM p10_events WHERE id=NEW.event_id AND kind='payment.confirmed') BEGIN SELECT RAISE(ABORT,'Payments are email only'); END",
            "CREATE TRIGGER p10_delivery_identity BEFORE UPDATE ON p10_deliveries WHEN NEW.id IS NOT OLD.id OR NEW.merchant_id IS NOT OLD.merchant_id OR NEW.event_id IS NOT OLD.event_id OR NEW.channel IS NOT OLD.channel OR NEW.recipient IS NOT OLD.recipient OR NEW.recipient_hash IS NOT OLD.recipient_hash OR NEW.attempts<OLD.attempts OR (OLD.status IN ('delivered','permanently_failed') AND NEW.status IS NOT OLD.status) BEGIN SELECT RAISE(ABORT,'Delivery identity or terminal status immutable'); END",
        ];
        foreach (['p10_events', 'p10_history', 'p10_downloads'] as $table) {
            $sql[] = "CREATE TRIGGER {$table}_update BEFORE UPDATE ON $table BEGIN SELECT RAISE(ABORT,'P10 immutable history'); END";
        }
        foreach (['p10_events', 'p10_deliveries', 'p10_history', 'p10_downloads'] as $table) {
            $sql[] = "CREATE TRIGGER {$table}_delete BEFORE DELETE ON $table BEGIN SELECT RAISE(ABORT,'P10 history retained'); END";
            $sql[] = "CREATE TRIGGER {$table}_replace BEFORE INSERT ON $table WHEN EXISTS(SELECT 1 FROM $table WHERE id=NEW.id) BEGIN SELECT RAISE(ABORT,'P10 replacement denied'); END";
        }

        return $sql;
    }
}
