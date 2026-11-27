<?php

namespace App\Staging;

use LogicException;
use PDO;

/** Local OS custodian is the trust root. Never imported by operational bootstrap. */
final class StagingIdentity
{
    public const ORIGIN = 'http://127.0.0.2:18762';

    public static function check(bool $condition): void
    {
        if (! $condition) {
            throw new LogicException('Disposable staging admission denied.');
        }
    }

    public static function anchor(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3)).'/scripts/.staging';
    }

    /** Reject aliases before reading a marker, key or database. Existing paths only. */
    public static function path(string $path): string
    {
        self::check(! str_contains($path, "\0") && ! str_contains($path, '\\') && ! str_contains($path, '//'));
        self::check((bool) preg_match('~^(?:[A-Z]:/|/)[^:]+$~iD', $path));
        foreach (explode('/', preg_replace('~^[A-Z]:/~i', '', $path)) as $part) {
            self::check($part !== '.' && $part !== '..' && ! preg_match('/[. ]$/', $part));
            self::check(! preg_match('/^(CON|PRN|AUX|NUL|COM[0-9]|LPT[0-9])(?:\.|$)/i', $part));
        }
        $cursor = $path;
        while (strlen($cursor) > 3) {
            clearstatcache(true, $cursor);
            self::check(! is_link($cursor));
            $resolved = realpath($cursor);
            self::check($resolved !== false && strcasecmp(str_replace('\\', '/', $resolved), $cursor) === 0);
            $stat = lstat($cursor);
            self::check($stat !== false && ($stat['nlink'] ?? 1) <= 1);
            $cursor = str_replace('\\', '/', dirname($cursor));
        }

        return $path;
    }

    public static function read(string $path): array
    {
        self::path($path);
        self::check(is_file($path) && filesize($path) < 1048576);

        return json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    }

    public static function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function signed(array $value, #[\SensitiveParameter] string $secret): array
    {
        return ['value' => $value, 'signature' => base64_encode(sodium_crypto_sign_detached(self::encode($value), $secret))];
    }

    public static function verified(array $envelope, string $public): array
    {
        $signature = base64_decode($envelope['signature'] ?? '', true);
        self::check(is_string($signature) && strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES);
        self::check(sodium_crypto_sign_verify_detached($signature, self::encode($envelope['value']), $public));

        return $envelope['value'];
    }

    /** Source-only release identity: no environment, store, vendor or generated data. */
    public static function release(): string
    {
        $base = str_replace('\\', '/', dirname(__DIR__, 2));
        $hashes = [];
        foreach (['app', 'bootstrap', 'config', 'routes', 'database/migrations'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base.'/'.$directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $name = str_replace('\\', '/', $file->getPathname());
                if ($file->getExtension() === 'php' && ! str_contains($name, '/bootstrap/cache/')) {
                    $hashes[substr($name, strlen($base) + 1)] = hash_file('sha256', $name);
                }
            }
        }
        foreach (['staging-router.php', 'staging-control.php', 'financial-control.php'] as $file) {
            $hashes[$file] = hash_file('sha256', dirname($base).'/scripts/'.$file);
        }
        $hashes['financial-schema-v1.json'] = hash_file('sha256', __DIR__.'/financial-schema-v1.json');
        ksort($hashes);

        return hash('sha256', self::encode($hashes));
    }

    /** No PDO is created until signature, origin, release and all fixed targets pass. */
    public static function admit(string $id, string $sapi, string $host, string $peer): array
    {
        self::check($sapi === 'cli-server' && $host === '127.0.0.2:18762' && in_array($peer, ['127.0.0.1', '127.0.0.2'], true));

        return self::load($id);
    }

    /** Custodian tools verify the same target without claiming to execute web requests. */
    public static function load(string $id): array
    {
        $context = self::target($id, 'manifest.json');
        self::assertStores($context);

        return $context;
    }

    public static function provisioning(string $id): array
    {
        $context = self::target($id, 'provisioning.json');
        self::check($context['purpose'] === 'financial-schema-provision' && $context['financial_profile'] === FinancialSchema::VERSION);

        return $context;
    }

    /** Retirement authenticates ownership, not readiness; it cannot grant runtime access. */
    public static function retirement(string $id): array
    {
        return self::target($id, 'provisioning.json', true);
    }

    public static function workflowProvisioning(string $id): array
    {
        $context = self::target($id, 'workflow-provisioning.json');
        self::check($context['purpose'] === 'workflow-schema-provision' && $context['provision_profile'] === WorkflowProvisioner::VERSION);
        self::assertStores($context);

        return $context;
    }

    public static function workflowAuthority(string $id): array
    {
        $context = self::target($id, PHP_SAPI === 'cli' ? 'workflow-provisioning.json' : 'manifest.json');
        self::check(($context['workflow_profile'] ?? $context['provision_profile'] ?? null) === WorkflowProvisioner::VERSION);
        self::check(PHP_SAPI === 'cli' ? ($context['purpose'] ?? null) === 'workflow-schema-provision' : PHP_SAPI === 'cli-server');

        return $context;
    }

    private static function target(string $id, string $document, bool $retiring = false): array
    {
        self::check((bool) preg_match('/^run-[a-f0-9]{32}$/D', $id));
        $anchor = self::path(self::anchor());
        $root = self::path($anchor.'/'.$id);
        self::check($retiring || (! file_exists($root.'/retired') && ! file_exists($root.'/financial-provision.failed') && ! file_exists($root.'/workflow-provision.failed')));
        $trust = self::read($anchor.'/issuer-public.json');
        $public = base64_decode($trust['public'], true);
        self::check(is_string($public) && strlen($public) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
        $manifest = self::verified(self::read($root.'/'.$document), $public);
        self::check($manifest['namespace'] === 'xlipa-fictional-r21' && $manifest['issuer'] === $trust['issuer']);
        self::check($manifest['runtime'] === $id && $manifest['origin'] === self::ORIGIN && $manifest['profile'] === 'foundation');
        self::check($manifest['root'] === $root && $manifest['generation'] === $id);
        if (! $retiring) {
            self::check($manifest['issued'] <= time() && $manifest['expires'] > time() && $manifest['expires'] - $manifest['issued'] <= 28800);
            self::check(hash_equals($manifest['release'], self::release()));
        }
        foreach (['workflow.sqlite', 'financial.sqlite', 'admission.sqlite', 'owner.json', 'app-key.json', 'storage', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache'] as $target) {
            self::path($root.'/'.$target);
        }
        self::check(self::read($root.'/owner.json') === ['runtime' => $id, 'generation' => $id, 'namespace' => 'xlipa-fictional-r21']);
        $context = [...$manifest, 'public' => $public];

        return $context;
    }

    public static function schema(PDO $pdo): string
    {
        return hash('sha256', self::encode($pdo->query('SELECT type,name,tbl_name,sql FROM sqlite_master WHERE sql IS NOT NULL ORDER BY type,name')->fetchAll(PDO::FETCH_ASSOC)));
    }

    public static function assertStores(array $context): void
    {
        foreach (['workflow', 'financial', 'admission'] as $kind) {
            $path = self::path($context['root'].'/'.$kind.'.sqlite');
            $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON');
            self::check((int) $pdo->query('PRAGMA application_id')->fetchColumn() === $context['headers'][$kind]['application']);
            self::check((int) $pdo->query('PRAGMA user_version')->fetchColumn() === $context['headers'][$kind]['version']);
            self::check(hash_equals($context['schemas'][$kind], self::schema($pdo)));
            if ($kind !== 'workflow') {
                $identity = $pdo->query('SELECT runtime,generation,profile FROM staging_identity')->fetch(PDO::FETCH_ASSOC);
                self::check($identity === ['runtime' => $context['runtime'], 'generation' => $context['generation'], 'profile' => $kind === 'financial' ? ($context['financial_profile'] ?? 'unprovisioned-r22') : 'admission-r21']);
            }
            if ($kind === 'financial' && ($context['financial_profile'] ?? null) === FinancialSchema::VERSION) {
                FinancialSchema::assertReady($pdo, $context);
            }
        }
    }
}
