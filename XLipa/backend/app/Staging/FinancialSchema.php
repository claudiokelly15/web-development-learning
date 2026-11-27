<?php

namespace App\Staging;

use App\Actions\Payments\NotificationSchema;
use App\Actions\Payments\PaymentOutcomeUnknown;
use Closure;
use PDO;
use Throwable;

/** Versioned exact DDL; no fixture imports, connection discovery or business admission. */
final class FinancialSchema
{
    public const VERSION = 'xlipa-financial-schema-v1';

    private const DEFINITION_HASH = 'b6a3de0d8e494c4f77f7c5b0bd9090fae430c08d6b065b11591f3f575ec88d2e';

    public static function definition(): array
    {
        $definition = json_decode(file_get_contents(__DIR__.'/financial-schema-v1.json'), true, 64, JSON_THROW_ON_ERROR);
        StagingIdentity::check(hash_equals(self::DEFINITION_HASH, hash('sha256', StagingIdentity::encode($definition))));
        StagingIdentity::check($definition['version'] === self::VERSION);

        return $definition;
    }

    public static function steps(): array
    {
        return [...self::definition()['steps'], 'p10' => NotificationSchema::statements(), 'p10-completion' => NotificationSchema::completionStatements()];
    }

    public static function source(): string
    {
        return hash('sha256', StagingIdentity::encode(self::steps()));
    }

    private static function metadata(): array
    {
        $sql = [
            "CREATE TABLE financial_schema_profile(singleton INTEGER PRIMARY KEY CHECK(singleton=1),version TEXT NOT NULL,source_hash TEXT NOT NULL,runtime TEXT NOT NULL,generation TEXT NOT NULL,store_id TEXT NOT NULL,release TEXT NOT NULL,status TEXT NOT NULL CHECK(status='ready'))",
            'CREATE TABLE financial_provisioning_ledger(sequence INTEGER PRIMARY KEY,stage TEXT NOT NULL UNIQUE,source_hash TEXT NOT NULL,catalogue_hash TEXT NOT NULL)',
        ];
        foreach (['financial_schema_profile', 'financial_provisioning_ledger', 'staging_identity'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $sql[] = "CREATE TRIGGER {$table}_".strtolower($operation)." BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT,'Provisioning identity is immutable'); END";
            }
            $sql[] = "CREATE TRIGGER {$table}_replace BEFORE INSERT ON $table WHEN EXISTS(SELECT 1 FROM $table) BEGIN SELECT RAISE(ABORT,'Provisioning replacement denied'); END";
        }
        // A ledger has multiple append-only rows, each with a unique stage and ordinal.
        $sql = array_values(array_filter($sql, fn (string $statement): bool => ! str_starts_with($statement, 'CREATE TRIGGER financial_provisioning_ledger_replace ')));

        return $sql;
    }

