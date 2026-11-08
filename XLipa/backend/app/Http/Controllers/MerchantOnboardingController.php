<?php

namespace App\Http\Controllers;

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
        $application = $merchant->applications()->where('current_slot', 1)->first(['id', 'merchant_id', 'status', 'attempt_number', 'submitted_at', 'reviewed_at', 'public_feedback']);
        $canViewDetails = $application !== null && Gate::allows('view', [$application, $merchant]);

        return view('merchants.onboarding', ['summary' => MerchantOnboardingSummary::fromRecords($merchant, $application, $canViewDetails), 'merchant' => $merchant]);
    }
}
