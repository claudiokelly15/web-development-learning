<?php

namespace App\Staging;

use App\Actions\Merchants\SubmissionEvidenceEnvironment;

final class StagingEvidenceEnvironment extends SubmissionEvidenceEnvironment
{
    public function authorized(): bool
    {
        return StagingWorkflowAuthority::modeAllowed('simulated');
    }

    public function namespace(): string
    {
        return 'xlipa-fictional-r23';
    }

    public function restoreReady(): bool
    {
        return $this->authorized();
    }

    public function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function retentionAllowed(string $policy, string $use, int $submittedAt): bool
    {
        return $this->authorized() && $policy === 'fictional-staging-v1' && $submittedAt <= $this->milliseconds() && in_array($use, ['encrypt', 'decrypt', 'bind', 'verify'], true);
    }

    public function keyId(string $domain): string
    {
        return 'fictional.'.$domain.'.v1';
    }

    public function key(string $domain, string $id, string $use): string
    {
        StagingIdentity::check($this->authorized() && $id === $this->keyId($domain));
        StagingIdentity::check(in_array($domain, ['evidence', 'binding'], true));

        return base64_decode(StagingIdentity::read(app('staging.context')['root'].'/onboarding-keys.json')[$domain], true);
    }
}
