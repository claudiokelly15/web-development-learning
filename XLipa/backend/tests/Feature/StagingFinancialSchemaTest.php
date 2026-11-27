<?php

namespace Tests\Feature;

use App\Actions\Payments\NotificationSchema;
use App\Actions\Payments\PaymentOutcomeUnknown;
use App\Actions\Payments\PaymentStore;
use App\Staging\FinancialProvisioner;
use App\Staging\FinancialSchema;
use App\Staging\StagingIdentity;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StagingFinancialSchemaTest extends TestCase
{
    private PDO $pdo;

    private string $root;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = str_replace('\\', '/', sys_get_temp_dir()).'/xlipa-r22-test-'.bin2hex(random_bytes(8));
        mkdir($this->root);
        $this->pdo = new PDO('sqlite:'.$this->root.'/financial.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON; PRAGMA busy_timeout=10');
        $this->pdo->exec('CREATE TABLE staging_identity(runtime TEXT NOT NULL,generation TEXT NOT NULL,profile TEXT NOT NULL)');
        $this->pdo->exec("INSERT INTO staging_identity VALUES('test-runtime','test-generation','unprovisioned-r22')");
        $this->context = ['runtime' => 'test-runtime', 'generation' => 'test-generation', 'store' => 'test-store', 'release' => 'test-release'];
        $this->context['root'] = $this->root;
        $this->context['namespace'] = 'xlipa-fictional-r21';
        $this->context['financial_profile'] = FinancialSchema::VERSION;
        foreach (['workflow', 'admission', 'financial'] as $kind) {
            $pdo = $kind === 'financial' ? $this->pdo : new PDO('sqlite:'.$this->root.'/'.$kind.'.sqlite');
            $pdo->exec('PRAGMA application_id=21; PRAGMA user_version=1');
            $this->context['headers'][$kind] = ['application' => 21, 'version' => 1];
            $this->context['schemas'][$kind] = StagingIdentity::schema($pdo);
        }
    }

    protected function tearDown(): void
    {
        unset($this->pdo);
        foreach (['financial.sqlite', 'financial.sqlite-journal', 'financial.sqlite-wal', 'financial.sqlite-shm', 'workflow.sqlite', 'admission.sqlite', 'financial-provision.started', 'financial-provision.lock', 'financial-provision.failed', 'interrupt.php', 'context.json', 'checkpoint'] as $file) {
            if (is_file($this->root.'/'.$file)) {
                unlink($this->root.'/'.$file);
            }
        }
        rmdir($this->root);
        parent::tearDown();
    }

    public function test_exact_composition_matches_unchanged_fixture_definitions_and_p10(): void
    {
        FinancialSchema::create($this->pdo, $this->context);
        $reference = new PDO('sqlite::memory:');
        foreach (['PaymentStoreFixture', 'LedgerStoreFixture', 'PaymentLinkStoreFixture', 'AnalyticsStoreFixture', 'LifecycleStoreFixture', 'DeveloperStoreFixture'] as $name) {
            $class = 'Tests\\Support\\'.$name;
            $class::install($reference);
        }
        foreach ([...NotificationSchema::statements(), ...NotificationSchema::completionStatements()] as $sql) {
            $reference->exec($sql);
        }
        $actual = array_values(array_filter(FinancialSchema::catalogue($this->pdo), fn ($row) => ! in_array($row['tbl_name'], ['staging_identity', 'financial_schema_profile', 'financial_provisioning_ledger'], true)));
        $this->assertSame(FinancialSchema::catalogue($reference), $actual);
        $this->assertSame(['p4', 'p5', 'p6', 'analytics', 'p8', 'p9', 'p10', 'p10-completion'], FinancialSchema::assertReady($this->pdo, $this->context)['steps']);
        foreach (FinancialSchema::definition()['sources'] as $file => $hash) {
            $this->assertSame($hash, hash_file('sha256', base_path($file)), $file.' original source retained');
        }
        $this->assertSame(0, (int) $this->pdo->query('SELECT count(*) FROM payment_intents')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT count(*) FROM ledger_journals')->fetchColumn());
        $this->assertFalse(app()->bound(PaymentStore::class));
    }

    public static function damage(): array
    {
        return [
            ['DROP TABLE api_rate_windows'],
            ['ALTER TABLE merchant_webhook_receipts DROP COLUMN received_at'],
            ['ALTER TABLE api_credentials ADD COLUMN unsupported TEXT'],
            ['DROP INDEX payment_events_payment'],
            ['DROP TRIGGER ledger_balanced'],
            ['CREATE TABLE unapproved_balance(value REAL)'],
            ['PRAGMA foreign_keys=OFF'],
            ['PRAGMA recursive_triggers=OFF'],
            ["ATTACH DATABASE ':memory:' AS unexpected"],
        ];
    }

    #[DataProvider('damage')]
    public function test_schema_drift_and_disabled_enforcement_fail_closed(string $sql): void
    {
        FinancialSchema::create($this->pdo, $this->context);
        $this->pdo->exec($sql);
        $this->expectException(\LogicException::class);
        FinancialSchema::assertReady($this->pdo, $this->context);
    }

    public static function wrongIdentity(): array
    {
        return [['runtime'], ['generation'], ['store'], ['release']];
    }

    #[DataProvider('wrongIdentity')]
    public function test_exact_profile_binds_store_lineage_and_release(string $key): void
    {
        FinancialSchema::create($this->pdo, $this->context);
        $this->expectException(\LogicException::class);
        FinancialSchema::assertReady($this->pdo, [...$this->context, $key => 'wrong']);
    }

    public static function corruptMetadata(): array
    {
        return [
            ['financial_schema_profile', "UPDATE financial_schema_profile SET version='wrong'", 'update'],
            ['financial_schema_profile', "UPDATE financial_schema_profile SET source_hash='wrong'", 'update'],
            ['financial_provisioning_ledger', 'DELETE FROM financial_provisioning_ledger WHERE sequence=1', 'delete'],
            ['financial_provisioning_ledger', "UPDATE financial_provisioning_ledger SET catalogue_hash='wrong' WHERE sequence=1", 'update'],
            ['staging_identity', "UPDATE staging_identity SET profile='partial'", 'update'],
        ];
    }

    #[DataProvider('corruptMetadata')]
    public function test_marker_and_ledger_content_cannot_declare_an_incompatible_store_ready(string $table, string $sql, string $operation): void
    {
        FinancialSchema::create($this->pdo, $this->context);
        $trigger = $table.'_'.$operation;
        $statement = $this->pdo->query("SELECT sql FROM sqlite_master WHERE name='$trigger'")->fetchColumn();
        $this->pdo->exec('DROP TRIGGER '.$trigger);
        $this->pdo->exec($sql);
        $this->pdo->exec($statement);
        $this->expectException(\LogicException::class);
        FinancialSchema::assertReady($this->pdo, $this->context);
    }

    public function test_partial_failure_rolls_back_but_cannot_rollback_another_store(): void
    {
        $workflow = new PDO('sqlite::memory:');
        $workflow->exec('CREATE TABLE independent(value TEXT)');
        try {
            FinancialSchema::create($this->pdo, $this->context, function (string $stage) use ($workflow): void {
                if ($stage === 'p6') {
                    $workflow->exec("INSERT INTO independent VALUES('committed independently')");
                    throw new \RuntimeException('fictional interrupted provisioning');
                }
            });
            $this->fail('Fault must not succeed');
        } catch (\RuntimeException) {
            FinancialSchema::blank($this->pdo, $this->context);
            $this->assertSame(1, (int) $workflow->query('SELECT count(*) FROM independent')->fetchColumn());
        }
        $this->expectException(\LogicException::class);
        FinancialSchema::assertReady($this->pdo, $this->context);
    }

    public function test_duplicate_initialization_and_competing_sqlite_writer_do_not_overwrite_schema(): void
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        $other = new PDO('sqlite:'.$this->root.'/financial.sqlite');
        $other->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON; PRAGMA busy_timeout=10');
        try {
            FinancialSchema::create($other, $this->context);
            $this->fail('Concurrent writer accepted');
        } catch (\PDOException) {
            $this->addToAssertionCount(1);
        }
        $this->pdo->exec('ROLLBACK');
        FinancialSchema::create($this->pdo, $this->context);
        $before = FinancialSchema::catalogue($this->pdo);
        try {
            FinancialSchema::create($this->pdo, $this->context);
            $this->fail('Duplicate raw initialization accepted');
        } catch (\LogicException) {
            $this->assertSame($before, FinancialSchema::catalogue($this->pdo));
            $this->assertSame('ready', FinancialSchema::assertReady($this->pdo, $this->context)['status']);
        }
    }

    public function test_uncertain_commit_is_reported_without_destructive_rollback_or_retry(): void
    {
        $pdo = new class('sqlite::memory:') extends PDO
        {
            public function exec(string $statement): int|false
            {
                $result = parent::exec($statement);
                if ($statement === 'COMMIT') {
                    throw new \RuntimeException('Lost synthetic commit acknowledgement');
                }

                return $result;
            }
        };
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON');
        $pdo->exec('CREATE TABLE staging_identity(runtime TEXT NOT NULL,generation TEXT NOT NULL,profile TEXT NOT NULL)');
        $pdo->exec("INSERT INTO staging_identity VALUES('test-runtime','test-generation','unprovisioned-r22')");
        try {
            FinancialSchema::create($pdo, $this->context);
            $this->fail('Unknown outcome was reported successful');
        } catch (PaymentOutcomeUnknown) {
            $this->assertSame('ready', FinancialSchema::assertReady($pdo, $this->context)['status']);
        }
    }

    public function test_arbitrary_target_does_not_open_or_change_a_synthetic_canary(): void
    {
        $before = hash_file('sha256', $this->root.'/financial.sqlite');
        try {
            FinancialProvisioner::provision('../'.$this->root.'/financial.sqlite');
            $this->fail('Unsafe target accepted');
        } catch (\LogicException) {
            $this->assertSame($before, hash_file('sha256', $this->root.'/financial.sqlite'));
        }
    }

    private function initialize(): array
    {
        return (new \ReflectionMethod(FinancialProvisioner::class, 'initialize'))->invoke(null, $this->context);
    }

    public function test_successful_custodian_repetition_is_read_only_and_serializes_competing_initializers(): void
    {
        $lock = fopen($this->root.'/financial-provision.lock', 'c');
        flock($lock, LOCK_EX | LOCK_NB);
        try {
            $this->initialize();
            $this->fail('Competing provisioner accepted');
        } catch (\LogicException) {
            $this->assertFileDoesNotExist($this->root.'/financial-provision.started');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->assertFalse($this->initialize()['repeated']);
        $before = hash_file('sha256', $this->root.'/financial.sqlite');
        $this->assertTrue($this->initialize()['repeated']);
        $this->assertSame($before, hash_file('sha256', $this->root.'/financial.sqlite'));
    }

    public function test_durable_interrupted_claim_cannot_be_reinitialized_or_silently_repaired(): void
    {
        $claim = array_intersect_key($this->context, array_flip(['namespace', 'runtime', 'generation', 'store', 'release', 'financial_profile']));
        file_put_contents($this->root.'/financial-provision.started', json_encode($claim, JSON_UNESCAPED_SLASHES));
        $before = hash_file('sha256', $this->root.'/financial.sqlite');
        try {
            $this->initialize();
            $this->fail('Interrupted generation was repaired');
        } catch (\LogicException) {
            $this->assertSame($before, hash_file('sha256', $this->root.'/financial.sqlite'));
            $this->assertFileExists($this->root.'/financial-provision.started');
        }
    }

    public function test_actual_process_interruption_leaves_no_committed_partial_financial_schema(): void
    {
        $script = <<<'PHP'
<?php
require $argv[1];
$context = json_decode(file_get_contents($argv[2]),true);
$pdo = new PDO('sqlite:'.$context['root'].'/financial.sqlite');
$pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON');
App\Staging\FinancialSchema::create($pdo,$context,function(string $stage) use($context):void {
    if ($stage === 'p6') {
        file_put_contents($context['root'].'/checkpoint','transaction open');
        sleep(30);
    }
});
PHP;
        file_put_contents($this->root.'/interrupt.php', $script);
        file_put_contents($this->root.'/context.json', json_encode($this->context));
        $process = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=', '-d', 'allow_url_fopen=0', $this->root.'/interrupt.php', base_path('vendor/autoload.php'), $this->root.'/context.json'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        try {
            $ready = false;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                clearstatcache();
                if (is_file($this->root.'/checkpoint')) {
                    $ready = true;
                    break;
                }
                usleep(20000);
            }
            $this->assertTrue($ready, 'Child reached the open DDL transaction');
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
        FinancialSchema::blank($this->pdo, $this->context);
        $this->expectException(\LogicException::class);
        FinancialSchema::assertReady($this->pdo, $this->context);
    }
}
