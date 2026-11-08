<?php

namespace App\ViewModels;

final readonly class MerchantDashboardOverview
{
    public function __construct(
        public MerchantOnboardingSummary $onboarding,
        public string $accountName,
        public string $accountEmail,
        public string $membershipRole,
    ) {}

    public function restricted(): bool
    {
        return in_array($this->onboarding->operationalStatus, ['suspended', 'closed'], true);
    }

    public function readinessTitle(): string
    {
        if ($this->restricted()) {
            return 'Merchant access is limited';
        }

        return match ($this->onboarding->applicationStatus) {
            'Draft' => 'Your onboarding is in progress',
            'Awaiting review' => 'Your application is awaiting review',
            'More information required' => 'Your application needs attention',
            'Approved' => 'Your application is approved',
            'Rejected' => 'Your application was not approved',
            default => 'Onboarding status is unavailable',
        };
    }

    public function readinessDescription(): string
    {
        if ($this->restricted()) {
            return 'You can view your merchant status here. Contact support if you need help with this restriction.';
        }
        if ($this->onboarding->applicationStatus === null) {
            return 'There is no current application to display. Contact support for help with your onboarding.';
        }
        if ($this->membershipRole === 'staff') {
            return 'You have access to the merchant summary. An owner or manager can review application details.';
        }

        return 'Review your current onboarding status below. Application approval, contact verification and payment activation are separate steps.';
    }
}
