<?php

namespace Tests\Feature;

use App\Actions\Merchants\CreateMerchant;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\B32CRehearsal;
use Tests\Support\OfflinePermit;
use Tests\Support\OfflineRunRegister;
use Tests\Support\OfflineSecurityGuard;
use Tests\Support\OfflineStateClassifier;
use Tests\TestCase;

class OfflineSecurityGuardTest extends TestCase
{
    private string $secret;

    private string $public;

    private string $registerRoot;

    private string $releaseRoot;

    private string $manifest;

    private array $permit;

    private array $expected;

    private array $clockState = ['utc_ms' => 1000, 'mono_ms' => 1000, 'healthy' => true];

    private int $accesses = 0;

    private OfflineStateClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        $keys = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($keys);
        $this->public = sodium_crypto_sign_publickey($keys);
        sodium_memzero($keys);
        $this->registerRoot = sys_get_temp_dir().'/xlipa-offline-guard-'.bin2hex(random_bytes(8));
        $this->releaseRoot = sys_get_temp_dir().'/xlipa-offline-release-'.bin2hex(random_bytes(8));
        mkdir($this->registerRoot, 0700);
        OfflineRunRegister::initializeFixture($this->registerRoot);
        mkdir($this->releaseRoot, 0700);
        mkdir($this->releaseRoot.'/activation', 0700);
        $artifact = 'activation/2026_11_07_200000_activate_b32_security_contract.php';
        $contract = 'activation/security-contract.json';
        copy(B32CRehearsal::artifact(), $this->releaseRoot.'/'.$artifact);
        copy(database_path('activation/b32c/security-contract.json'), $this->releaseRoot.'/'.$contract);
        $files = [$artifact => hash_file('sha256', $this->releaseRoot.'/'.$artifact), $contract => hash_file('sha256', $this->releaseRoot.'/'.$contract)];
        $this->manifest = json_encode(['version' => 1, 'mode' => 'offline-synthetic', 'artifact' => $artifact, 'contract' => $contract, 'files' => $files], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->permit = ['v' => 1, 'permit_id' => (string) Str::uuid(), 'run_id' => (string) Str::uuid(), 'approval_ref' => 'synthetic-approval', 'action' => 'offline_classify', 'operator' => 'fixture-operator', 'host' => 'fixture-host',
            'target' => ['path' => ':memory:', 'identity' => 'fixture-'.bin2hex(random_bytes(8)), 'class' => 'synthetic-only'], 'environment' => 'offline-synthetic', 'nbf' => 900, 'exp' => 61000, 'max_duration_ms' => 10000,
            'hashes' => ['release' => hash('sha256', $this->manifest), 'artifact' => $files[$artifact], 'contract' => $files[$contract]]];
        $this->expected = array_intersect_key($this->permit, array_flip(['action', 'operator', 'host', 'target', 'hashes']));
        $foundation = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true, 64, JSON_THROW_ON_ERROR);
        $sql = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
        $final = $foundation['foundation_objects'];
        $final['table:audit_logs'] = hash('sha256', $sql['audit']['sql']);
        foreach (['create_tables' => 'table', 'indexes' => 'index', 'triggers' => 'trigger'] as $group => $kind) {
            foreach ($sql[$group] as $name => $statement) {
                $final[$kind.':'.$name] = hash('sha256', $statement);
            }
        }
        ksort($final);
        $this->classifier = new OfflineStateClassifier($foundation['foundation_objects'], $foundation['foundation_ledger'], $final, '2026_11_07_200000_activate_b32_security_contract');
    }

    protected function tearDown(): void
    {
        if (isset($this->secret)) {
            sodium_memzero($this->secret);
        }
        foreach ([$this->registerRoot, $this->releaseRoot] as $root) {
            if (realpath(dirname($root)) !== realpath(sys_get_temp_dir()) || ! preg_match('/^xlipa-offline-(guard|release)-[a-f0-9]+$/D', basename($root))) {
                throw new LogicException('Unsafe fixture cleanup.');
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
                if ($item->isDir() && ! $item->isLink()) {
                    rmdir($item->getPathname());
                } else {
                    unlink($item->getPathname());
                }
            }
            rmdir($root);
        }
        parent::tearDown();
    }

    private function envelope(?array $payload = null): array
    {
        $bytes = json_encode($payload ?? $this->permit, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return [$bytes, bin2hex(sodium_crypto_sign_detached("xlipa-offline-permit-v1\0".$bytes, $this->secret))];
    }

    private function guard(?string $key = null): OfflineSecurityGuard
    {
        return new OfflineSecurityGuard($key ?? $this->public, new OfflineRunRegister($this->registerRoot), $this->classifier, fn (): array => $this->clockState);
    }

    private function inspect(?array $envelope = null, ?array $requested = null, ?\Closure $access = null, array $revoked = []): string
    {
        [$bytes,$signature] = $envelope ?? $this->envelope();

        return $this->guard()->inspect($bytes, $signature, $this->expected, $revoked, $this->releaseRoot, $this->manifest, $requested ?? ['activation/2026_11_07_200000_activate_b32_security_contract.php'], $access ?? function (): array {
            $this->accesses++;

            return ['pdo' => DB::connection()->getPdo(), 'identity' => $this->permit['target']['identity']];
        });
    }

    private function denied(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Invalid offline operation was accepted.');
        } catch (LogicException $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }
    }

    public static function badAuthorizations(): array
    {
        return array_map(fn (string $case): array => [$case], ['missing_signature', 'bad_signature', 'tampered_bytes', 'expired', 'not_yet_valid', 'operator', 'host', 'action', 'action_array', 'real_action', 'target', 'hash', 'environment', 'bad_duration', 'unhealthy_clock', 'revoked', 'working_path', 'duplicate_key', 'missing_key', 'wrong_key', 'actual_environment']);
    }

    #[DataProvider('badAuthorizations')]
    public function test_invalid_authorization_invokes_zero_target_access(string $case): void
    {
        $p = $this->permit;
        $revoked = [];
        $key = $this->public;
        switch ($case) {
            case 'expired':$p['exp'] = 1000;
                break;
            case 'not_yet_valid':$p['nbf'] = 1001;
                break;
            case 'operator':$p['operator'] = 'other-operator';
                break;
            case 'host':$p['host'] = 'other-host';
                break;
            case 'action':$p['action'] = 'offline_select_release';
                break;
            case 'action_array':$p['action'] = ['offline_classify'];
                break;
            case 'real_action':$p['action'] = 'working_migrate';
                $this->expected['action'] = 'working_migrate';
                break;
            case 'target':$p['target']['identity'] = 'fixture-'.str_repeat('0', 16);
                break;
            case 'hash':$p['hashes']['release'] = str_repeat('0', 64);
                break;
            case 'environment':$p['environment'] = 'working-local';
                break;
            case 'bad_duration':$p['max_duration_ms'] = 0;
                break;
            case 'unhealthy_clock':$this->clockState['healthy'] = false;
                break;
            case 'revoked':$revoked = [$p['permit_id']];
                break;
            case 'working_path':$p['target']['path'] = 'C:\Users\hp\OneDrive\Desktop\WEB-DEVELOPMENT\XLipa\backend\database\database.sqlite';
                $this->expected['target'] = $p['target'];
                break;
            case 'missing_key':$key = '';
                break;
            case 'wrong_key':$key = random_bytes(32);
                break;
        }
        [$bytes,$signature] = $this->envelope($p);
        if ($case === 'missing_signature') {
            $signature = '';
        }
        if ($case === 'bad_signature') {
            $signature = str_repeat('0', 128);
        }
        if ($case === 'tampered_bytes') {
            $bytes = str_replace('fixture-operator', 'forged-operator', $bytes);
        }
        if ($case === 'duplicate_key') {
            $bytes = str_replace('"v":1', '"v":1,"v":1', $bytes);
            $signature = bin2hex(sodium_crypto_sign_detached("xlipa-offline-permit-v1\0".$bytes, $this->secret));
        }
        $environment = getenv('APP_ENV');
        if ($case === 'actual_environment') {
            putenv('APP_ENV=local');
        }
        try {
            $this->denied(fn () => $this->guard($key)->inspect($bytes, $signature, $this->expected, $revoked, $this->releaseRoot, $this->manifest, ['activation/2026_11_07_200000_activate_b32_security_contract.php'], function (): array {
                $this->accesses++;
                throw new LogicException('Should never access target.');
            }), 'Offline authorization rejected.');
        } finally {
            putenv('APP_ENV='.$environment);
        }
        $this->assertSame(0, $this->accesses);
        $this->assertSame('', file_get_contents($this->registerRoot.'/claims.log'));
    }

    public static function badSelections(): array
    {
        return array_map(fn (string $case): array => [$case], ['unfiltered', 'wildcard', 'multiple', 'escape', 'extra', 'duplicate_artifact', 'synthetic_leak', 'modified_hash', 'missing_file', 'manifest_tamper']);
    }

    #[DataProvider('badSelections')]
    public function test_release_refusal_invokes_zero_target_access(string $case): void
    {
        $requested = ['activation/2026_11_07_200000_activate_b32_security_contract.php'];
        switch ($case) {
            case 'unfiltered':$requested = [];
                break;
            case 'wildcard':$requested = ['*'];
                break;
            case 'multiple':$requested[] = 'activation/second.php';
                break;
            case 'escape':$requested = ['../database/migrations'];
                break;
            case 'extra':file_put_contents($this->releaseRoot.'/unexpected.txt', 'fixture');
                break;
            case 'duplicate_artifact':copy($this->releaseRoot.'/'.$requested[0], $this->releaseRoot.'/duplicate.php');
                break;
            case 'synthetic_leak':mkdir($this->releaseRoot.'/migrations');
                file_put_contents($this->releaseRoot.'/migrations/2026_11_06_100000_create_synthetic_verification_contract.php', 'fixture');
                break;
            case 'modified_hash':file_put_contents($this->releaseRoot.'/'.$requested[0], "\nchanged", FILE_APPEND);
                break;
            case 'missing_file':unlink($this->releaseRoot.'/activation/security-contract.json');
                break;
            case 'manifest_tamper':$this->manifest = str_replace('offline-synthetic', 'working-local', $this->manifest);
                break;
        }
        $this->denied(fn () => $this->inspect(null, $requested), 'Offline release');
        $this->assertSame(0, $this->accesses);
        $this->assertSame('', file_get_contents($this->registerRoot.'/claims.log'));
    }

    public function test_valid_foundation_is_read_only_and_replay_stays_consumed_after_restart(): void
    {
        $this->assertSame('foundation', $this->inspect());
        $this->assertSame(1, $this->accesses);
        $this->assertSame(1, (int) DB::connection()->getPdo()->query('PRAGMA query_only')->fetchColumn());
        $this->denied(fn () => $this->inspect(), 'already consumed');
        $this->assertSame(1, $this->accesses);
        $p = $this->permit;
        $p['permit_id'] = (string) Str::uuid();
        $this->denied(fn () => $this->inspect($this->envelope($p)), 'already consumed');
        $this->assertSame(1, $this->accesses);
    }

    public function test_interrupted_claim_and_terminal_failure_never_become_reusable(): void
    {
        [$bytes,$sig] = $this->envelope();
        $verified = OfflinePermit::verify($bytes, $sig, $this->public, $this->expected, [], $this->clockState);
        (new OfflineRunRegister($this->registerRoot))->claim($verified);
        $this->denied(fn () => $this->inspect(), 'already consumed');
        $this->assertSame(0, $this->accesses);
        $register = new OfflineRunRegister($this->registerRoot);
        $register->finish($verified, 'unknown');
        $this->denied(fn () => $register->finish($verified, 'completed'), 'transition rejected');
        $this->denied(fn () => $this->inspect(), 'already consumed');
    }

    public function test_partial_or_unavailable_register_denies_before_access(): void
    {
        file_put_contents($this->registerRoot.'/claims.log', 'interrupted partial record');
        $this->denied(fn () => $this->inspect(), 'register corrupt');
        $this->assertSame(0, $this->accesses);
        $this->denied(fn () => new OfflineRunRegister($this->registerRoot.'-missing'), 'register unavailable');
    }

    public function test_lost_register_never_reinitializes_or_releases_prior_claims(): void
    {
        $this->assertSame('foundation', $this->inspect());
        unlink($this->registerRoot.'/claims.log');
        $this->denied(fn () => $this->inspect(), 'register unavailable');
        $this->denied(fn () => OfflineRunRegister::initializeFixture($this->registerRoot), 'initialization refused');
        $this->assertFileDoesNotExist($this->registerRoot.'/claims.log');
        $this->assertSame(1, $this->accesses);
    }

    public function test_expiry_between_claim_and_access_consumes_permit_without_access(): void
    {
        $calls = 0;
        $guard = new OfflineSecurityGuard($this->public, new OfflineRunRegister($this->registerRoot), $this->classifier, function () use (&$calls): array {
            $calls++;

            return ['utc_ms' => $calls === 1 ? 1000 : 61000, 'mono_ms' => 1000, 'healthy' => true];
        });
        [$bytes,$sig] = $this->envelope();
        $this->denied(fn () => $guard->inspect($bytes, $sig, $this->expected, [], $this->releaseRoot, $this->manifest, ['activation/2026_11_07_200000_activate_b32_security_contract.php'], function (): array {
            $this->accesses++;

            return [];
        }), 'clock rejected');
        $this->assertSame(0, $this->accesses);
        $this->denied(fn () => $this->inspect(), 'already consumed');
    }

    public function test_wrong_database_identity_and_disabled_foreign_keys_consume_failed_claim(): void
    {
        $this->denied(fn () => $this->inspect(null, null, function (): array {
            $this->accesses++;

            return ['pdo' => DB::connection()->getPdo(), 'identity' => 'fixture-'.str_repeat('0', 16)];
        }), 'identity mismatch');
        $this->assertSame(1, $this->accesses);
        $this->denied(fn () => $this->inspect(), 'already consumed');
    }

    public function test_disabled_foreign_keys_are_not_silently_enabled(): void
    {
        DB::connection()->getPdo()->exec('PRAGMA foreign_keys=OFF');
        $this->denied(fn () => $this->inspect(), 'foreign keys required');
        $this->assertSame(0, (int) DB::connection()->getPdo()->query('PRAGMA foreign_keys')->fetchColumn());
    }

    public static function states(): array
    {
        return [['completed', 'completed'], ['gap', 'quarantine:schema_without_ledger'], ['missing', 'quarantine:ledger_schema_mismatch'], ['partial', 'quarantine:drift_or_partial'], ['ledger_drift', 'quarantine:drift_or_partial'], ['schema_drift', 'quarantine:drift_or_partial'], ['ambiguous', 'quarantine:ambiguous'], ['nonempty', 'quarantine:nonempty_security_tables'], ['foreign_key_corruption', 'quarantine:integrity']];
    }

    #[DataProvider('states')]
    public function test_state_classification_never_repairs_or_adopts(string $fixture, string $expected): void
    {
        if (in_array($fixture, ['completed', 'gap', 'missing', 'nonempty'], true)) {
            B32CRehearsal::activate();
            if ($fixture === 'gap') {
                DB::table('migrations')->where('migration', '2026_11_07_200000_activate_b32_security_contract')->delete();
            }
            if ($fixture === 'missing') {
                DB::statement('DROP TABLE verification_limit_state');
            }
            if ($fixture === 'nonempty') {
                $owner = User::factory()->create();
                $merchant = app(CreateMerchant::class)->execute($owner, 'Synthetic nonempty classifier fixture');
                DB::table('merchant_onboarding_contexts')->insert(['merchant_id' => $merchant->id, 'updated_at' => 1]);
            }
        } elseif ($fixture === 'partial') {
            DB::statement('CREATE TABLE merchant_identity_records (id TEXT)');
        } elseif ($fixture === 'ledger_drift') {
            DB::table('migrations')->where('id', 1)->update(['batch' => 99]);
        } elseif ($fixture === 'schema_drift') {
            DB::statement('CREATE INDEX unexpected_guard_fixture ON users(account_status)');
        } elseif ($fixture === 'ambiguous') {
            DB::statement('DROP TABLE migrations');
        } elseif ($fixture === 'foreign_key_corruption') {
            DB::statement('PRAGMA foreign_keys=OFF');
            DB::table('audit_logs')->insert(['id' => (string) Str::ulid(), 'event_type' => 'merchant.created', 'actor_user_id' => 999999, 'request_id' => (string) Str::uuid(), 'occurred_at' => '2026-11-07']);
            DB::statement('PRAGMA foreign_keys=ON');
        }
        $before = DB::select('SELECT type,name,sql FROM sqlite_master ORDER BY type,name');
        $this->assertSame($expected, $this->inspect());
        $this->assertEquals($before, DB::select('SELECT type,name,sql FROM sqlite_master ORDER BY type,name'));
        $this->assertSame(1, $this->accesses);
    }

    public function test_release_only_action_never_invokes_database_callback_and_context_cannot_be_forged(): void
    {
        $this->permit['action'] = $this->expected['action'] = 'offline_select_release';
        $this->assertSame('selected_offline_only', $this->inspect());
        $this->assertSame(0, $this->accesses);
        $this->assertFalse((new \ReflectionClass(OfflinePermit::class))->isInstantiable());
        [$bytes,$sig] = $this->envelope();
        $cap = OfflinePermit::verify($bytes, $sig, $this->public, $this->expected, [], $this->clockState);
        $this->denied(fn () => serialize($cap), 'serialization forbidden');
        $this->denied(fn () => clone $cap, 'cloning forbidden');
        $class = OfflinePermit::class;
        $this->denied(fn () => unserialize('O:'.strlen($class).':"'.$class.'":0:{}'), 'deserialization forbidden');
    }

    public function test_selection_that_outlives_its_permit_never_returns_authority(): void
    {
        $this->permit['action'] = $this->expected['action'] = 'offline_select_release';
        $polls = 0;
        $guard = new OfflineSecurityGuard($this->public, new OfflineRunRegister($this->registerRoot), $this->classifier, function () use (&$polls): array {
            $polls++;

            return ['utc_ms' => $polls < 3 ? 1000 : 61000, 'mono_ms' => 1000, 'healthy' => true];
        });
        [$bytes, $sig] = $this->envelope();
        $this->denied(fn () => $guard->inspect($bytes, $sig, $this->expected, [], $this->releaseRoot, $this->manifest, ['activation/2026_11_07_200000_activate_b32_security_contract.php'], function (): array {
            $this->accesses++;

            return [];
        }), 'clock rejected');
        $this->assertSame(0, $this->accesses);
        $this->denied(fn () => $this->inspect(), 'already consumed');
    }

    public function test_revocation_after_claim_is_denied_before_access(): void
    {
        [$bytes,$sig] = $this->envelope();
        $polls = 0;
        $guard = new OfflineSecurityGuard($this->public, new OfflineRunRegister($this->registerRoot), $this->classifier, fn (): array => $this->clockState, function () use (&$polls): array {
            $polls++;

            return $polls === 1 ? [] : [$this->permit['permit_id']];
        });
        $this->denied(fn () => $guard->inspect($bytes, $sig, $this->expected, [], $this->releaseRoot, $this->manifest, ['activation/2026_11_07_200000_activate_b32_security_contract.php'], function (): array {
            $this->accesses++;

            return [];
        }), 'authorization rejected');
        $this->assertSame(0, $this->accesses);
        $this->denied(fn () => $this->inspect(), 'already consumed');
    }

    public function test_monotonic_budget_failure_after_claim_never_retries_access(): void
    {
        $calls = 0;
        $guard = new OfflineSecurityGuard($this->public, new OfflineRunRegister($this->registerRoot), $this->classifier, function () use (&$calls): array {
            $calls++;

            return ['utc_ms' => 1000, 'mono_ms' => $calls === 1 ? 1000 : 11000, 'healthy' => true];
        });
        [$bytes,$sig] = $this->envelope();
        $this->denied(fn () => $guard->inspect($bytes, $sig, $this->expected, [], $this->releaseRoot, $this->manifest, ['activation/2026_11_07_200000_activate_b32_security_contract.php'], function (): array {
            $this->accesses++;

            return [];
        }), 'clock rejected');
        $this->assertSame(0, $this->accesses);
    }

    public function test_attached_memory_database_is_not_an_authorized_identity(): void
    {
        DB::connection()->getPdo()->exec("ATTACH DATABASE ':memory:' AS extra");
        $this->denied(fn () => $this->inspect(), 'identity mismatch');
        $this->assertSame(1, $this->accesses);
    }

    public function test_clock_rollback_is_denied_before_access(): void
    {
        $polls = 0;
        $guard = new OfflineSecurityGuard($this->public, new OfflineRunRegister($this->registerRoot), $this->classifier, function () use (&$polls): array {
            $polls++;

            return ['utc_ms' => $polls === 1 ? 1000 : 999, 'mono_ms' => 1000, 'healthy' => true];
        });
        [$bytes,$sig] = $this->envelope();
        $this->denied(fn () => $guard->inspect($bytes, $sig, $this->expected, [], $this->releaseRoot, $this->manifest, ['activation/2026_11_07_200000_activate_b32_security_contract.php'], function (): array {
            $this->accesses++;

            return [];
        }), 'clock rejected');
        $this->assertSame(0, $this->accesses);
    }

    public function test_two_independent_processes_claim_one_permit_once_and_restart_cannot_reuse_it(): void
    {
        [$bytes,$signature] = $this->envelope();
        file_put_contents($this->registerRoot.'/public-input.json', json_encode(['bytes' => $bytes, 'signature' => $signature, 'public' => bin2hex($this->public), 'expected' => $this->expected, 'clock' => $this->clockState], JSON_THROW_ON_ERROR));
        $workers = [];
        try {
            foreach (['0', '1'] as $id) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/offline-claim-worker.php'), $this->registerRoot, $id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 15;
            while (! is_file($this->registerRoot.'/ready0') || ! is_file($this->registerRoot.'/ready1')) {
                if (microtime(true) > $deadline) {
                    throw new LogicException('Offline claim fixture startup failed.');
                }usleep(10000);
            }
            file_put_contents($this->registerRoot.'/go', 'fixture');
            $winners = 0;
            foreach ($workers as [$process,$pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $errors);
                $winners += (int) json_decode($output, true, 8, JSON_THROW_ON_ERROR)['claimed'];
            }
            $workers = [];
            $this->assertSame(1, $winners);
            $this->denied(fn () => $this->inspect(), 'already consumed');
            $this->assertSame(0, $this->accesses);
        } finally {
            foreach ($workers as [$process,$pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    foreach ([1, 2] as $i) {
                        if (is_resource($pipes[$i])) {
                            fclose($pipes[$i]);
                        }
                    }proc_close($process);
                }
            }
        }
    }
}
