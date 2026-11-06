<?php

namespace App\ViewModels;

use App\Enums\ApplicationStatus;
use App\Models\Merchant;
use App\Models\MerchantApplication;

final readonly class MerchantOnboardingSummary
{
    public function __construct(
        public string $businessName,
        public string $operationalStatus,
        public string $verificationStatus,
        public ?string $applicationStatus,
        public ?int $attemptNumber,
        public ?string $submittedAt,
        public ?string $reviewedAt,
        public ?string $publicFeedback,
    ) {}

    public static function fromRecords(Merchant $merchant, ?MerchantApplication $application, bool $canViewDetails): self
    {
        $status = $application ? match ($application->status) {
            ApplicationStatus::Draft => 'Draft',
            ApplicationStatus::PendingReview => 'Awaiting review',
            ApplicationStatus::InformationRequested => 'More information required',
            ApplicationStatus::Approved => 'Approved',
            ApplicationStatus::Rejected => 'Rejected',
        } : null;

        return new self(
            $merchant->business_name,
            $merchant->operational_status->value,
            $merchant->verification_status->value,
            $status,
            $canViewDetails ? $application?->attempt_number : null,
            $canViewDetails ? $application?->submitted_at?->toIso8601String() : null,
            $canViewDetails ? $application?->reviewed_at?->toIso8601String() : null,
            $canViewDetails ? $application?->public_feedback : null,
        );
    }
}
