<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\ViewModels\MerchantOnboardingSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MerchantOnboardingController extends Controller
{
    public function show(Request $request): View
    {
        $merchant = $request->route('merchant');
        Gate::authorize('viewSummary', $merchant);
        $application = $merchant->applications()->where('current_slot', 1)->first(['id', 'merchant_id', 'status', 'attempt_number', 'profile_version', 'profile_snapshot', 'submitted_at', 'submitted_by_user_id', 'reviewed_at', 'reviewed_by_user_id', 'public_feedback', 'internal_review_notes']);
        $canViewDetails = $application !== null && Gate::allows('view', [$application, $merchant]);
        $completionPreview = Gate::allows('manageVerification', $merchant)
            && $merchant->business_name === ''
            && ! in_array($merchant->operational_status->value, ['suspended', 'closed'], true)
            && $application !== null
            && $application->status === ApplicationStatus::Draft
            && $application->profile_version === $merchant->newQuery()->whereKey($merchant->id)->value('profile_version')
            && $application->getRawOriginal('profile_snapshot') === null
            && $application->submitted_at === null
            && $application->submitted_by_user_id === null
            && $application->reviewed_at === null
            && $application->reviewed_by_user_id === null
            && $application->public_feedback === null
            && $application->getRawOriginal('internal_review_notes') === null;

        return view('merchants.onboarding', ['summary' => MerchantOnboardingSummary::fromRecords($merchant, $application, $canViewDetails), 'merchant' => $merchant, 'completionPreview' => $completionPreview]);
    }
}
