<?php

namespace App\Staging;

use PDO;
use Throwable;

/** Custodian-only file provisioning. Never registered in the application container. */
final class FinancialProvisioner
{
    public static function provision(string $id): array
    {
        StagingIdentity::check(PHP_SAPI === 'cli');
        $context = StagingIdentity::provisioning($id);

        return self::initialize($context);
    }

    /** Reached only after signed target verification; directly fault-tested on owned fixtures. */
    private static function initialize(array $context): array
    {
        $root = $context['root'];
        $lockPath = $root.'/financial-provision.lock';
        if (file_exists($lockPath)) {
            StagingIdentity::path($lockPath);
        }
        $lock = fopen($lockPath, 'c');
        StagingIdentity::check($lock !== false);
        try {
            StagingIdentity::check(flock($lock, LOCK_EX | LOCK_NB));
            // Header and non-financial identities are independently rechecked before DDL.
            foreach (['workflow', 'admission', 'financial'] as $kind) {
                $pdo = new PDO('sqlite:'.StagingIdentity::path($root.'/'.$kind.'.sqlite'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                StagingIdentity::check((int) $pdo->query('PRAGMA application_id')->fetchColumn() === $context['headers'][$kind]['application']);
                StagingIdentity::check((int) $pdo->query('PRAGMA user_version')->fetchColumn() === $context['headers'][$kind]['version']);
                if ($kind !== 'financial') {
                    StagingIdentity::check(hash_equals($context['schemas'][$kind], StagingIdentity::schema($pdo)));
                }
            }
            $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON; PRAGMA busy_timeout=2000');
            $claim = $root.'/financial-provision.started';
            if (file_exists($claim)) {
                StagingIdentity::check(StagingIdentity::read($claim) === self::claim($context));

                // Only a fully committed, independently verified result is a safe no-op.
                return [...FinancialSchema::assertReady($pdo, $context), 'repeated' => true];
            }
            FinancialSchema::blank($pdo, $context);
            $intent = fopen($claim, 'x');
            StagingIdentity::check($intent !== false);
            try {
                $body = StagingIdentity::encode(self::claim($context));
                StagingIdentity::check(fwrite($intent, $body) === strlen($body) && fflush($intent) && fsync($intent));
            } finally {
                fclose($intent);
            }
            try {
                FinancialSchema::create($pdo, $context);

                return [...FinancialSchema::assertReady($pdo, $context), 'repeated' => false];
            } catch (Throwable $error) {
                file_put_contents($root.'/financial-provision.failed', 'Unavailable; retain evidence and retire this generation.', LOCK_EX);
                throw $error;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function claim(array $context): array
    {
        return array_intersect_key($context, array_flip(['namespace', 'runtime', 'generation', 'store', 'release', 'financial_profile']));
    }
}
