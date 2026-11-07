<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private function selected(): array
    {
        $settings = config('database.connections.'.config('database.default'), []);
        $path = $settings['database'] ?? '';
        $temporary = realpath(dirname($path)) === realpath(sys_get_temp_dir()) && preg_match('/^xlipa-b32-synthetic-[a-f0-9]+\.sqlite$/D', basename($path));
        if (config('b32c.selected') !== realpath(__FILE__) || ! app()->environment('testing') || getenv('XLIPA_SYNTHETIC_ONLY') !== '1'
            || ($settings['driver'] ?? null) !== 'sqlite' || ! empty($settings['url']) || ($path !== ':memory:' && ! $temporary)) {
            throw new LogicException('B32C requires explicit isolated rehearsal selection; working activation is disabled.');
        }
        $manifest = json_decode(file_get_contents(__DIR__.'/selection-manifest.json'), true, 32, JSON_THROW_ON_ERROR);
        if ($manifest['mode'] !== 'isolated-rehearsal-only' || $manifest['artifact'] !== basename(__FILE__)
            || ! hash_equals($manifest['artifact_sha256'], hash_file('sha256', __FILE__))
            || ! hash_equals($manifest['contract_sha256'], hash_file('sha256', __DIR__.'/security-contract.json'))) {
            throw new LogicException('B32C selection manifest mismatch.');
        }
        foreach ($manifest['foundation'] as $name => $hash) {
            if (! hash_equals($hash, hash_file('sha256', database_path('migrations/'.$name.'.php')))) {
                throw new LogicException('B32C foundation source drift.');
            }
        }

        return [$settings, $manifest, json_decode(file_get_contents(__DIR__.'/security-contract.json'), true, 64, JSON_THROW_ON_ERROR)];
    }

    private function rows(PDO $pdo, string $sql): array
    {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function quote(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    private function checkpoint(string $phase): void
    {
        // Fault injection is confined to the already guarded testing entry point.
        $hook = config('b32c.rehearsal_hook');
        if ($hook instanceof Closure) {
            $hook($phase);
        }
    }

    public function up(): void
    {
        [$settings, $manifest, $contract] = $this->selected();
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== $settings['database'] || $connection->transactionLevel() !== 0) {
            throw new LogicException('B32C connection or transaction ownership mismatch.');
        }
        $pdo = $connection->getPdo();
        if ($pdo->inTransaction() || (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
            throw new LogicException('B32C requires an idle FK-enforced connection.');
        }
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $tables = array_column($this->rows($pdo, "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
            $expected = $manifest['foundation_tables'];
            sort($expected);
            if ($tables !== $expected) {
                throw new LogicException('B32C quarantine: unexpected or partially activated schema.');
            }
            $ledger = $this->rows($pdo, 'SELECT migration,batch FROM migrations ORDER BY id');
            if ($ledger !== $manifest['foundation_ledger']) {
                throw new LogicException('B32C quarantine: migration ledger mismatch.');
            }
            $objects = $this->rows($pdo, "SELECT type,name,tbl_name,sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY type,name");
            $actual = [];
            foreach ($objects as $object) {
                $actual[$object['type'].':'.$object['name']] = hash('sha256', $object['sql']);
            }
            if ($actual !== $manifest['foundation_objects']) {
                throw new LogicException('B32C quarantine: foundation schema fingerprint mismatch.');
            }
            foreach ($tables as $table) {
                foreach ($this->rows($pdo, 'PRAGMA foreign_key_list('.$this->quote($table).')') as $fk) {
                    if ($fk['table'] === 'audit_logs') {
                        throw new LogicException('B32C unexpected inbound audit dependency.');
                    }
                }
            }
            foreach ($objects as $object) {
                if ($object['type'] === 'view' || ($object['type'] === 'trigger' && ($object['tbl_name'] === 'audit_logs' || stripos($object['sql'], 'audit_logs') !== false))) {
                    throw new LogicException('B32C unexpected audit/view dependency.');
                }
            }
            $auditSql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='audit_logs'")->fetchColumn();
            if (! hash_equals($manifest['old_audit_sql_sha256'], hash('sha256', $auditSql))) {
                throw new LogicException('B32C unreviewed audit definition.');
            }
            if ($this->rows($pdo, 'PRAGMA foreign_key_check') !== [] || $pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
                throw new LogicException('B32C foundation integrity failure.');
            }
            $before = [];
            foreach ($tables as $table) {
                $before[$table] = $this->rows($pdo, 'SELECT * FROM '.$this->quote($table).' ORDER BY rowid');
            }
            $this->checkpoint('before_audit');
            $stage = preg_replace('/^CREATE TABLE "audit_logs"/', 'CREATE TABLE "audit_logs_b32c_stage"', $contract['audit']['sql'], 1, $replaced);
            if ($replaced !== 1) {
                throw new LogicException('B32C invalid frozen audit SQL.');
            }
            $pdo->exec($stage);
            $pdo->exec('INSERT INTO audit_logs_b32c_stage SELECT * FROM audit_logs');
            $this->checkpoint('after_audit_copy');
            $pdo->exec('DROP TABLE audit_logs');
            $this->checkpoint('after_audit_drop');
            $pdo->exec('ALTER TABLE audit_logs_b32c_stage RENAME TO audit_logs');
            $this->checkpoint('after_audit_rename');
            foreach ($objects as $object) {
                if ($object['type'] === 'index' && $object['tbl_name'] === 'audit_logs') {
                    $pdo->exec($object['sql']);
                }
            }
            $this->checkpoint('after_audit_indexes');
            foreach ($contract['tables'] as $table) {
                $pdo->exec($contract['create_tables'][$table]);
                $this->checkpoint('table:'.$table);
            }
            foreach ($contract['indexes'] as $sql) {
                $pdo->exec($sql);
            }
            foreach ($contract['triggers'] as $sql) {
                $pdo->exec($sql);
            }
            $this->checkpoint('after_security_triggers');
            foreach ($tables as $table) {
                if ($before[$table] !== $this->rows($pdo, 'SELECT * FROM '.$this->quote($table).' ORDER BY rowid')) {
                    throw new LogicException('B32C historical row preservation failure.');
                }
            }
            foreach ($objects as $object) {
                if ($object['type'] === 'table' && $object['name'] === 'audit_logs') {
                    continue;
                }
                $statement = $pdo->prepare('SELECT sql FROM sqlite_master WHERE type=? AND name=?');
                $statement->execute([$object['type'], $object['name']]);
                if ($statement->fetchColumn() !== $object['sql']) {
                    throw new LogicException('B32C historical schema object changed.');
                }
            }
            foreach ($contract['tables'] as $table) {
                if ((int) $pdo->query('SELECT count(*) FROM '.$this->quote($table))->fetchColumn() !== 0) {
                    throw new LogicException('B32C requires empty new tables.');
                }
            }
            if ($this->rows($pdo, 'PRAGMA foreign_key_check') !== [] || $pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
                throw new LogicException('B32C postflight integrity failure.');
            }
            $this->checkpoint('before_commit');
            $pdo->exec('COMMIT');
        } catch (Throwable $error) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable) {
                // Preserve unknown commit outcome; never automatically rerun.
            }
            throw $error;
        }
        $this->checkpoint('after_commit_before_ledger');
    }

    public function down(): void
    {
        throw new LogicException('B32C downgrade disabled: reviewed forward recovery required.');
    }
};
