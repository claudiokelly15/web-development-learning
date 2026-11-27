<?php

namespace App\Staging;

use App\Actions\Merchants\BusinessNameCompletionSchema;
use App\Actions\Merchants\ComposedSubmissionSchema;
use App\Actions\Merchants\SubmissionEvidenceSchema;
use App\Actions\Merchants\SubmissionOutcomeUnknown;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Custodian-only composition; the frozen rehearsal installers remain inaccessible to web boot. */
final class WorkflowProvisioner
{
    public const VERSION = 'xlipa-onboarding-fictional-v1';

    public static function provision(string $runtime): void
    {
        StagingIdentity::check(PHP_SAPI === 'cli');
        self::compose(StagingIdentity::workflowProvisioning($runtime));
    }

    private static function compose(array $context, ?Closure $checkpoint = null): void
    {
        StagingIdentity::check(PHP_SAPI === 'cli' && app()->bound('staging.context'));
        StagingIdentity::check(DB::connection()->getDatabaseName() === $context['root'].'/workflow.sqlite');
        StagingIdentity::check(app('staging.context')['runtime'] === $context['runtime']);
        StagingIdentity::check(app(WorkflowSchemaReadiness::class)->profile() === WorkflowSchemaReadiness::FOUNDATION);
        ComposedSubmissionSchema::assertSources();
        $root = StagingIdentity::path($context['root']);
        $claim = fopen($root.'/workflow-provision.started', 'x');
        StagingIdentity::check($claim !== false);
        try {
            $body = StagingIdentity::encode(['profile' => self::VERSION, 'runtime' => $context['runtime'], 'generation' => $context['generation'], 'store' => $context['store'], 'release' => $context['release']]);
            StagingIdentity::check(fwrite($claim, $body) === strlen($body) && fflush($claim) && fsync($claim));
        } finally {
            fclose($claim);
        }
        $pdo = DB::connection()->getPdo();
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON');
        $pdo->exec('BEGIN IMMEDIATE');
        $committing = false;
        $fictional = ($context['provision_profile'] ?? null) === self::VERSION;
        try {
            $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
            $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='audit_logs' AND sql IS NOT NULL");
            StagingIdentity::check(DB::table('audit_logs')->count() === 0);
            $pdo->exec('DROP TABLE audit_logs');
            $pdo->exec($contract['audit']['sql']);
            foreach ($indexes as $index) {
                $pdo->exec($index->sql);
            }
            foreach (['create_tables', 'indexes', 'triggers'] as $group) {
                foreach ($contract[$group] as $sql) {
                    $pdo->exec($sql);
                }
            }
            DB::table('migrations')->insert(['migration' => '2026_11_07_200000_activate_b32_security_contract', 'batch' => 3]);
            $checkpoint && $checkpoint('b32c');
            $pdo->exec("ALTER TABLE users ADD COLUMN email_binding_version INTEGER NOT NULL DEFAULT 1 CHECK(typeof(email_binding_version)='integer' AND email_binding_version>=1)");
            $pdo->exec("ALTER TABLE merchant_memberships ADD COLUMN authority_version INTEGER NOT NULL DEFAULT 1 CHECK(typeof(authority_version)='integer' AND authority_version>=1)");
            foreach (SubmissionEvidenceSchema::objects($fictional) as $sql) {
                $pdo->exec($sql);
            }
            DB::table('migrations')->insert(['migration' => SubmissionEvidenceSchema::MIGRATION, 'batch' => 4]);
            $checkpoint && $checkpoint('se1');
            foreach (BusinessNameCompletionSchema::objects() as $sql) {
                $pdo->exec($sql);
            }
            DB::table('migrations')->insert(['migration' => BusinessNameCompletionSchema::MIGRATION, 'batch' => 5]);
            $checkpoint && $checkpoint('bnc1');
            config(['workflow_schema.expected_profile' => WorkflowSchemaReadiness::BNC]);
            ComposedSubmissionSchema::assertReady($fictional);
            StagingIdentity::check($pdo->query('PRAGMA quick_check')->fetchColumn() === 'ok');
            $committing = true;
            $pdo->exec('COMMIT');
        } catch (Throwable $error) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable) {
                $committing = true;
            }
            file_put_contents($root.'/workflow-provision.failed', 'Retire generation; preserve evidence.');
            config(['workflow_schema.expected_profile' => WorkflowSchemaReadiness::FOUNDATION]);
            if ($committing) {
                throw new SubmissionOutcomeUnknown;
            }
            throw $error;
        }
    }
}
