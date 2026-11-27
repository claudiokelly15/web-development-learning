<?php

namespace App\Staging;

use PDO;

/** Independent fictional issuer signature plus current durable revocation/replay state. */
final class StagingCapability
{
    public const WORKFLOW_ACTIONS = ['onboarding.name', 'onboarding.name.resolve', 'onboarding.contact', 'onboarding.identity', 'onboarding.otp.request', 'onboarding.otp.verify', 'onboarding.verification.read', 'onboarding.application.read', 'onboarding.submit', 'onboarding.submit.resolve', 'onboarding.decision', 'onboarding.decision.read', 'onboarding.proof.attest', 'onboarding.chief.assurance'];

    public function __construct(private readonly array $context) {}

    public function authorize(array $envelope, int $actor, ?string $merchant, string $action, string $resource, int $version, ?string $command = null, bool $consume = true): void
    {
        $c = StagingIdentity::verified($envelope, $this->context['public']);
        foreach (['namespace', 'issuer', 'runtime', 'release', 'generation'] as $key) {
            StagingIdentity::check($c[$key] === $this->context[$key]);
        }
        StagingIdentity::check($c['store'] === $this->context['store']);
        StagingIdentity::check(in_array($action, ['dashboard.read', 'admin.chief.read', 'admin.staff.read', 'foundation.probe', ...self::WORKFLOW_ACTIONS, ...StagingFinancialAuthority::ACTIONS], true));
        StagingIdentity::check($c['actor'] === $actor && $c['merchant'] === $merchant && $c['action'] === $action && $c['resource'] === $resource && $c['version'] === $version);
        StagingIdentity::check($c['issued'] <= time() && $c['expires'] > time() && $c['expires'] <= $this->context['expires']);
        StagingIdentity::check(($c['command'] ?? null) === $command);
        StagingIdentity::check(! file_exists($this->context['root'].'/retired'));
        $pdo = new PDO('sqlite:'.StagingIdentity::path($this->context['root'].'/admission.sqlite'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA busy_timeout=2000');
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $revoked = $pdo->prepare('SELECT 1 FROM revoked WHERE id=?');
            $revoked->execute([$c['id']]);
            StagingIdentity::check(! $revoked->fetchColumn());
            $clock = (int) $pdo->query('SELECT observed FROM clock')->fetchColumn();
            StagingIdentity::check(time() >= $clock);
            $pdo->prepare('UPDATE clock SET observed=?')->execute([time()]);
            if ($command !== null) {
                StagingIdentity::check((bool) preg_match('/^[a-f0-9]{32}$/D', $command));
                if ($consume) {
                    $pdo->prepare('INSERT INTO commands(id,capability) VALUES(?,?)')->execute([$command, $c['id']]);
                } else {
                    $claimed = $pdo->prepare('SELECT capability FROM commands WHERE id=?');
                    $claimed->execute([$command]);
                    StagingIdentity::check($claimed->fetchColumn() === $c['id']);
                }
            }
            $pdo->exec('COMMIT');
        } catch (\Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    }
}
