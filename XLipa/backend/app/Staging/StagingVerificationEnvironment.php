<?php

namespace App\Staging;

use App\Actions\Merchants\VerificationEnvironment;

final class StagingVerificationEnvironment extends VerificationEnvironment
{
    public function authorized(): bool
    {
        return StagingWorkflowAuthority::modeAllowed('simulated');
    }

    public function namespace(): string
    {
        return 'xlipa-fictional-r23';
    }

    public function mode(): string
    {
        return 'simulated';
    }

    public function keyId(string $domain): string
    {
        StagingIdentity::check(in_array($domain, ['phone', 'nin', 'otp', 'contact'], true));

        return 'fictional.'.$domain.'.v1';
    }

    public function key(string $domain, string $id): string
    {
        StagingIdentity::check($this->authorized() && $id === $this->keyId($domain));

        return base64_decode(StagingIdentity::read(app('staging.context')['root'].'/onboarding-keys.json')[$domain], true);
    }

    public function identityPolicy(): ?array
    {
        return ['syntax' => 'fictional-r23-v1', 'declaration' => 'fictional-declaration-v1', 'notice' => 'fictional-notice-v1', 'text' => 'Fictional local exercise only. Never enter a real identity. No NIDA verification or legal collection authority.'];
    }

    public function normalizeIdentity(#[\SensitiveParameter] string $value): string
    {
        StagingIdentity::check((bool) preg_match('/^FICTIONAL-[A-Z]{8,32}$/D', $value));

        return $value;
    }

    public function reserveDeliveryBudget(string $targetFingerprint, string $requestId, string $ip): bool
    {
        return $this->authorized() && in_array($ip, ['127.0.0.1', '127.0.0.2'], true);
    }

    public function deliver(#[\SensitiveParameter] string $phone, #[\SensitiveParameter] string $code, string $requestId): string
    {
        StagingIdentity::check($this->authorized() && $phone === '+255600000000');
        StagingIdentity::check((bool) preg_match('/^[a-f0-9-]{36}$/D', $requestId));
        $path = app('staging.context')['root'].'/otp-'.$requestId.'.json';
        $handle = fopen($path, 'x');
        StagingIdentity::check($handle !== false);
        try {
            StagingIdentity::check(fwrite($handle, StagingIdentity::encode(['code' => $code, 'actor' => auth()->id(), 'request' => $requestId])) !== false && fflush($handle));
        } finally {
            fclose($handle);
        }

        return 'accepted';
    }
}
