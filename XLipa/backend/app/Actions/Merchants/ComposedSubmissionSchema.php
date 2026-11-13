<?php

namespace App\Actions\Merchants;

use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/** Exact b32c+se1+bnc1 capability. Existing runtime consumers are intentionally unmodified. */
final class ComposedSubmissionSchema
{
    public static function manifestPath(): string
    {
        return database_path('activation/bnc1/manifest.json');
    }

    public static function basePins(): array
    {
        return [
            'app/Actions/Merchants/SubmissionEvidenceSchema.php' => '1fde336da8709ea7d052fe4fdb41262e47cea4aad5cd505e32d26c0ee769ee61',
            'database/activation/b32c/2026_11_07_200000_activate_b32_security_contract.php' => 'be27fdee16c972244688bac7a1eac3b46c0e7a11bcb5a9189bc460b59e337e97',
            'database/activation/b32c/security-contract.json' => '09d12df3d292445f89b81d9200370a65ae4a74caba05b086367b35dbdb2e44a0',
            'database/activation/b32c/selection-manifest.json' => '20fbe5628164853b6a023ff98feaa1ae8a8a0ce9bf9572b64df9e361e34f2873',
            'database/activation/se1/2026_11_08_210000_add_se1_submission_evidence.php' => '75cbd497fa5f5b219087d2777c376f3e0234fb6007be10c48da9e0666dd10431',
            'database/activation/se1/manifest.json' => 'a44be8d2c4ff2cc228b25a55fd4c22ffb90912aebb393251c14a408cf5be7034',
        ];
    }

    public static function ledger(bool $composed = true): array
    {
        $foundation = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true, 64, JSON_THROW_ON_ERROR);
        $entries = [...$foundation['foundation_ledger'],
            ['migration' => '2026_11_07_200000_activate_b32_security_contract', 'batch' => 3],
            ['migration' => SubmissionEvidenceSchema::MIGRATION, 'batch' => 4],
        ];
        if ($composed) {
            $entries[] = ['migration' => BusinessNameCompletionSchema::MIGRATION, 'batch' => 5];
        }

