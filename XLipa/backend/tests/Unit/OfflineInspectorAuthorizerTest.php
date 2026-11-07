<?php

namespace Tests\Unit;

use Error;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SQLite3;
use SQLite3Exception;
use Tests\Support\OfflineInspectorAuthorizer;

class OfflineInspectorAuthorizerTest extends TestCase
{
    private string $root;

    private string $path;

    private SQLite3 $db;

    private OfflineInspectorAuthorizer $policy;

    private string $baseline;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('APP_ENV') !== 'testing' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1') {
            throw new LogicException('Synthetic bootstrap required');
        }
        $this->root = sys_get_temp_dir().'/xlipa-authorizer-'.bin2hex(random_bytes(10));
        mkdir($this->root, 0700);
        $this->path = $this->root.'/fixture.sqlite';
        $builder = new SQLite3($this->path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $builder->enableExceptions(true);
        $builder->exec('PRAGMA foreign_keys=ON');
        $builder->exec('CREATE TABLE parents(id INTEGER PRIMARY KEY)');
        $builder->exec('CREATE TABLE migrations(id INTEGER PRIMARY KEY, migration TEXT, batch INTEGER, parent_id INTEGER REFERENCES parents(id))');
        $builder->exec('CREATE INDEX ledger_batch ON migrations(batch)');
        $builder->exec("INSERT INTO parents VALUES(1); INSERT INTO migrations VALUES(1,'synthetic_foundation',1,1)");
        $builder->close();
        $this->baseline = hash_file('sha256', $this->path);
        $this->db = new SQLite3($this->path, SQLITE3_OPEN_READONLY);
        $this->db->enableExceptions(true);
        $this->db->exec('PRAGMA query_only=ON');
        $this->db->exec('PRAGMA temp_store=MEMORY');
        $this->db->exec('PRAGMA foreign_keys=ON');
        $this->policy = new OfflineInspectorAuthorizer;
        self::assertTrue($this->db->setAuthorizer($this->policy));
    }

    protected function tearDown(): void
    {
        $this->db->close();
        self::assertSame($this->baseline, hash_file('sha256', $this->path));
        self::assertSame(['fixture.sqlite'], array_values(array_diff(scandir($this->root), ['.', '..'])));
        unlink($this->path);
        rmdir($this->root);
        parent::tearDown();
    }

    public function test_original_callback_reproduces_auth_and_instrumentation_identifies_constant_error(): void
    {
        self::assertFalse(defined('SQLITE3_SELECT'));
        $trace = [];
        $this->db->setAuthorizer(static function ($action, $a, $b, $c, $d) use (&$trace): int {
            $trace[] = ['action' => $action, 'first' => $a, 'second' => $b, 'database' => $c, 'source' => $d];
            try {
                // Exact failing expression from the FRR callback; do not fix this regression control.
                return in_array($action, [SQLITE3_SELECT, SQLITE3_READ, SQLITE3_FUNCTION, SQLITE3_TRANSACTION], true) ? SQLITE3_OK : SQLITE3_DENY;
            } catch (Error $e) {
                $trace[] = ['error' => $e->getMessage()];

                return SQLite3::DENY;
            }
        });
        try {
            $this->db->exec('BEGIN');
            self::fail('Legacy callback unexpectedly allowed BEGIN');
        } catch (SQLite3Exception $e) {
            self::assertSame(23, $e->getCode());
        }
        self::assertSame(['action' => 22, 'first' => 'BEGIN', 'second' => null, 'database' => null, 'source' => null], $trace[0]);
        self::assertSame('Undefined constant "Tests\\Unit\\SQLITE3_SELECT"', $trace[1]['error']);
        self::assertSame(22, SQLite3::TRANSACTION);
    }

    public function test_uninstrumented_original_callback_fails_at_begin(): void
    {
        $this->db->setAuthorizer(static function ($action, $a, $b, $c, $d): int {
            return in_array($action, [SQLITE3_SELECT, SQLITE3_READ, SQLITE3_FUNCTION, SQLITE3_TRANSACTION], true) ? SQLITE3_OK : SQLITE3_DENY;
        });
        try {
            $this->db->exec('BEGIN');
            self::fail('Original callback unexpectedly succeeded');
        } catch (SQLite3Exception $e) {
            self::assertSame(23, $e->getCode());
        }
    }

    public function test_class_constant_only_correction_allows_begin_without_new_action_permission(): void
    {
        $events = [];
        $this->db->setAuthorizer(static function ($action, $a, $b, $c, $d) use (&$events): int {
            $events[] = $action;

            return in_array($action, [SQLite3::SELECT, SQLite3::READ, SQLite3::FUNCTION, SQLite3::TRANSACTION], true) ? SQLite3::OK : SQLite3::DENY;
        });
        $this->db->exec('BEGIN');
        self::assertSame('synthetic_foundation', $this->db->querySingle('SELECT migration FROM migrations'));
        $this->db->exec('ROLLBACK');
        self::assertSame(SQLite3::TRANSACTION, $events[0]);
    }

    public function test_corrected_policy_completes_original_inventory_categories(): void
    {
        $this->db->exec('BEGIN');
        $queries = [
            'identity' => 'PRAGMA database_list',
            'version' => 'SELECT sqlite_version()',
            'schema' => "SELECT type,name,tbl_name,sql FROM sqlite_schema WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name",
            'ledger' => 'SELECT migration,batch FROM migrations ORDER BY id',
            'columns' => 'PRAGMA table_info(migrations)',
            'indexes' => 'PRAGMA index_list(migrations)',
            'index_columns' => 'PRAGMA index_info(ledger_batch)',
            'foreign_keys' => 'PRAGMA foreign_key_list(migrations)',
            'count' => 'SELECT COUNT(*) FROM migrations',
            'integrity' => 'PRAGMA integrity_check',
            'fk_check' => 'PRAGMA foreign_key_check',
        ];
        foreach ($queries as $category => $sql) {
            $this->policy->category = $category;
            try {
                $result = $this->db->query($sql);
            } catch (SQLite3Exception $e) {
                self::fail(json_encode(['category' => $category, 'events' => $this->policy->events], JSON_THROW_ON_ERROR));
            }
            $rows = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
            $result->finalize();
            if ($category === 'integrity') {
                self::assertSame([['integrity_check' => 'ok']], $rows);
            } elseif ($category === 'fk_check') {
                self::assertSame([], $rows);
            } else {
                self::assertNotEmpty($rows, $category);
            }
        }
        foreach (['query_only' => 1, 'foreign_keys' => 1, 'temp_store' => 2, 'journal_mode' => 'delete'] as $setting => $value) {
            self::assertSame($value, $this->db->querySingle('PRAGMA '.$setting));
        }
        foreach (['data_version', 'synchronous', 'page_size', 'encoding', 'schema_version', 'user_version', 'busy_timeout', 'compile_options'] as $setting) {
            $r = $this->db->query('PRAGMA '.$setting);
            self::assertNotFalse($r->fetchArray(SQLITE3_ASSOC));
            $r->finalize();
        }
        $this->db->exec('ROLLBACK');
        self::assertNotContains(false, array_column($this->policy->events, 'allowed'));
    }

    public static function forbiddenQueries(): array
    {
        return array_map(static fn (string $sql): array => [$sql], [
            'INSERT INTO parents VALUES(2)', 'UPDATE parents SET id=2', 'DELETE FROM parents',
            'CREATE TABLE forbidden(id INTEGER)', 'DROP TABLE parents', 'ALTER TABLE parents ADD COLUMN other TEXT',
            "ATTACH ':memory:' AS other", 'DETACH main', 'PRAGMA query_only=OFF', 'PRAGMA foreign_keys=OFF',
            'PRAGMA journal_mode=WAL', 'PRAGMA wal_checkpoint', 'PRAGMA writable_schema=ON',
            'PRAGMA user_version=9', 'PRAGMA synchronous=OFF', 'PRAGMA temp_store=FILE',
            "SELECT load_extension('not_a_real_library')", 'PRAGMA optimize', 'VACUUM',
        ]);
    }

    #[DataProvider('forbiddenQueries')]
    public function test_dangerous_statements_are_denied_by_authorizer(string $sql): void
    {
        $this->policy->category = 'negative';
        try {
            $r = $this->db->query($sql);
            $r->finalize();
            self::fail('Dangerous statement allowed');
        } catch (SQLite3Exception $e) {
            self::assertContains($e->getCode(), [1, 23]);
            self::assertContains(false, array_column($this->policy->events, 'allowed'));
        }
    }

    public function test_no_create_flag_refuses_missing_fixture_and_unknown_action_denies(): void
    {
        try {
            new SQLite3($this->root.'/missing.sqlite', SQLITE3_OPEN_READONLY);
            self::fail('Missing file opened');
        } catch (\Exception $e) {
            self::assertFileDoesNotExist($this->root.'/missing.sqlite');
        }
        self::assertSame(SQLite3::DENY, ($this->policy)(9999));
        self::assertSame(SQLite3::DENY, ($this->policy)(SQLite3::PRAGMA, 'query_only', 'OFF'));
    }

    public function test_callback_error_fails_closed_before_read_results(): void
    {
        $this->db->setAuthorizer(static function (): int {
            throw new LogicException('Synthetic callback failure');
        });
        try {
            $this->db->query('SELECT migration,batch FROM migrations');
            self::fail('Callback failure permitted query');
        } catch (SQLite3Exception $e) {
            self::assertSame(23, $e->getCode());
        }
    }
}