    public static function catalogue(PDO $pdo): array
    {
        return $pdo->query('SELECT type,name,tbl_name,sql FROM sqlite_master ORDER BY type,name')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The existing marker is the sole permitted starting schema. */
    public static function blank(PDO $pdo, array $context): void
    {
        StagingIdentity::check(self::catalogue($pdo) === [['type' => 'table', 'name' => 'staging_identity', 'tbl_name' => 'staging_identity', 'sql' => 'CREATE TABLE staging_identity(runtime TEXT NOT NULL,generation TEXT NOT NULL,profile TEXT NOT NULL)']]);
        StagingIdentity::check($pdo->query('SELECT runtime,generation,profile FROM staging_identity')->fetchAll(PDO::FETCH_ASSOC) === [[
            'runtime' => $context['runtime'], 'generation' => $context['generation'], 'profile' => 'unprovisioned-r22',
        ]]);
    }

    private static function execute(PDO $pdo, array $context, ?Closure $checkpoint = null): array
    {
        $pdo->prepare('UPDATE staging_identity SET profile=?')->execute([self::VERSION]);
        foreach (self::metadata() as $sql) {
            $pdo->exec($sql);
        }
        $ledger = [];
        foreach (self::steps() as $stage => $statements) {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            $entry = ['sequence' => count($ledger) + 1, 'stage' => $stage, 'source_hash' => hash('sha256', StagingIdentity::encode($statements)), 'catalogue_hash' => hash('sha256', StagingIdentity::encode(self::catalogue($pdo)))];
            $pdo->prepare('INSERT INTO financial_provisioning_ledger VALUES(?,?,?,?)')->execute(array_values($entry));
            $ledger[] = $entry;
            if ($checkpoint) {
                $checkpoint($stage);
            }
        }
        $pdo->prepare('INSERT INTO financial_schema_profile VALUES(1,?,?,?,?,?,?,?)')->execute([
            self::VERSION, self::source(), $context['runtime'], $context['generation'], $context['store'], $context['release'], 'ready',
        ]);

        return $ledger;
    }

    private static function expected(array $context): array
    {
        static $template;
        if ($template === null) {
            $memory = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $memory->exec('CREATE TABLE staging_identity(runtime TEXT NOT NULL,generation TEXT NOT NULL,profile TEXT NOT NULL)');
            $memory->prepare('INSERT INTO staging_identity VALUES(?,?,?)')->execute([$context['runtime'], $context['generation'], 'unprovisioned-r22']);
            $ledger = self::execute($memory, $context);
            $template = ['catalogue' => self::catalogue($memory), 'ledger' => $ledger];
        }

        return $template;
    }

    public static function constraints(PDO $pdo): void
    {
        StagingIdentity::check((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1);
        StagingIdentity::check((int) $pdo->query('PRAGMA recursive_triggers')->fetchColumn() === 1);
        StagingIdentity::check((int) $pdo->query('PRAGMA ignore_check_constraints')->fetchColumn() === 0);
        StagingIdentity::check((int) $pdo->query('PRAGMA writable_schema')->fetchColumn() === 0);
        $databases = array_column($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC), 'name');
        StagingIdentity::check(in_array($databases, [['main'], ['main', 'temp']], true));
        StagingIdentity::check($pdo->query('SELECT 1 FROM sqlite_temp_master')->fetchAll() === []);
    }

    /** Caller supplies a verified owned PDO; the custodian is the only file-target selector. */
    public static function create(PDO $pdo, array $context, ?Closure $checkpoint = null): void
    {
        self::constraints($pdo);
        StagingIdentity::check(! $pdo->inTransaction());
        $pdo->exec('BEGIN IMMEDIATE');
        $committing = false;
        try {
            self::blank($pdo, $context);
            self::execute($pdo, $context, $checkpoint);
            self::assertReady($pdo, $context);
            $committing = true;
            $pdo->exec('COMMIT');
        } catch (Throwable $error) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable) {
                $committing = true;
            }
            if ($committing) {
                throw new PaymentOutcomeUnknown;
            }
            throw $error;
        }
    }

    /** Exact catalogue includes autoindexes, definitions, checks/FKs and trigger bodies. */
    public static function assertReady(PDO $pdo, array $context): array
    {
        self::constraints($pdo);
        $expected = self::expected($context);
        StagingIdentity::check(self::catalogue($pdo) === $expected['catalogue']);
        StagingIdentity::check($pdo->query('SELECT * FROM financial_schema_profile')->fetchAll(PDO::FETCH_ASSOC) === [[
            'singleton' => 1, 'version' => self::VERSION, 'source_hash' => self::source(), 'runtime' => $context['runtime'],
            'generation' => $context['generation'], 'store_id' => $context['store'], 'release' => $context['release'], 'status' => 'ready',
        ]]);
        StagingIdentity::check($pdo->query('SELECT * FROM financial_provisioning_ledger ORDER BY sequence')->fetchAll(PDO::FETCH_ASSOC) === $expected['ledger']);
        StagingIdentity::check($pdo->query('SELECT runtime,generation,profile FROM staging_identity')->fetchAll(PDO::FETCH_ASSOC) === [[
            'runtime' => $context['runtime'], 'generation' => $context['generation'], 'profile' => self::VERSION,
        ]]);
        StagingIdentity::check($pdo->query('PRAGMA foreign_key_check')->fetchAll() === [] && $pdo->query('PRAGMA quick_check')->fetchColumn() === 'ok');
        $counts = array_count_values(array_column($expected['catalogue'], 'type'));

        return ['profile' => self::VERSION, 'source' => self::source(), 'status' => 'ready', 'objects' => $counts, 'steps' => array_keys(self::steps())];
    }
}
