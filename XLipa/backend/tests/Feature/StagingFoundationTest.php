<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAuthenticationReady;
use App\Staging\StagingCapability;
use App\Staging\StagingIdentity as Identity;
use App\Staging\StagingServiceProvider;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StagingFoundationTest extends TestCase
{
    private string $root;

    private string $secret;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = str_replace('\\', '/', sys_get_temp_dir()).'/xlipa-r21-test-'.bin2hex(random_bytes(10));
        mkdir($this->root);
        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        $this->context = ['root' => $this->root, 'namespace' => 'xlipa-fictional-r21', 'issuer' => 'fictional-test', 'runtime' => 'test-runtime',
            'release' => 'test-release', 'generation' => 'test-generation', 'store' => 'test-store', 'public' => sodium_crypto_sign_publickey($pair), 'expires' => time() + 300];
        foreach (['workflow', 'financial', 'admission'] as $kind) {
            $pdo = new PDO('sqlite:'.$this->root.'/'.$kind.'.sqlite');
            $pdo->exec('PRAGMA application_id=21; PRAGMA user_version=1');
            $pdo->exec('CREATE TABLE staging_identity(runtime TEXT,generation TEXT,profile TEXT)');
            $pdo->prepare('INSERT INTO staging_identity VALUES(?,?,?)')->execute(['test-runtime', 'test-generation', $kind === 'financial' ? 'unprovisioned-r22' : 'admission-r21']);
            if ($kind === 'admission') {
                $pdo->exec('CREATE TABLE revoked(id TEXT PRIMARY KEY); CREATE TABLE commands(id TEXT PRIMARY KEY,capability TEXT); CREATE TABLE clock(observed INTEGER)');
                $pdo->prepare('INSERT INTO clock VALUES(?)')->execute([time()]);
            }
            $this->context['schemas'][$kind] = Identity::schema($pdo);
            $this->context['headers'][$kind] = ['application' => 21, 'version' => 1];
        }
    }

    protected function tearDown(): void
    {
        foreach (['workflow.sqlite', 'financial.sqlite', 'admission.sqlite', 'retired', 'canary', 'alias'] as $file) {
            $path = $this->root.'/'.$file;
            if (is_link($path) || is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->root);
        parent::tearDown();
    }

    private function grant(array $changes = []): array
    {
        return Identity::signed(array_replace([
            'namespace' => 'xlipa-fictional-r21', 'issuer' => 'fictional-test', 'runtime' => 'test-runtime', 'release' => 'test-release',
            'generation' => 'test-generation', 'store' => 'test-store', 'id' => str_repeat('a', 32), 'actor' => 7,
            'merchant' => 'merchant-one', 'action' => 'dashboard.read', 'resource' => '/dashboard', 'version' => 3,
            'issued' => time() - 1, 'expires' => time() + 60, 'command' => null,
        ], $changes), $this->secret);
    }

    public function test_narrow_grant_and_schema_are_usable_without_financial_authority(): void
    {
        Identity::assertStores($this->context);
        (new StagingCapability($this->context))->authorize($this->grant(), 7, 'merchant-one', 'dashboard.read', '/dashboard', 3);
        $pdo = new PDO('sqlite:'.$this->root.'/financial.sqlite');
        $this->assertSame(['staging_identity'], $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function wrongGrants(): array
    {
        return array_map(fn ($change) => [$change], [
            ['namespace' => 'operational'], ['issuer' => 'foreign'], ['runtime' => 'foreign'], ['release' => 'foreign'], ['generation' => 'foreign'],
            ['store' => 'foreign'], ['actor' => 8], ['merchant' => 'merchant-two'], ['action' => 'ledger.post'], ['action' => 'admin.chief.read'],
            ['resource' => '/foreign'], ['version' => 2], ['expires' => 1], ['issued' => PHP_INT_MAX], ['expires' => PHP_INT_MAX], ['command' => 'unexpected'],
        ]);
    }

    #[DataProvider('wrongGrants')]
    public function test_signed_but_inapplicable_capabilities_are_denied(array $change): void
    {
        $this->expectException(\LogicException::class);
        (new StagingCapability($this->context))->authorize($this->grant($change), 7, 'merchant-one', 'dashboard.read', '/dashboard', 3);
    }

    public function test_tampering_revocation_retirement_and_replay_fail_closed(): void
    {
        $service = new StagingCapability($this->context);
        $grant = $this->grant();
        $grant['value']['actor'] = 8;
        try {
            $service->authorize($grant, 8, 'merchant-one', 'dashboard.read', '/dashboard', 3);
            $this->fail('Tampered grant accepted');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        $pdo = new PDO('sqlite:'.$this->root.'/admission.sqlite');
        $pdo->prepare('INSERT INTO revoked VALUES(?)')->execute([str_repeat('a', 32)]);
        try {
            $service->authorize($this->grant(), 7, 'merchant-one', 'dashboard.read', '/dashboard', 3);
            $this->fail('Revoked grant accepted');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        $command = str_repeat('b', 32);
        $oneShot = $this->grant(['id' => str_repeat('c', 32), 'action' => 'foundation.probe', 'command' => $command]);
        $service->authorize($oneShot, 7, 'merchant-one', 'foundation.probe', '/dashboard', 3, $command);
        try {
            $service->authorize($oneShot, 7, 'merchant-one', 'foundation.probe', '/dashboard', 3, $command);
            $this->fail('Replay accepted');
        } catch (\PDOException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM commands')->fetchColumn());
        file_put_contents($this->root.'/retired', 'retired');
        $this->expectException(\LogicException::class);
        $service->authorize($oneShot, 7, 'merchant-one', 'foundation.probe', '/dashboard', 3, $command);
    }

    public static function unsafeRuntime(): array
    {
        return [
            ['run-'.str_repeat('a', 32), 'cli', '127.0.0.2:18762', '127.0.0.1'],
            ['run-'.str_repeat('a', 32), 'fpm-fcgi', 'xlipa.test', '127.0.0.1'],
            ['', 'cli-server', '127.0.0.2:18762', '127.0.0.1'],
            ['../canary', 'cli-server', '127.0.0.2:18762', '127.0.0.1'],
            ['run-'.str_repeat('a', 32), 'cli-server', 'xlipa.test', '127.0.0.1'],
            ['run-'.str_repeat('a', 32), 'cli-server', '127.0.0.2:18762', '203.0.113.10'],
        ];
    }

    #[DataProvider('unsafeRuntime')]
    public function test_untrusted_runtime_is_denied_before_target_access(string $id, string $sapi, string $host, string $peer): void
    {
        file_put_contents($this->root.'/canary', 'fictional protected canary');
        $before = hash_file('sha256', $this->root.'/canary');
        try {
            Identity::admit($id, $sapi, $host, $peer);
            $this->fail('Unsafe runtime accepted');
        } catch (\LogicException) {
            $this->assertSame($before, hash_file('sha256', $this->root.'/canary'));
        }
    }

    public static function unsafePaths(): array
    {
        return array_map(fn ($tail) => [$tail], ['/../canary', '/./canary', '//canary', '/canary.', '/canary ', '/CON', '/canary:stream', '/missing', '/alias\\canary']);
    }

    #[DataProvider('unsafePaths')]
    public function test_windows_aliases_and_traversal_are_rejected(string $tail): void
    {
        file_put_contents($this->root.'/canary', 'canary');
        $this->expectException(\LogicException::class);
        Identity::path($this->root.$tail);
    }

    public function test_schema_lineage_generation_and_clock_rollback_are_denied(): void
    {
        $pdo = new PDO('sqlite:'.$this->root.'/financial.sqlite');
        $pdo->exec("UPDATE staging_identity SET generation='retired-generation'");
        try {
            Identity::assertStores($this->context);
            $this->fail('Wrong generation accepted');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        $pdo->exec("UPDATE staging_identity SET generation='test-generation'; CREATE TABLE unexpected(x TEXT)");
        try {
            Identity::assertStores($this->context);
            $this->fail('Schema drift accepted');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        $clock = new PDO('sqlite:'.$this->root.'/admission.sqlite');
        $clock->exec('UPDATE clock SET observed='.PHP_INT_MAX);
        $this->expectException(\LogicException::class);
        (new StagingCapability($this->context))->authorize($this->grant(), 7, 'merchant-one', 'dashboard.read', '/dashboard', 3);
    }

    public function test_operational_boot_has_no_staging_binding_and_rejects_provider(): void
    {
        $this->assertFalse(app()->bound('staging.context'));
        $this->assertSame(EnsureAuthenticationReady::class, app(EnsureAuthenticationReady::class)::class);
        $this->assertFalse(collect(app('router')->getRoutes())->contains(fn ($route) => str_starts_with($route->uri(), 'staging/')));
        $this->expectException(\LogicException::class);
        app()->register(StagingServiceProvider::class);
    }
}
