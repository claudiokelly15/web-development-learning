<?php

namespace Tests\Unit;

use Closure;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SQLite3;
use Symfony\Component\Process\Process;
use Tests\Support\SobBoundary;
use Tests\Support\SobCustody;
use Tests\Support\SobFence;
use Tests\Support\SobFixture;
use Tests\Support\SobPermit;
use Tests\Support\SobRelease;
use Tests\Support\SobWindowsLease;
use Tests\Support\SobWitnessRegister;

class SobBoundaryTest extends TestCase
{
    private array $roots = [];

    private string $root;

    private string $registerRoot;

    private string $witnessRoot;

    private string $releaseRoot;

    private string $signingSecret;

    private string $signingPublic;

    private string $encryptionKey;

    private string $encryptionPublic;

    private array $reviewed;

    private SobWitnessRegister $register;

    private SobFence $fence;

    private int $generation;

    private array $clock = ['healthy' => true, 'utc' => 1000, 'mono' => 1000];

    private array $status = ['healthy' => true, 'suspended' => false, 'epoch' => 1, 'at' => 1000, 'revoked' => []];

    private int $accesses = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = $this->directory();
        $this->registerRoot = $this->directory();
        $this->witnessRoot = $this->directory();
        $this->releaseRoot = $this->directory();
        SobWitnessRegister::initialize($this->registerRoot, $this->witnessRoot);
        $this->register = new SobWitnessRegister($this->registerRoot, $this->witnessRoot);
        $this->fence = new SobFence;
        $this->generation = $this->fence->acquire();
        $pair = sodium_crypto_sign_keypair();
        $this->signingSecret = sodium_crypto_sign_secretkey($pair);
        $this->signingPublic = sodium_crypto_sign_publickey($pair);
        sodium_memzero($pair);
        $this->encryptionKey = sodium_crypto_box_keypair();
        $this->encryptionPublic = sodium_crypto_box_publickey($this->encryptionKey);
        $this->reviewed = [];
        $assets = ['artifact.txt' => 'SOB inert synthetic migration text; never executed', 'contract.json' => '{"synthetic":true}', 'executor.txt' => SobRelease::executorBytes(), 'policy.json' => SobRelease::policyBytes()];
        foreach ($assets as $name => $bytes) {
            SobFixture::create($this->releaseRoot, $name, $bytes);
            $this->reviewed[$name] = hash('sha256', $bytes);
        }
        $db = new SQLite3($this->root.'/source.sqlite', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $db->enableExceptions(true);
        $db->exec('CREATE TABLE fixture_rows (id INTEGER PRIMARY KEY, payload TEXT NOT NULL)');
        $db->exec('CREATE TABLE fixture_ledger (state TEXT NOT NULL)');
        $db->exec("INSERT INTO fixture_ledger VALUES ('foundation')");
        $db->exec("INSERT INTO fixture_rows VALUES (1,'independently generated alpha'),(2,'independently generated beta')");
        $db->close();
    }

    private function directory(): string
    {
        $root = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/xlipa-sob-'.bin2hex(random_bytes(10));
        mkdir($root, 0700);
        $this->roots[] = $root;

        return $root;
    }

    protected function tearDown(): void
    {
        sodium_memzero($this->signingSecret);
        sodium_memzero($this->encryptionKey);
        foreach ($this->roots as $root) {
            SobFixture::root($root);
            foreach (array_diff(scandir($root), ['.', '..']) as $name) {
                if (is_dir($root.'/'.$name)) {
                    throw new LogicException('SOB unexpected directory; refuse recursive cleanup.');
                }
                unlink($root.'/'.$name);
            }
            rmdir($root);
        }
        parent::tearDown();
    }

