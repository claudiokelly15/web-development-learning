<?php

namespace Tests\Feature;

use App\Actions\Merchants\ComposedSubmissionSchema;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use App\Staging\StagingBusinessNameAdmission;
use App\Staging\StagingIdentity;
use App\Staging\WorkflowProvisioner;
use Illuminate\Support\Facades\DB;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

class StagingWorkflowSchemaTest extends TestCase
{
    private string $root;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        $this->root = str_replace('\\', '/', sys_get_temp_dir()).'/xlipa-r23-'.bin2hex(random_bytes(8));
        mkdir($this->root);
        DB::statement("VACUUM INTO '".$this->root."/workflow.sqlite'");
        DB::purge();
        config(['database.connections.sqlite.database' => $this->root.'/workflow.sqlite', 'workflow_schema.expected_profile' => 'foundation']);
        DB::statement('PRAGMA recursive_triggers=ON');
        $this->context = ['root' => $this->root, 'runtime' => 'fixture', 'generation' => 'fixture', 'release' => 'fixture', 'store' => 'fixture'];
        app()->instance('staging.context', $this->context);
    }

    protected function tearDown(): void
    {
        DB::purge();
        foreach (['workflow.sqlite', 'workflow.sqlite-journal', 'workflow-provision.started', 'workflow-provision.failed'] as $file) {
            if (is_file($this->root.'/'.$file)) {
                unlink($this->root.'/'.$file);
            }
        }
        rmdir($this->root);
        parent::tearDown();
    }

    public function test_exact_composition_uses_existing_catalogue_and_migration_ledger(): void
    {
        $this->assertSame('foundation', app(WorkflowSchemaReadiness::class)->profile());
        $this->compose($this->context);
        ComposedSubmissionSchema::assertReady();
        $this->assertSame(ComposedSubmissionSchema::ledger(), array_map(fn ($row): array => (array) $row, DB::select('SELECT id,migration,batch FROM migrations ORDER BY id')));
        $this->assertSame('b32c+se1+bnc1', app(WorkflowSchemaReadiness::class)->profile());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertFileExists($this->root.'/workflow-provision.started');
    }

    public function test_interrupted_composition_rolls_back_and_keeps_failure_evidence(): void
    {
        $before = StagingIdentity::schema(DB::connection()->getPdo());
        try {
            $this->compose($this->context, function (string $stage): void {
                if ($stage === 'se1') {
                    throw new \RuntimeException('fictional interruption');
                }
            });
            $this->fail('Interrupted composition succeeded');
        } catch (\RuntimeException) {
            $this->assertSame($before, StagingIdentity::schema(DB::connection()->getPdo()));
            $this->assertSame('foundation', app(WorkflowSchemaReadiness::class)->profile());
            $this->assertFileExists($this->root.'/workflow-provision.failed');
        }
    }

    public function test_incorrect_connection_is_rejected_before_a_claim_or_ddl(): void
    {
        try {
            $this->compose([...$this->context, 'root' => $this->root.'/wrong']);
            $this->fail('Foreign target accepted');
        } catch (\LogicException) {
            $this->assertFileDoesNotExist($this->root.'/workflow-provision.started');
            $this->assertSame('foundation', app(WorkflowSchemaReadiness::class)->profile());
        }
    }

    public function test_existing_claim_prevents_an_interrupted_generation_from_being_reinitialized(): void
    {
        file_put_contents($this->root.'/workflow-provision.started', 'interrupted');
        $before = hash_file('sha256', $this->root.'/workflow.sqlite');
        $denied = false;
        try {
            $this->compose($this->context);
        } catch (\Throwable $error) {
            $denied = true;
            $this->assertSame($before, hash_file('sha256', $this->root.'/workflow.sqlite'));
            $this->assertSame('interrupted', file_get_contents($this->root.'/workflow-provision.started'));
        }
        $this->assertTrue($denied, 'Interrupted generation must not be reused');
    }

    public function test_ordinary_cli_cannot_impersonate_the_signed_web_command_authority(): void
    {
        $this->assertFalse(app(StagingBusinessNameAdmission::class)->authorized(1, 'foreign-merchant', [], false));
        $this->expectException(\LogicException::class);
        WorkflowProvisioner::provision('../outside-staging');
    }

    private function compose(array $context, ?\Closure $checkpoint = null): void
    {
        (new \ReflectionMethod(WorkflowProvisioner::class, 'compose'))->invoke(null, $context, $checkpoint);
    }
}
