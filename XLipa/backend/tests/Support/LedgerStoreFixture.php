<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use PDO;
use Throwable;

/** Additive fictional accounting on the existing explicitly owned P4 memory store only. */
final class LedgerStoreFixture
{
    public static function install(PDO $pdo): void
    {
        $databases = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing') || count($databases) !== 1 || $databases[0]['file'] !== ''
            || $pdo === DB::connection()->getPdo() || (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name IN ('payment_intents','payment_events','payment_confirmations')")->fetchColumn() !== 3
            || (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE name LIKE 'ledger_%'")->fetchColumn() !== 0) {
            throw new LogicException('Ledger schema requires the distinct owned P4 memory fixture.');
        }
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA recursive_triggers=ON');
        $pdo->beginTransaction();
        try {
            foreach (self::objects() as $sql) {
                $pdo->exec($sql);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public static function objects(): array
    {
        $amount = "INTEGER NOT NULL CHECK(typeof(amount_tzs)='integer' AND amount_tzs BETWEEN 1 AND 1000000000000)";
        $timestamp = "INTEGER NOT NULL CHECK(typeof(posted_at)='integer' AND posted_at BETWEEN 0 AND 253402214399999)";
        $objects = [
            "CREATE TABLE ledger_accounts(id TEXT PRIMARY KEY NOT NULL,merchant_id TEXT NOT NULL,kind TEXT NOT NULL,account_type TEXT NOT NULL,currency TEXT NOT NULL CHECK(currency='TZS'),created_at INTEGER NOT NULL CHECK(typeof(created_at)='integer' AND created_at>=0),
                CHECK((kind='simulated_clearing_receivable' AND account_type='asset') OR (kind='merchant_pending_obligation' AND account_type='liability')),
                UNIQUE(merchant_id,kind,currency),UNIQUE(id,merchant_id,currency,kind))",
            "CREATE TABLE ledger_journals(id TEXT PRIMARY KEY NOT NULL,consumer TEXT NOT NULL CHECK(consumer='mock-payment-accounting-v1'),confirmation_id TEXT NOT NULL REFERENCES payment_confirmations(id),payment_reference TEXT NOT NULL REFERENCES payment_intents(reference),merchant_id TEXT NOT NULL,
                amount_tzs $amount,currency TEXT NOT NULL CHECK(currency='TZS'),source_hash TEXT NOT NULL CHECK(length(source_hash)=64 AND source_hash NOT GLOB '*[^0-9a-f]*'),posted_at $timestamp,
                UNIQUE(consumer,confirmation_id),UNIQUE(payment_reference),UNIQUE(id,merchant_id,currency),
                FOREIGN KEY(id) REFERENCES ledger_seals(journal_id) DEFERRABLE INITIALLY DEFERRED)",
            "CREATE TABLE ledger_postings(journal_id TEXT NOT NULL,line INTEGER NOT NULL CHECK(line IN (1,2)),account_id TEXT NOT NULL,merchant_id TEXT NOT NULL,currency TEXT NOT NULL CHECK(currency='TZS'),kind TEXT NOT NULL,direction TEXT NOT NULL CHECK(direction IN ('debit','credit')),amount_tzs $amount,
                CHECK((line=1 AND direction='debit' AND kind='simulated_clearing_receivable') OR (line=2 AND direction='credit' AND kind='merchant_pending_obligation')),
                PRIMARY KEY(journal_id,line),UNIQUE(journal_id,direction),
                FOREIGN KEY(journal_id,merchant_id,currency) REFERENCES ledger_journals(id,merchant_id,currency),
                FOREIGN KEY(account_id,merchant_id,currency,kind) REFERENCES ledger_accounts(id,merchant_id,currency,kind))",
            'CREATE TABLE ledger_seals(journal_id TEXT PRIMARY KEY NOT NULL REFERENCES ledger_journals(id))',
            "CREATE TRIGGER ledger_source BEFORE INSERT ON ledger_journals WHEN NOT EXISTS(
                SELECT 1 FROM payment_confirmations c JOIN payment_intents p ON p.reference=c.payment_reference JOIN payment_events e ON e.id=c.event_id
                WHERE c.id=NEW.confirmation_id AND c.payment_reference=NEW.payment_reference AND c.merchant_id=NEW.merchant_id AND c.amount_tzs=NEW.amount_tzs AND c.currency=NEW.currency
                AND c.confirmed_at<=NEW.posted_at AND c.confirmed_at=e.received_at AND p.merchant_id=c.merchant_id AND p.amount_tzs=c.amount_tzs AND p.currency=c.currency
                AND p.provider='fictional_mock' AND p.dispatch_state='attempted' AND p.status='confirmed' AND e.provider=p.provider AND e.payment_reference=p.reference
                AND e.outcome='applied' AND e.normalized_status='confirmed' AND e.sequence<=p.last_sequence AND e.occurred_at>=p.created_at)
                BEGIN SELECT RAISE(ABORT,'Authoritative fictional confirmation required'); END",
            "CREATE TRIGGER ledger_posting_open BEFORE INSERT ON ledger_postings WHEN EXISTS(SELECT 1 FROM ledger_seals WHERE journal_id=NEW.journal_id)
                BEGIN SELECT RAISE(ABORT,'Posted journal is sealed'); END",
            "CREATE TRIGGER ledger_balanced BEFORE INSERT ON ledger_seals WHEN NOT EXISTS(SELECT 1 FROM ledger_journals j WHERE j.id=NEW.journal_id
                AND (SELECT count(*) FROM ledger_postings WHERE journal_id=j.id)=2
                AND (SELECT sum(amount_tzs) FROM ledger_postings WHERE journal_id=j.id AND direction='debit')=j.amount_tzs
                AND (SELECT sum(amount_tzs) FROM ledger_postings WHERE journal_id=j.id AND direction='credit')=j.amount_tzs)
                BEGIN SELECT RAISE(ABORT,'Complete balanced journal required'); END",
        ];
        foreach (['ledger_accounts', 'ledger_journals', 'ledger_postings', 'ledger_seals'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $objects[] = "CREATE TRIGGER {$table}_".strtolower($operation)." BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT,'Ledger history is immutable'); END";
            }
            $condition = match ($table) {
                'ledger_accounts' => 'id=NEW.id OR (merchant_id=NEW.merchant_id AND kind=NEW.kind AND currency=NEW.currency)',
                'ledger_journals' => 'id=NEW.id OR confirmation_id=NEW.confirmation_id OR payment_reference=NEW.payment_reference',
                'ledger_postings' => 'journal_id=NEW.journal_id AND (line=NEW.line OR direction=NEW.direction)',
                'ledger_seals' => 'journal_id=NEW.journal_id',
            };
            $objects[] = "CREATE TRIGGER {$table}_replace BEFORE INSERT ON $table WHEN EXISTS(SELECT 1 FROM $table WHERE $condition) BEGIN SELECT RAISE(ABORT,'Ledger replacement denied'); END";
        }

        return $objects;
    }
}