        return array_map(fn (array $row, int $index): array => ['id' => $index + 1, ...$row], $entries, array_keys($entries));
    }

    /** Release preparation only. Runtime compares this shape with the reviewed manifest. */
    public static function releaseDefinition(): array
    {
        $sources = [];
        foreach (['app/Actions/Merchants/BusinessNameCompletionSchema.php', 'app/Actions/Merchants/ComposedSubmissionSchema.php', 'database/activation/bnc1/'.BusinessNameCompletionSchema::MIGRATION.'.php'] as $path) {
            $sources[$path] = hash_file('sha256', base_path($path));
        }
        $objects = [];
        foreach (BusinessNameCompletionSchema::objects() as $name => $sql) {
            preg_match('/^CREATE (?:UNIQUE )?(TABLE|INDEX|TRIGGER) /', $sql, $matches);
            $type = strtolower($matches[1]);
            preg_match('/\bON (\w+)/', $sql, $parent);
            $objects[] = ['type' => $type, 'name' => $name, 'table' => $type === 'table' ? $name : $parent[1], 'sql_sha256' => hash('sha256', $sql)];
        }
        usort($objects, fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return ['version' => 'BNC-1.0', 'record_contract' => 'business-name-completion-v1', 'profile' => 'b32c+se1+bnc1', 'mode' => 'isolated-only', 'migration' => BusinessNameCompletionSchema::MIGRATION,
            'base_pins' => self::basePins(), 'source_pins' => $sources, 'objects' => $objects, 'ledger' => self::ledger(),
            'connection_requirements' => ['driver' => 'sqlite', 'foreign_keys' => 1, 'recursive_triggers' => 1],
        ];
    }

    public static function assertSources(): void
    {
        $raw = file_get_contents(self::manifestPath());
        $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        $canonical = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        if ($raw !== $canonical || $manifest !== self::releaseDefinition()) {
            throw new LogicException('BNC1 manifest/source mismatch.');
        }
        foreach (self::basePins() as $path => $hash) {
            if (! hash_equals($hash, hash_file('sha256', base_path($path)))) {
                throw new LogicException('BNC1 frozen source mismatch.');
            }
        }
        $foundation = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true, 64, JSON_THROW_ON_ERROR);
        foreach ($foundation['foundation'] as $name => $hash) {
            if (! hash_equals($hash, hash_file('sha256', database_path('migrations/'.$name.'.php')))) {
                throw new LogicException('BNC1 foundation source mismatch.');
            }
        }
        SubmissionEvidenceSchema::assertSources();
    }

    public static function assertBaseLedger(): void
    {
        if (array_map(fn (object $row): array => (array) $row, DB::select('SELECT id,migration,batch FROM migrations ORDER BY id')) !== self::ledger(false)) {
            throw new LogicException('BNC1 isolated base ledger mismatch.');
        }
    }

    public static function assertReady(): void
    {
        if (DB::getDriverName() !== 'sqlite' || (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1 || (int) DB::selectOne('PRAGMA recursive_triggers')->recursive_triggers !== 1 || (int) DB::selectOne('PRAGMA ignore_check_constraints')->ignore_check_constraints !== 0 || (int) DB::selectOne('PRAGMA writable_schema')->writable_schema !== 0) {
            throw new LogicException('BNC1 enforcement unavailable.');
        }
        self::assertSources();
        if (array_column(DB::select('PRAGMA database_list'), 'name') !== ['main'] && array_column(DB::select('PRAGMA database_list'), 'name') !== ['main', 'temp']) {
            throw new LogicException('BNC1 attached database denied.');
        }
        if (DB::select('SELECT 1 FROM sqlite_temp_master') !== []) {
            throw new LogicException('BNC1 temporary objects denied.');
        }
        SubmissionEvidenceSchema::assertFrozen();
        $foundation = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true, 64, JSON_THROW_ON_ERROR);
        $expected = [];
        foreach ($foundation['foundation_objects'] as $key => $hash) {
            [$type, $name] = explode(':', $key, 2);
            if ($name === 'audit_logs') {
                continue;
            }
            $row = DB::selectOne('SELECT type,tbl_name,sql FROM sqlite_master WHERE name=?', [$name]);
            $sql = $row?->sql ?? '';
            if (in_array($name, ['users', 'merchant_memberships'], true)) {
                $column = $name === 'users' ? 'email_binding_version' : 'authority_version';
                $addition = ", $column INTEGER NOT NULL DEFAULT 1 CHECK(typeof($column)='integer' AND $column>=1)";
                if (substr_count($sql, $addition) !== 1) {
                    throw new LogicException('BNC1 SE column mismatch.');
                }
                $sql = str_replace($addition, '', $sql);
            }
            if ($row?->type !== $type || ! hash_equals($hash, hash('sha256', $sql))) {
                throw new LogicException('BNC1 foundation definition mismatch.');
            }
            $expected[] = $name;
        }
        $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
        $objects = $contract['create_tables'] + $contract['indexes'] + $contract['triggers'] + ['audit_logs' => $contract['audit']['sql']] + SubmissionEvidenceSchema::objects() + BusinessNameCompletionSchema::objects();
        foreach ($objects as $name => $sql) {
            $row = DB::selectOne('SELECT sql FROM sqlite_master WHERE name=?', [$name]);
            if ($row?->sql !== $sql) {
                throw new LogicException('BNC1 exact object mismatch.');
            }
            $expected[] = $name;
        }
        $expected = array_values(array_unique($expected));
        sort($expected);
        if ($expected !== array_column(DB::select("SELECT name FROM sqlite_master WHERE sql IS NOT NULL AND name!='sqlite_sequence' ORDER BY name"), 'name') || DB::selectOne("SELECT sql FROM sqlite_master WHERE name='sqlite_sequence'")?->sql !== 'CREATE TABLE sqlite_sequence(name,seq)') {
            throw new LogicException('BNC1 unknown or partial catalogue.');
        }
        foreach (self::releaseDefinition()['objects'] as $object) {
            $row = DB::selectOne('SELECT type,tbl_name FROM sqlite_master WHERE name=?', [$object['name']]);
            if ($row?->type !== $object['type'] || $row?->tbl_name !== $object['table']) {
                throw new LogicException('BNC1 object ownership mismatch.');
            }
        }
        if (array_map(fn (object $row): array => (array) $row, DB::select('SELECT id,migration,batch FROM migrations ORDER BY id')) !== self::ledger()) {
            throw new LogicException('BNC1 exact ledger mismatch.');
        }
        if (DB::select('PRAGMA foreign_key_check') !== []) {
            throw new LogicException('BNC1 incomplete foreign keys.');
        }
        foreach ([BusinessNameCompletionSchema::TABLE => 5, BusinessNameCompletionSchema::WITNESSES => 1] as $table => $count) {
            if (count(array_unique(array_column(DB::select("PRAGMA foreign_key_list('$table')"), 'id'))) !== $count) {
                throw new LogicException('BNC1 foreign-key catalogue mismatch.');
            }
            $primary = array_values(array_filter(DB::select("PRAGMA index_list('$table')"), fn (object $index): bool => $index->origin === 'pk'));
            $key = $table === BusinessNameCompletionSchema::TABLE ? 'id' : 'completion_id';
            $index = count($primary) === 1 ? DB::select("PRAGMA index_xinfo('".$primary[0]->name."')") : [];
            if (count($index) !== 2 || $index[0]->name !== $key || $index[0]->key !== 1 || $index[0]->coll !== 'BINARY' || $index[1]->cid !== -1) {
                throw new LogicException('BNC1 primary-key index mismatch.');
            }
        }
    }

    public static function ready(): bool
    {
        try {
            self::assertReady();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