    private function request(string $action = 'backup_simulation'): array
    {
        $name = $action === 'restore_simulation' ? 'backup.sob' : 'source.sqlite';
        $lease = new SobWindowsLease($this->root, [$name]);
        try {
            $identity = $lease->digest();
        } finally {
            $lease->close();
        }
        $expected = ['action' => $action, 'operator' => 'sim-operator-alpha', 'host' => 'sim-host-alpha', 'environment' => 'sob-synthetic-only',
            'target' => ['root' => $this->root, 'file' => $name, 'identity' => $identity, 'hash' => hash_file('sha256', $this->root.'/'.$name)],
            'release' => hash('sha256', SobFixture::canonical($this->reviewed)), 'policy' => $this->reviewed['policy.json'], 'executor' => $this->reviewed['executor.txt']];
        $p = $expected + ['v' => 1, 'id' => bin2hex(random_bytes(16)), 'run' => bin2hex(random_bytes(16)), 'nbf' => 0, 'exp' => 20000, 'max_ms' => 10000, 'status_epoch' => 1, 'checkpoint' => $this->register->checkpoint()];

        return $this->signed($p, $expected);
    }

    private function signed(array $p, array $expected): array
    {
        $bytes = SobFixture::canonical($p);

        return ['bytes' => $bytes, 'signature' => bin2hex(sodium_crypto_sign_detached(SobPermit::DOMAIN.$bytes, $this->signingSecret)), 'expected' => $expected];
    }

    private function permit(array $r): SobPermit
    {
        return SobPermit::verify($r['bytes'], hex2bin($r['signature']), $this->signingPublic, $r['expected'], $this->clock, $this->status);
    }

    private function boundary(?Closure $key = null): SobBoundary
    {
        return new SobBoundary($this->signingPublic, $this->register, $this->fence, fn (): array => $this->clock, fn (): array => $this->status, $this->releaseRoot, $this->reviewed, $this->encryptionPublic, $key ?? fn (): string => $this->encryptionKey);
    }

    private function runRequest(array $r, ?Closure $hook = null, array $requested = ['artifact.txt'], int $capacity = 1048576, bool $io = false): array
    {
        return $this->boundary()->run($r['bytes'], hex2bin($r['signature']), $r['expected'], $this->generation, $requested, function (string $phase) use ($hook): void {
            if ($phase === 'before_target_access') {
                $this->accesses++;
            }
            if ($hook !== null) {
                $hook($phase);
            }
        }, $capacity, $io);
    }

