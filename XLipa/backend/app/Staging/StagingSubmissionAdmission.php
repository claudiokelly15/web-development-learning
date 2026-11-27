<?php

namespace App\Staging;

use App\Actions\Merchants\SubmissionAdmission;
use Illuminate\Support\Facades\DB;

final class StagingSubmissionAdmission implements SubmissionAdmission
{
    public function authorized(int $actor, string $merchant, array $expectations): bool
    {
        try {
            $c = StagingWorkflowAuthority::current();

            return $c['actor'] === $actor && $c['merchant'] === $merchant && in_array($c['action'], ['onboarding.submit', 'onboarding.submit.resolve'], true) && $c['expected'] === $expectations;
        } catch (\Throwable) {
            return false;
        }
    }

    public function restoreReady(): bool
    {
        return StagingWorkflowAuthority::web();
    }

    public function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function retentionPolicy(): string
    {
        return 'fictional-staging-v1';
    }

    public function retentionAllowed(string $policy, int $submittedAt, ?int $identityDueAt): bool
    {
        return $policy === $this->retentionPolicy() && $submittedAt <= $this->milliseconds() && ($identityDueAt === null || $identityDueAt > $submittedAt);
    }

    public function trusted(array $proof, array $challenge, array $intent, array $identity, int $submittedAt): bool
    {
        try {
            StagingIdentity::check(StagingWorkflowAuthority::provenance($proof, $challenge, $intent));
            $context = app('staging.context');
            $envelope = StagingIdentity::read($context['root'].'/proof-'.$proof['id'].'.json');
            $c = StagingIdentity::verified($envelope, $context['public']);
            $version = DB::table('users')->where('id', $proof['actor_user_id'])->value('auth_version');
            (new StagingCapability($context))->authorize($envelope, $proof['actor_user_id'], $proof['merchant_id'], 'onboarding.proof.attest', $proof['id'], $version);

            return hash_equals($c['lineage'], StagingWorkflowAuthority::lineage($proof, $challenge, $intent, $identity));
        } catch (\Throwable) {
            return false;
        }
    }
}
