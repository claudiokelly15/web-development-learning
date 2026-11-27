<?php

namespace Tests\Feature;

use App\Actions\Merchants\ComposedSubmissionSchema;
use App\Actions\Merchants\SubmissionEvidenceSchema;
use App\Staging\StagingCapability;
use App\Staging\StagingCommandState;
use App\Staging\StagingIdentity;
use App\Staging\StagingWorkflowAuthority;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StagingFictionalEvidenceTest extends TestCase
{
    public function test_live_schema_objects_remain_byte_identical_to_the_reviewed_pre_amendment_source(): void
    {
        $original = json_decode(file_get_contents(base_path('tests/Support/submission-evidence-live-v1-hashes.json')), true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame($original, array_map(fn (string $sql): string => hash('sha256', $sql), SubmissionEvidenceSchema::objects()));
        $fictional = SubmissionEvidenceSchema::objects(true);
        $changed = [];
        foreach (SubmissionEvidenceSchema::objects() as $name => $sql) {
            if ($fictional[$name] !== $sql) {
                $changed[] = $name;
                $this->assertStringContainsString("p.provenance='simulated'", $fictional[$name]);
                $this->assertStringContainsString("h.transport_mode='simulated'", $fictional[$name]);
                $this->assertStringContainsString("d.transport_mode='simulated'", $fictional[$name]);
                $this->assertStringNotContainsString("provenance='live'", $fictional[$name]);
            }
        }
        $this->assertSame(['se1_evidence_insert'], $changed);
    }

    public function test_environment_request_and_configuration_labels_cannot_authorize_fictional_evidence(): void
    {
        app()->instance('env', 'staging');
        config(['workflow_schema.expected_profile' => 'b32c+se1+bnc1', 'staging.authorized' => true]);
        app()->instance('staging.context', ['runtime' => 'forged', 'root' => '/fictional', 'workflow_profile' => 'xlipa-onboarding-fictional-v1']);
        $this->assertFalse(StagingWorkflowAuthority::schemaAuthorized());
        $this->assertFalse(StagingWorkflowAuthority::modeAllowed('simulated'));
        $this->assertFalse(StagingWorkflowAuthority::modeAllowed('live'));
        $this->expectException(\LogicException::class);
        ComposedSubmissionSchema::assertReady(true);
    }

    public static function corrupt(): array
    {
        return [
            [['runtime' => 'stale']], [['release' => 'foreign']], [['store' => 'foreign']], [['generation' => 'stale']],
            [['actor' => 8]], [['merchant' => 'foreign']], [['resource' => '/foreign']], [['version' => 4]],
            [['action' => 'ledger.post']], [['expires' => 1]], [['issued' => PHP_INT_MAX]],
        ];
    }

    #[DataProvider('corrupt')]
    public function test_signed_workflow_commands_reject_foreign_scope_and_stale_epochs(array $changes): void
    {
        $this->capabilityFixture(function (array $context, string $secret) use ($changes): void {
            $envelope = $this->grant($context, $secret, $changes);
            $this->expectException(\LogicException::class);
            (new StagingCapability($context))->authorize($envelope, 7, 'merchant', 'onboarding.submit', '/submit', 3, str_repeat('a', 32));
        });
    }

    public function test_workflow_command_is_claimed_once_and_rechecks_revocation_during_service_execution(): void
    {
        $this->capabilityFixture(function (array $context, string $secret): void {
            $envelope = $this->grant($context, $secret);
            $service = new StagingCapability($context);
            $service->authorize($envelope, 7, 'merchant', 'onboarding.submit', '/submit', 3, str_repeat('a', 32));
            $service->authorize($envelope, 7, 'merchant', 'onboarding.submit', '/submit', 3, str_repeat('a', 32), false);
            $this->addToAssertionCount(1);
            try {
                $service->authorize($envelope, 7, 'merchant', 'onboarding.submit', '/submit', 3, str_repeat('a', 32));
                $this->fail('Command replay accepted');
            } catch (\PDOException) {
                $this->addToAssertionCount(1);
            }
            $pdo = new PDO('sqlite:'.$context['root'].'/admission.sqlite');
            $pdo->prepare('INSERT INTO revoked VALUES(?)')->execute([str_repeat('b', 32)]);
            unset($pdo);
            $this->expectException(\LogicException::class);
            $service->authorize($envelope, 7, 'merchant', 'onboarding.submit', '/submit', 3, str_repeat('a', 32), false);
        });
    }

    public function test_forged_signature_cannot_issue_workflow_authority(): void
    {
        $this->capabilityFixture(function (array $context, string $secret): void {
            $envelope = $this->grant($context, $secret);
            $envelope['value']['merchant'] = 'foreign';
            $this->expectException(\LogicException::class);
            (new StagingCapability($context))->authorize($envelope, 7, 'foreign', 'onboarding.submit', '/submit', 3, str_repeat('a', 32));
        });
    }

    private function grant(array $context, string $secret, array $changes = []): array
    {
        return StagingIdentity::signed(array_replace(array_intersect_key($context, array_flip(['namespace', 'issuer', 'runtime', 'release', 'store', 'generation'])), [
            'id' => str_repeat('b', 32), 'actor' => 7, 'merchant' => 'merchant', 'action' => 'onboarding.submit', 'resource' => '/submit', 'version' => 3,
            'command' => str_repeat('a', 32), 'issued' => time() - 1, 'expires' => time() + 60,
        ], $changes), $secret);
    }

    public function test_lost_acknowledgement_or_process_interruption_cannot_authorize_a_blind_retry(): void
    {
        $this->capabilityFixture(function (array $context, string $secret): void {
            app()->instance('staging.context', $context);
            $c = $this->grant($context, $secret)['value'];
            StagingCommandState::begin($c);
            $denied = false;
            try {
                StagingCommandState::assertNotUnresolved($c);
            } catch (\LogicException) {
                $denied = true;
            }
            $this->assertTrue($denied, 'A durable dispatch with no acknowledgement must be quarantined');
            StagingCommandState::finish($c, 'unresolved');
            $this->expectException(\LogicException::class);
            StagingCommandState::assertNotUnresolved($c);
        });
    }

    public function test_a_known_rejection_does_not_masquerade_as_an_uncertain_commit(): void
    {
        $this->capabilityFixture(function (array $context, string $secret): void {
            app()->instance('staging.context', $context);
            $c = $this->grant($context, $secret)['value'];
            StagingCommandState::begin($c);
            StagingCommandState::finish($c, 'rejected');
            StagingCommandState::assertNotUnresolved($c);
            $this->addToAssertionCount(1);
        });
    }

    private function capabilityFixture(\Closure $test): void
    {
        $root = str_replace('\\', '/', sys_get_temp_dir()).'/xlipa-r23-cap-'.bin2hex(random_bytes(8));
        mkdir($root);
        $pdo = new PDO('sqlite:'.$root.'/admission.sqlite');
        $pdo->exec('CREATE TABLE revoked(id TEXT PRIMARY KEY); CREATE TABLE commands(id TEXT PRIMARY KEY,capability TEXT); CREATE TABLE clock(observed INTEGER)');
        $pdo->prepare('INSERT INTO clock VALUES(?)')->execute([time()]);
        $pair = sodium_crypto_sign_keypair();
        $context = ['root' => $root, 'namespace' => 'fictional', 'issuer' => 'local', 'runtime' => 'runtime', 'release' => 'release', 'store' => 'store', 'generation' => 'generation', 'public' => sodium_crypto_sign_publickey($pair), 'expires' => time() + 300];
        try {
            $test($context, sodium_crypto_sign_secretkey($pair));
        } finally {
            unset($pdo);
            unlink($root.'/admission.sqlite');
            foreach (['claimed', 'outcome'] as $suffix) {
                $file = $root.'/workflow-command-'.str_repeat('a', 32).'.'.$suffix;
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($root);
        }
    }
}
