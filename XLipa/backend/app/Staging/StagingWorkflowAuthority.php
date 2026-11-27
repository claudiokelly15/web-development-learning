<?php

namespace App\Staging;

use Illuminate\Support\Facades\DB;

/** Independent signed local custody; labels, roles and request inputs cannot select this profile. */
final class StagingWorkflowAuthority
{
    public static function schemaAuthorized(): bool
    {
        try {
            StagingIdentity::check(app()->bound('staging.context'));
            $context = app('staging.context');
            $verified = StagingIdentity::workflowAuthority($context['runtime']);
            foreach (['root', 'runtime', 'store', 'release', 'generation'] as $field) {
                StagingIdentity::check($context[$field] === $verified[$field]);
            }
            StagingIdentity::check(DB::connection()->getDatabaseName() === $verified['root'].'/workflow.sqlite');
            StagingIdentity::check((int) DB::selectOne('PRAGMA application_id')->application_id === $verified['headers']['workflow']['application']);
            StagingIdentity::check((int) DB::selectOne('PRAGMA user_version')->user_version === $verified['headers']['workflow']['version']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function web(): bool
    {
        return PHP_SAPI === 'cli-server' && ! app()->runningInConsole() && ! app()->runningUnitTests() && self::schemaAuthorized();
    }

    public static function current(): array
    {
        StagingIdentity::check(self::web());
        $envelope = request()->attributes->get('staging.workflow.command');
        StagingIdentity::check(is_array($envelope));
        $c = StagingIdentity::verified($envelope, app('staging.context')['public']);
        (new StagingCapability(app('staging.context')))->authorize($envelope, auth()->id(), $c['merchant'], $c['action'], request()->getPathInfo(), auth()->user()->auth_version, $c['command'], false);

        return $c;
    }

    public static function modeAllowed(string $mode): bool
    {
        try {
            if ($mode !== 'simulated' || ! self::web()) {
                return false;
            }
            self::current();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function provenance(array $proof, array $challenge, array $intent): bool
    {
        return self::modeAllowed('simulated') && $proof['provenance'] === 'simulated' && $challenge['transport_mode'] === 'simulated' && $intent['transport_mode'] === 'simulated'
            && $proof['merchant_id'] === $challenge['merchant_id'] && $proof['merchant_id'] === $intent['merchant_id']
            && $proof['actor_user_id'] === $challenge['actor_user_id'] && $proof['actor_user_id'] === $intent['actor_user_id']
            && $proof['contact_id'] === $challenge['contact_id'] && $proof['contact_id'] === $intent['contact_id']
            && $proof['challenge_id'] === $challenge['id'] && $intent['challenge_id'] === $challenge['id'];
    }

    public static function lineage(array $proof, array $challenge, array $intent, array $identity): string
    {
        return hash('sha256', StagingIdentity::encode([
            array_intersect_key($proof, array_flip(['id', 'merchant_id', 'actor_user_id', 'contact_id', 'purpose', 'challenge_id', 'provenance', 'verified_at', 'expires_at'])),
            array_intersect_key($challenge, array_flip(['id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'transport_mode', 'terminal_at', 'code_digest'])),
            array_intersect_key($intent, array_flip(['id', 'challenge_id', 'merchant_id', 'actor_user_id', 'contact_id', 'request_id', 'transport_mode', 'completed_at'])),
            array_intersect_key($identity, array_flip(['id', 'merchant_id', 'declared_by_user_id', 'nin_ciphertext', 'syntax_policy_version', 'declaration_version', 'notice_version'])),
        ]));
    }
}
