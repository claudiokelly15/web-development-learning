<?php

namespace App\Staging;

use App\Actions\Merchants\ApplicationDecisionAdmission;
use Illuminate\Support\Facades\DB;

final class StagingDecisionAdmission extends ApplicationDecisionAdmission
{
    public function authorized(int $actor, string $merchant, string $application, array $expected, string $outcome, ?string $reason): bool
    {
        try {
            $c = StagingWorkflowAuthority::current();
            StagingIdentity::check($c['actor'] === $actor && $c['merchant'] === $merchant && $c['application'] === $application && $c['expected'] === $expected && $c['outcome'] === $outcome && $c['reason'] === $reason);
            $context = app('staging.context');
            $envelope = StagingIdentity::read($context['root'].'/chief-assurance-'.$actor.'.json');
            $assurance = StagingIdentity::verified($envelope, $context['public']);
            (new StagingCapability($context))->authorize($envelope, $actor, $merchant, 'onboarding.chief.assurance', $application, auth()->user()->auth_version);
            $role = (array) DB::table('platform_role_assignments')->where('user_id', $actor)->where('role', 'chief_admin')->whereNull('revoked_at')->sole();

            return $assurance['command_id'] === $c['command'] && $assurance['authority'] === hash('sha256', StagingIdentity::encode($role));
        } catch (\Throwable) {
            return false;
        }
    }

    public function authorityVersion(int $actor): ?int
    {
        return DB::table('platform_role_assignments')->where('user_id', $actor)->where('role', 'chief_admin')->whereNull('revoked_at')->value('user_id');
    }

    public function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
