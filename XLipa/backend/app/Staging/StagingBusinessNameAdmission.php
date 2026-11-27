<?php

namespace App\Staging;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use Illuminate\Database\Connection;

/** Exact custodian-signed command, never a browser-created authority tuple. */
final class StagingBusinessNameAdmission implements BusinessNameCompletionAdmission
{
    public function expectedProfile(): ?string
    {
        return WorkflowSchemaReadiness::BNC;
    }

    public function authorized(int $actor, string $merchant, array $expected, bool $resolving): bool
    {
        try {
            $context = app('staging.context');
            StagingIdentity::check(PHP_SAPI === 'cli-server' && ! app()->runningInConsole() && ! app()->runningUnitTests());
            StagingIdentity::load($context['runtime']);
            $command = request()->attributes->get('staging.name.command');
            StagingIdentity::check(is_array($command) && $command['value']['expected'] === $expected);
            $action = $resolving ? 'onboarding.name.resolve' : 'onboarding.name';
            (new StagingCapability($context))->authorize($command, $actor, $merchant, $action, request()->getPathInfo(), auth()->user()->auth_version, $command['value']['command'], false);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function recordPolicy(): string
    {
        return 'fictional-staging-redacted-v1';
    }

    public function retentionAllowed(string $policy, int $occurredAt): bool
    {
        return $policy === $this->recordPolicy() && $occurredAt <= $this->milliseconds();
    }

    public function authoritative(Connection $connection, int $actor, string $merchant, array $expected, bool $resolving): bool
    {
        return $connection->getDatabaseName() === app('staging.context')['root'].'/workflow.sqlite'
            && $this->authorized($actor, $merchant, $expected, $resolving);
    }

    public function reconciliationReady(int $actor, string $merchant): bool
    {
        return true;
    }
}