    private function denies(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('SOB unsafe operation allowed.');
        } catch (\Exception $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }
    }

    public function test_native_identity_and_sharing_lease_block_file_and_directory_replacement(): void
    {
        $lease = new SobWindowsLease($this->root, ['source.sqlite']);
        try {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{8}:[a-f0-9]{16}$/', $lease->identities['source.sqlite']);
            $this->assertNotSame($lease->identities['root'], $lease->identities['source.sqlite']);
            $this->assertFalse(@file_put_contents($this->root.'/source.sqlite', 'replacement'));
            $this->assertFalse(@rename($this->root.'/source.sqlite', $this->root.'/second.txt'));
            $this->assertFalse(@rename($this->root, $this->root.'-moved'));
            $db = new SQLite3($this->root.'/source.sqlite', SQLITE3_OPEN_READONLY);
            $this->assertSame(2, $db->querySingle('SELECT count(*) FROM fixture_rows'));
            $db->close();
            $lease->alive();
        } finally {
            $lease->close();
        }
        $this->assertTrue(rename($this->root.'/source.sqlite', $this->root.'/second.txt'));
        $this->assertTrue(rename($this->root.'/second.txt', $this->root.'/source.sqlite'));
    }

    public function test_native_hardlink_and_path_alias_are_refused(): void
    {
        $this->assertTrue(link($this->root.'/source.sqlite', $this->root.'/second.txt'));
        $this->denies(fn () => new SobWindowsLease($this->root, ['source.sqlite']), 'hardlink');
        unlink($this->root.'/second.txt');
        $this->denies(fn () => new SobWindowsLease($this->root.'/../'.basename($this->root), ['source.sqlite']), 'before access');
        $this->denies(fn () => new SobWindowsLease($this->root, ['../source.sqlite']), 'before access');
    }

    public function test_native_junction_parent_is_rejected_without_entering_its_target(): void
    {
        $target = $this->directory();
        SobFixture::create($target, 'fixture.txt', 'independent junction fixture');
        $process = new Process(['C:/Windows/System32/WindowsPowerShell/v1.0/powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', dirname(__DIR__).'/Support/sob-windows-lease.ps1'], null, null, json_encode(['root' => $this->root, 'target' => $target, 'mode' => 'junction'])."\n", 15);
        try {
            $this->assertSame(0, $process->run(), $process->getErrorOutput());
            $this->denies(fn () => new SobWindowsLease($this->root, ['nested/fixture.txt']), 'reparse');
        } finally {
            // Nonrecursive removal of this exact disposable junction, never its target.
            if (is_dir($this->root.'/nested')) {
                $this->assertTrue(rmdir($this->root.'/nested'));
            }
        }
        $this->assertSame('independent junction fixture', SobFixture::read($target, 'fixture.txt'));
    }

    public function test_directory_replacement_before_lease_is_detected_by_parent_identity(): void
    {
        $r = $this->request();
        $moved = $this->directory();
        rmdir($moved);
        rename($this->root, $moved);
        mkdir($this->root, 0700);
        copy($moved.'/source.sqlite', $this->root.'/source.sqlite');
        $this->denies(fn () => $this->runRequest($r), 'identity/hash');
        $this->assertFileDoesNotExist($this->root.'/backup.sob');
    }

    public function test_bootstrap_and_capability_copying_cannot_promote_prototype_authority(): void
    {
        $r = $this->request();
        $permit = $this->permit($r);
        $this->denies(fn () => serialize($permit), 'serialization forbidden');
        $this->denies(fn () => clone $permit, 'cloning forbidden');
        putenv('APP_ENV=production');
        try {
            $this->denies(fn () => $this->runRequest($r), 'synthetic bootstrap required');
        } finally {
            putenv('APP_ENV=testing');
        }
        $this->assertSame(0, $this->accesses);
    }

    public function test_encrypted_synthetic_capture_and_restore_have_independent_completed_claims(): void
    {
        $before = hash_file('sha256', $this->root.'/source.sqlite');
        $backup = $this->request();
        $result = $this->runRequest($backup);
        $this->assertSame('encrypted_fixture_published', $result['execution']);
        $envelope = SobFixture::read($this->root, 'backup.sob');
        $this->assertStringNotContainsString('independently generated', $envelope);
        $opened = SobCustody::open($envelope, $this->encryptionKey);
        $restore = $this->runRequest($this->request('restore_simulation'));
        $restored = SobCustody::open(SobFixture::read($this->root, 'restore.sob'), $this->encryptionKey);
        $this->assertSame($opened['plaintext'], $restored['plaintext']);
        $this->assertSame($result['digest'], $restored['provenance']['origin']);
        $this->assertNotSame($result['digest'], $restore['digest']);
        $this->assertSame($before, hash_file('sha256', $this->root.'/source.sqlite'));
        $this->assertSame(4, $this->register->checkpoint());
        $this->denies(fn () => $this->runRequest($backup), 'consumed');
        $this->denies(fn () => $this->runRequest($this->request()), 'never overwrite');
    }

    public static function invalidAuthorization(): array
    {
        return array_map(static fn (string $case): array => [$case], ['signature', 'domain', 'operator', 'host', 'environment', 'action', 'release', 'executor', 'policy', 'target', 'expired', 'future', 'long-validity', 'long-duration', 'revoked', 'suspended', 'status-epoch', 'stale-status', 'clock', 'unknown', 'duplicate', 'noncanonical', 'wrong-source']);
    }

    #[DataProvider('invalidAuthorization')]
    public function test_invalid_authorization_has_zero_target_access(string $case): void
    {
        $r = $this->request();
        $p = SobFixture::decode($r['bytes']);
        switch ($case) {
            case 'operator': $p['operator'] = 'sim-operator-other';
                break;
            case 'host': $p['host'] = 'sim-host-other';
                break;
            case 'environment': $p['environment'] = 'production';
                break;
            case 'action': $p['action'] = 'working_migrate';
                break;
            case 'release': case 'executor': case 'policy': $p[$case] = str_repeat('0', 64);
                break;
            case 'target': $p['target']['root'] = 'C:/working-never-accessed';
                break;
            case 'expired': $p['exp'] = 1000;
                break;
            case 'future': $p['nbf'] = 1001;
                break;
            case 'long-validity': $p['exp'] = 300001;
                break;
            case 'long-duration': $p['max_ms'] = 60001;
                break;
            case 'revoked': $this->status['revoked'][] = $p['id'];
                break;
            case 'suspended': $this->status['suspended'] = true;
                break;
            case 'status-epoch': $this->status['epoch'] = 2;
                break;
            case 'stale-status': $this->status['at'] = -60000;
                break;
            case 'clock': $this->clock['healthy'] = false;
                break;
            case 'unknown': $p['extra'] = true;
                break;
            case 'wrong-source': $p['target']['file'] = 'backup.sob';
                $r['expected']['target']['file'] = 'backup.sob';
                break;
        }
        $r = $this->signed($p, $r['expected']);
        if ($case === 'signature') {
            $r['signature'] = str_repeat('00', 64);
        } elseif ($case === 'domain') {
            $r['signature'] = bin2hex(sodium_crypto_sign_detached("xlipa-offline-permit-v1\0".$r['bytes'], $this->signingSecret));
        } elseif (in_array($case, ['duplicate', 'noncanonical'], true)) {
            $r['bytes'] = $case === 'duplicate' ? '{"v":1,'.substr($r['bytes'], 1) : ' '.$r['bytes'];
            $r['signature'] = bin2hex(sodium_crypto_sign_detached(SobPermit::DOMAIN.$r['bytes'], $this->signingSecret));
        }
        $this->denies(fn () => $this->runRequest($r), 'authorization rejected');
        $this->assertSame(0, $this->accesses);
        $this->assertSame(0, $this->register->checkpoint());
        $this->assertFileDoesNotExist($this->root.'/backup.sob');
    }

    public function test_wrong_native_identity_and_pre_access_file_replacement_are_quarantined(): void
    {
        $r = $this->request();
        $this->assertTrue(rename($this->root.'/source.sqlite', $this->root.'/second.txt'));
        copy($this->root.'/second.txt', $this->root.'/source.sqlite');
        $this->denies(fn () => $this->runRequest($r), 'identity/hash');
        $this->assertSame(1, $this->register->checkpoint());
        $this->assertFileDoesNotExist($this->root.'/backup.sob');
    }

    public function test_release_allowlist_hash_pins_and_direct_bypass_are_enforced(): void
    {
        $r = $this->request();
        $this->denies(fn () => $this->runRequest($r, null, [$this->releaseRoot.'/artifact.txt']), 'selection rejected');
        SobFixture::create($this->releaseRoot, 'unexpected.php', '<?php never_execute();');
        $this->denies(fn () => $this->runRequest($r), 'inventory rejected');
        unlink($this->releaseRoot.'/unexpected.php');
        foreach (SobRelease::FILES as $name) {
            $original = SobFixture::read($this->releaseRoot, $name);
            file_put_contents($this->releaseRoot.'/'.$name, 'substituted asset');
            $this->denies(fn () => $this->runRequest($r), 'hash rejected');
            file_put_contents($this->releaseRoot.'/'.$name, $original);
        }
        $this->assertSame(0, $this->accesses);
        $this->assertSame(0, $this->register->checkpoint());
    }

    public function test_staged_release_stays_readable_and_unmodifiable_until_result_publication(): void
    {
        $r = $this->request();
        $this->runRequest($r, function (string $phase): void {
            if ($phase === 'target_leased') {
                $this->assertFalse(@file_put_contents($this->releaseRoot.'/artifact.txt', 'tampered'));
                $this->assertFalse(@rename($this->releaseRoot, $this->releaseRoot.'-moved'));
                $this->assertFalse(@file_put_contents($this->root.'/source.sqlite', 'tampered'));
            }
        });
        $this->assertSame(2, $this->register->checkpoint());
    }

    public function test_extra_release_file_after_selection_stops_before_publication(): void
    {
        $r = $this->request();
        $this->denies(fn () => $this->runRequest($r, function (string $phase): void {
            if ($phase === 'target_leased') {
                SobFixture::create($this->releaseRoot, 'unexpected.php', 'inert unreviewed migration');
            }
        }), 'inventory rejected');
        $this->assertFileDoesNotExist($this->root.'/backup.sob');
        $this->assertSame(1, $this->register->checkpoint());
    }

    public static function repinnedAssets(): array
    {
        return [['executor.txt'], ['policy.json']];
    }

    #[DataProvider('repinnedAssets')]
    public function test_repinned_executor_or_policy_cannot_replace_fixed_prototype(string $name): void
    {
        file_put_contents($this->releaseRoot.'/'.$name, 'newly signed unreviewed source or policy');
        $this->reviewed[$name] = hash_file('sha256', $this->releaseRoot.'/'.$name);
        $this->denies(fn () => $this->runRequest($this->request()), 'source pin rejected');
        $this->assertSame(0, $this->accesses);
        $this->assertSame(0, $this->register->checkpoint());
    }

    public function test_claim_is_witnessed_before_first_target_access(): void
    {
        $this->runRequest($this->request(), function (string $phase): void {
            if ($phase === 'before_target_access') {
                $this->assertSame(1, $this->register->checkpoint());
                $this->assertSame(SobFixture::read($this->registerRoot, 'register.log'), SobFixture::read($this->witnessRoot, 'witness.log'));
                $this->assertFileDoesNotExist($this->root.'/backup.sob');
            }
        });
        $this->assertSame(1, $this->accesses);
    }

    public static function registerFailures(): array
    {
        return [['missing-register'], ['missing-witness'], ['corrupt-register'], ['corrupt-witness'], ['rollback-register'], ['rollback-witness'], ['conflict'], ['stale-checkpoint']];
    }

    #[DataProvider('registerFailures')]
    public function test_register_and_witness_fail_closed(string $case): void
    {
        $r = $this->request();
        if ($case === 'missing-register') {
            unlink($this->registerRoot.'/register.log');
        } elseif ($case === 'missing-witness') {
            unlink($this->witnessRoot.'/witness.log');
        } elseif (str_starts_with($case, 'corrupt')) {
            file_put_contents(($case === 'corrupt-register' ? $this->registerRoot.'/register.log' : $this->witnessRoot.'/witness.log'), 'torn');
        } else {
            $this->register->claim($this->permit($r));
            if ($case === 'rollback-register') {
                file_put_contents($this->registerRoot.'/register.log', '');
            } elseif ($case === 'rollback-witness') {
                file_put_contents($this->witnessRoot.'/witness.log', '');
            } elseif ($case === 'conflict') {
                file_put_contents($this->witnessRoot.'/witness.log', "{}\n");
            }
            $p = SobFixture::decode($r['bytes']);
            $p['id'] = bin2hex(random_bytes(16));
            $p['run'] = bin2hex(random_bytes(16));
            $r = $this->signed($p, $r['expected']);
        }
        $this->denies(fn () => $this->runRequest($r), $case === 'stale-checkpoint' ? 'stale checkpoint' : 'SOB');
        $this->assertSame(0, $this->accesses);
        $this->assertFileDoesNotExist($this->root.'/backup.sob');
    }

    public function test_single_use_run_ids_and_interrupted_claims_are_not_refunded(): void
    {
        $r = $this->request();
        $this->register->claim($this->permit($r));
        $this->denies(fn () => $this->register->claim($this->permit($r)), 'consumed');
        $p = SobFixture::decode($r['bytes']);
        $p['id'] = bin2hex(random_bytes(16));
        $p['checkpoint'] = 1;
        $other = $this->signed($p, $r['expected']);
        $this->denies(fn () => $this->register->claim($this->permit($other)), 'consumed');
        $this->assertSame(1, $this->register->checkpoint());
    }

    public static function custodyFailures(): array
    {
        return [['missing-key'], ['wrong-key'], ['ciphertext'], ['provenance'], ['wrapped'], ['nonce'], ['unknown-field']];
    }

    #[DataProvider('custodyFailures')]
    public function test_custody_cryptographically_rejects_tampering_and_key_loss(string $case): void
    {
        $bytes = SobCustody::seal('independent synthetic plaintext', ['artifact' => 'fixture-only'], $this->encryptionPublic);
        $envelope = SobFixture::decode($bytes);
        $key = $this->encryptionKey;
        switch ($case) {
            case 'missing-key': $key = null;
                break;
            case 'wrong-key': $key = sodium_crypto_box_keypair();
                break;
            case 'ciphertext': case 'wrapped': case 'nonce':
                $binary = base64_decode($envelope[$case]);
                $binary[0] = chr(ord($binary[0]) ^ 1);
                $envelope[$case] = base64_encode($binary);
                break;
            case 'provenance': $envelope['provenance']['artifact'] = 'forged';
                break;
            case 'unknown-field': $envelope['extra'] = true;
                break;
        }
        try {
            $this->denies(fn () => SobCustody::open(SobFixture::canonical($envelope), $key), 'authentication/key rejected');
        } finally {
            if (is_string($key)) {
                sodium_memzero($key);
            }
        }
    }

    public function test_encryption_uses_fresh_wrapped_keys_nonces_and_separate_identities(): void
    {
        $a = SobFixture::decode(SobCustody::seal('same synthetic content', ['artifact' => 'fixture'], $this->encryptionPublic));
        $b = SobFixture::decode(SobCustody::seal('same synthetic content', ['artifact' => 'fixture'], $this->encryptionPublic));
        $this->assertNotSame($this->signingPublic, $this->encryptionPublic);
        foreach (['nonce', 'wrapped', 'ciphertext'] as $field) {
            $this->assertNotSame($a[$field], $b[$field]);
        }
        $ka = sodium_crypto_box_seal_open(base64_decode($a['wrapped']), $this->encryptionKey);
        $kb = sodium_crypto_box_seal_open(base64_decode($b['wrapped']), $this->encryptionKey);
        $this->assertNotSame($ka, $kb);
        sodium_memzero($ka);
        sodium_memzero($kb);
    }

    public function test_public_key_encryption_cannot_forge_completed_backup_provenance(): void
    {
        $this->runRequest($this->request());
        $original = SobFixture::decode(SobFixture::read($this->root, 'backup.sob'));
        $forged = SobCustody::seal('forged synthetic content', $original['provenance'], $this->encryptionPublic);
        file_put_contents($this->root.'/backup.sob', $forged);
        $this->denies(fn () => $this->runRequest($this->request('restore_simulation')), 'completion evidence missing');
        $this->assertFileDoesNotExist($this->root.'/restore.sob');
    }

    public static function activeWriters(): array
    {
        return [['web'], ['queue'], ['scheduler'], ['cli'], ['transaction']];
    }

    #[DataProvider('activeWriters')]
    public function test_each_writer_class_blocks_maintenance_and_access(string $kind): void
    {
        $r = $this->request();
        $this->fence->writer($kind, 1);
        $this->denies(fn () => $this->fence->acquire(), 'active writers');
        $this->denies(fn () => $this->runRequest($r), 'fence lost');
        $this->assertSame(0, $this->accesses);
        $this->assertSame(0, $this->register->checkpoint());
    }

    public function test_new_writer_interrupted_maintenance_and_stale_fence_refuse_publication(): void
    {
        $r = $this->request();
        $this->denies(fn () => $this->runRequest($r, function (string $phase): void {
            if ($phase === 'output_reserved') {
                $this->fence->lose();
            }
        }), 'fence lost');
        $this->assertSame('', SobFixture::read($this->root, 'backup.sob'));
        $this->assertSame(1, $this->register->checkpoint());
        $this->denies(fn () => $this->fence->writer('unexpected', 1), 'unexpected writer');
        $this->fence->acquire();
        $this->denies(fn () => $this->fence->assert($this->generation), 'fence lost');
    }

    public static function midrunFailures(): array
    {
        return [['writer'], ['duration'], ['revoked'], ['lease']];
    }

    #[DataProvider('midrunFailures')]
    public function test_fresh_checks_stop_changed_writer_time_revocation_or_lost_lease(string $case): void
    {
        if ($case === 'lease') {
            $lease = new SobWindowsLease($this->root, ['source.sqlite']);
            $lease->close();
            $this->denies(fn () => $lease->alive(), 'lease lost');

            return;
        }
        $r = $this->request();
        $p = SobFixture::decode($r['bytes']);
        $this->denies(fn () => $this->runRequest($r, function (string $phase) use ($case, $p): void {
            if ($phase === 'output_reserved') {
                if ($case === 'writer') {
                    $this->fence->writer('web', 1);
                } elseif ($case === 'duration') {
                    $this->clock['mono'] += 10001;
                } else {
                    $this->status['revoked'][] = $p['id'];
                }
            }
        }), match ($case) {
            'writer' => 'fence lost', 'duration' => 'duration rejected', default => 'authorization rejected'
        });
        $this->assertSame('', SobFixture::read($this->root, 'backup.sob'));
        $this->assertSame(1, $this->register->checkpoint());
    }

    public static function publicationFailures(): array
    {
        return [['backup_simulation', 'encryption_started'], ['backup_simulation', 'output_published'], ['restore_simulation', 'encryption_started'], ['restore_simulation', 'output_published'], ['backup_simulation', 'capacity'], ['backup_simulation', 'io']];
    }

    #[DataProvider('publicationFailures')]
    public function test_incomplete_outputs_never_gain_completion_or_overwrite(string $action, string $phase): void
    {
        if ($action === 'restore_simulation') {
            $this->runRequest($this->request());
        }
        $r = $this->request($action);
        $p = SobFixture::decode($r['bytes']);
        $this->denies(fn () => $this->runRequest($r, static function (string $actual) use ($phase): void {
            if ($phase === $actual) {
                throw new LogicException('SOB injected interruption.');
            }
        }, ['artifact.txt'], $phase === 'capacity' ? 1 : 1048576, $phase === 'io'), match ($phase) {
            'capacity' => 'insufficient capacity', 'io' => 'I/O failure', default => 'injected interruption'
        });
        $output = $action === 'restore_simulation' ? 'restore.sob' : 'backup.sob';
        $this->assertFalse($this->register->completed($p['id'], $p['run'], hash_file('sha256', $this->root.'/'.$output)));
        $this->denies(fn () => $this->runRequest($r), 'consumed');
        $this->denies(fn () => $this->runRequest($this->request($action)), 'never overwrite');
        if ($action === 'backup_simulation' && $phase === 'output_published') {
            $this->denies(fn () => $this->runRequest($this->request('restore_simulation')), 'completion evidence missing');
        }
    }

    public static function sourceFailures(): array
    {
        return [['wal'], ['shm'], ['journal'], ['header'], ['unknown-ledger'], ['busy']];
    }

    #[DataProvider('sourceFailures')]
    public function test_sqlite_journal_lock_and_unknown_completion_refuse_capture(string $case): void
    {
        $lock = null;
        if (in_array($case, ['wal', 'shm', 'journal'], true)) {
            file_put_contents($this->root.'/source.sqlite-'.$case, 'synthetic sidecar');
        } elseif ($case === 'header') {
            file_put_contents($this->root.'/source.sqlite', 'not sqlite');
        } elseif ($case === 'unknown-ledger') {
            $db = new SQLite3($this->root.'/source.sqlite');
            $db->exec("UPDATE fixture_ledger SET state='ddl_committed_ledger_unknown'");
            $db->close();
        }
        $r = $this->request();
        if ($case === 'busy') {
            $lock = new SQLite3($this->root.'/source.sqlite');
            $lock->exec('BEGIN EXCLUSIVE');
        }
        try {
            $this->denies(fn () => $this->runRequest($r), match ($case) {
                'header' => 'header', 'unknown-ledger' => 'unknown migration', 'busy' => 'native open refused: 32', default => 'journal state'
            });
            $this->assertFileDoesNotExist($this->root.'/backup.sob');
        } finally {
            if ($lock !== null) {
                $lock->exec('ROLLBACK');
                $lock->close();
            }
        }
        if ($case === 'busy') {
            $this->assertSame('encrypted_fixture_published', $this->runRequest($this->request())['execution']);
        }
    }

    public function test_inspect_and_migration_simulation_dispatch_no_migration(): void
    {
        $before = hash_file('sha256', $this->root.'/source.sqlite');
        $this->assertSame('inspected', $this->runRequest($this->request('inspect'))['execution']);
        $this->assertSame('disabled', $this->runRequest($this->request('migration_simulation'))['execution']);
        $this->assertSame($before, hash_file('sha256', $this->root.'/source.sqlite'));
        $this->assertFileDoesNotExist($this->root.'/backup.sob');
    }

    private function worker(array $r, string $mode, ?string $phase = null): Process
    {
        $input = ['register' => $this->registerRoot, 'witness' => $this->witnessRoot, 'request' => $r, 'public' => bin2hex($this->signingPublic), 'mode' => $mode,
            'phase' => $phase, 'release' => $this->releaseRoot, 'reviewed' => $this->reviewed, 'encryption_public' => bin2hex($this->encryptionPublic), 'encryption_key' => bin2hex($this->encryptionKey)];

        return new Process([PHP_BINARY, dirname(__DIR__).'/Support/sob-worker.php'], dirname(__DIR__, 2), null, SobFixture::canonical($input), 30);
    }

    public function test_two_subprocess_claims_have_exactly_one_winner(): void
    {
        $r = $this->request();
        $processes = [$this->worker($r, 'claim'), $this->worker($r, 'claim')];
        foreach ($processes as $process) {
            $process->start();
        }
        try {
            $codes = array_map(static fn (Process $process): int => $process->wait(), $processes);
            sort($codes);
            $this->assertSame([0, 2], $codes);
            $this->assertSame(1, substr_count(implode('', array_map(static fn (Process $process): string => $process->getOutput(), $processes)), 'WON'));
            $this->assertSame(1, $this->register->checkpoint());
        } finally {
            foreach ($processes as $process) {
                $process->stop(0);
            }
        }
    }

    public static function crashBoundaries(): array
    {
        return [['backup_simulation', 'claimed_primary'], ['backup_simulation', 'claimed_witnessed'], ['backup_simulation', 'output_reserved'], ['backup_simulation', 'output_published'], ['backup_simulation', 'completed_primary'], ['restore_simulation', 'output_reserved'], ['restore_simulation', 'output_published'], ['restore_simulation', 'completed_primary']];
    }

    #[DataProvider('crashBoundaries')]
    public function test_real_process_termination_keeps_claims_consumed_and_incomplete_results_untrusted(string $action, string $phase): void
    {
        if ($action === 'restore_simulation') {
            $this->runRequest($this->request());
        }
        $r = $this->request($action);
        $process = $this->worker($r, 'run', $phase);
        $process->start();
        try {
            $deadline = microtime(true) + 25;
            while (! str_contains($process->getOutput(), 'READY') && $process->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
        } finally {
            $process->stop(0);
        }
        $this->assertFalse($process->isRunning());
        $this->denies(fn () => $this->runRequest($r), str_ends_with($phase, '_primary') ? 'witness conflict' : 'consumed');
        if (in_array($phase, ['output_reserved', 'output_published'], true)) {
            $this->denies(fn () => $this->runRequest($this->request($action)), 'never overwrite');
        }
        if ($action === 'backup_simulation' && $phase === 'output_published') {
            $this->denies(fn () => $this->runRequest($this->request('restore_simulation')), 'completion evidence missing');
        }
    }
}
