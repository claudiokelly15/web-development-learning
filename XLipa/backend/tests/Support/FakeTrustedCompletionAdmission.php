<?php

namespace Tests\Support;

use App\Actions\Merchants\BusinessNameCompletionAdmission;
use App\Actions\Merchants\BusinessNameCompletionHttpInput;
use App\Actions\Merchants\TrustedWorkflowProfile;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use Illuminate\Database\Connection;
use RuntimeException;
use Throwable;

/** Existing admission interface reused only for fictional contracts, never provider-bound. */
final class FakeTrustedCompletionAdmission implements BusinessNameCompletionAdmission
{
    public array $owner;

    public string $session = 'fixture-session';

    public bool $ownerAvailable = true;

    private array $grants = [];

    public function __construct(public readonly FakeCompletionTrustAuthority $authority, private readonly array $descriptor, array $owner)
    {
        if (getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing')) {
            throw new RuntimeException('Fictional admission is unavailable.');
        }
        $this->owner = $owner;
    }

    /** Explicit fixture grant; mode-specific and scoped to the exact original private tuple. */
    public function grant(int $actor, string $merchant, array $expected, bool $resolving): void
    {
        (new BusinessNameCompletionHttpInput)->expectations($merchant, $expected);
        $this->grants[$resolving ? 'resolve' : 'complete'] = ['actor' => $actor, 'merchant' => $merchant, 'expected' => $expected, 'session' => $this->session];
    }

    private function descriptor(): array
    {
        return (new TrustedWorkflowProfile)->select($this->descriptor, $this->authority->runtime, $this->authority->observed, $this->authority, $this->authority);
    }

    public function expectedProfile(): ?string
    {
        try {
            if (! $this->authority->managedMemoryReady()) {
                return null;
            }

            return $this->descriptor()['approved_profile'];
        } catch (Throwable) {
            return null;
        }
    }

    public function authorized(int $actor, string $merchant, array $expected, bool $resolving): bool
    {
        try {
            if (! $this->authority->managedMemoryReady()) {
                return false;
            }
            $this->descriptor();
            (new BusinessNameCompletionHttpInput)->expectations($merchant, $expected);
            $grant = $this->grants[$resolving ? 'resolve' : 'complete'] ?? null;
            $owner = $this->owner;
            if ($this->expectedProfile() !== WorkflowSchemaReadiness::BNC || ! $this->ownerAvailable || ! $grant || $grant !== ['actor' => $actor, 'merchant' => $merchant, 'expected' => $expected, 'session' => $this->session]) {
                return false;
            }
            if ($owner['actor'] !== $actor || $owner['merchant'] !== $merchant || $owner['membership_id'] !== $expected['membership_id'] || $owner['session'] !== $this->session || ! $owner['verified'] || ! $owner['active'] || $owner['role'] !== 'owner' || $owner['revoked'] || $owner['suspended'] || $owner['session_auth_version'] !== $owner['auth_version'] || $owner['session_email_binding_version'] !== $owner['email_binding_version']) {
                return false;
            }
            if (! $resolving && ($owner['auth_version'] !== $expected['auth_version'] || $owner['email_binding_version'] !== $expected['email_binding_version'] || $owner['authority_version'] !== $expected['authority_version'])) {
                return false;
            }

            return $this->authority->requestAllowed($actor, $merchant, $expected['completion_request_id'], $resolving);
        } catch (Throwable) {
            return false;
        }
    }

    public function milliseconds(): int
    {
        return $this->authority->milliseconds();
    }

    public function recordPolicy(): string
    {
        return $this->authority->recordPolicy();
    }

    public function retentionAllowed(string $policy, int $occurredAt): bool
    {
        return $this->authority->retentionAllowed($policy, $occurredAt, $this->authority->recoveryHold);
    }

    public function authoritative(Connection $connection, int $actor, string $merchant, array $expected, bool $resolving): bool
    {
        try {
            return $this->authorized($actor, $merchant, $expected, $resolving) && $this->authority->authoritative($connection, $this->descriptor());
        } catch (Throwable) {
            return false;
        }
    }

    public function reconciliationReady(int $actor, string $merchant): bool
    {
        return $this->ownerAvailable && $this->authority->reconciliation && $this->owner['actor'] === $actor && $this->owner['merchant'] === $merchant;
    }
}
