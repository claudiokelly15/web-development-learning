<?php

namespace Tests\Support;

use App\Actions\Merchants\ApplicationDecisionAdmission;
use LogicException;

/** Fictional operation-specific final authority and step-up, never registered by runtime code. */
final class FakeApplicationDecisionAdmission extends ApplicationDecisionAdmission
{
    public bool $allow = true;

    public int $version = 1;

    public int $time = 1794614400200;

    private array $grants = [];

    public function __construct()
    {
        if (getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing')) {
            throw new LogicException('Fictional decision authority requires isolated testing.');
        }
    }

    public function grant(int $actor, string $merchant, string $application, array $expected, string $outcome, ?string $reason): void
    {
        $this->grants[$expected['decision_request_id']] = [$actor, $merchant, $application, $expected, $outcome, $reason];
    }

    public function authorized(int $actor, string $merchant, string $application, array $expected, string $outcome, ?string $reason): bool
    {
        return $this->allow && ($this->grants[$expected['decision_request_id']] ?? null) === [$actor, $merchant, $application, $expected, $outcome, $reason];
    }

    public function authorityVersion(int $actor): ?int
    {
        return $this->version;
    }

    public function milliseconds(): int
    {
        return $this->time;
    }
}
