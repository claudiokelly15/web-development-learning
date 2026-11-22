<?php

namespace App\Actions\Payments;

/** SQLite DDL shared by the production migration and disposable integration fixtures. */
final class NotificationSchema
{
    public static function statements(): array
    {
        $sql = [
            "CREATE TABLE p10_events(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,kind TEXT NOT NULL CHECK(kind IN ('payment.confirmed','withdrawal.requested','payout.confirmed')),source_id TEXT NOT NULL,payload TEXT NOT NULL,payload_hash TEXT NOT NULL,occurred_at INTEGER NOT NULL,recorded_at INTEGER NOT NULL CHECK(recorded_at>=occurred_at),UNIQUE(merchant_id,kind,source_id),UNIQUE(id,merchant_id))",
            "CREATE TABLE p10_deliveries(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,event_id TEXT NOT NULL,channel TEXT NOT NULL CHECK(channel IN ('email','sms')),recipient TEXT,recipient_hash TEXT,status TEXT NOT NULL CHECK(status IN ('pending','queued','delivered','failed','retry_scheduled','permanently_failed')),attempts INTEGER NOT NULL DEFAULT 0 CHECK(attempts BETWEEN 0 AND 3),next_at INTEGER NOT NULL,claim TEXT,receipt TEXT,FOREIGN KEY(event_id,merchant_id) REFERENCES p10_events(id,merchant_id),UNIQUE(event_id,channel),UNIQUE(id,merchant_id),CHECK((recipient IS NULL AND recipient_hash IS NULL) OR (recipient IS NOT NULL AND recipient_hash IS NOT NULL)))",
            "CREATE TABLE p10_history(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,delivery_id TEXT NOT NULL,attempt INTEGER NOT NULL CHECK(attempt BETWEEN 0 AND 3),status TEXT NOT NULL,reason TEXT NOT NULL,occurred_at INTEGER NOT NULL,FOREIGN KEY(delivery_id,merchant_id) REFERENCES p10_deliveries(id,merchant_id))",
            "CREATE TABLE p10_downloads(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,actor_id INTEGER NOT NULL,document_reference TEXT NOT NULL,content_hash TEXT NOT NULL,occurred_at INTEGER NOT NULL)",
            'CREATE INDEX p10_delivery_due ON p10_deliveries(merchant_id,status,next_at)',
            "CREATE TRIGGER p10_payment_email_only BEFORE INSERT ON p10_deliveries WHEN NEW.channel='sms' AND EXISTS(SELECT 1 FROM p10_events WHERE id=NEW.event_id AND kind='payment.confirmed') BEGIN SELECT RAISE(ABORT,'Payments are email only'); END",
            "CREATE TRIGGER p10_delivery_identity BEFORE UPDATE ON p10_deliveries WHEN NEW.id IS NOT OLD.id OR NEW.merchant_id IS NOT OLD.merchant_id OR NEW.event_id IS NOT OLD.event_id OR NEW.channel IS NOT OLD.channel OR NEW.recipient IS NOT OLD.recipient OR NEW.recipient_hash IS NOT OLD.recipient_hash OR NEW.attempts<OLD.attempts OR (OLD.status IN ('delivered','permanently_failed') AND NEW.status IS NOT OLD.status) BEGIN SELECT RAISE(ABORT,'Delivery identity or terminal status immutable'); END",
        ];
        foreach (['p10_events','p10_history','p10_downloads'] as $table) {
            $sql[] = "CREATE TRIGGER {$table}_update BEFORE UPDATE ON $table BEGIN SELECT RAISE(ABORT,'P10 immutable history'); END";
        }
        foreach (['p10_events','p10_deliveries','p10_history','p10_downloads'] as $table) {
            $sql[] = "CREATE TRIGGER {$table}_delete BEFORE DELETE ON $table BEGIN SELECT RAISE(ABORT,'P10 history retained'); END";
            $sql[] = "CREATE TRIGGER {$table}_replace BEFORE INSERT ON $table WHEN EXISTS(SELECT 1 FROM $table WHERE id=NEW.id) BEGIN SELECT RAISE(ABORT,'P10 replacement denied'); END";
        }

        return $sql;
    }
}
