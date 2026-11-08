<?php

namespace App\Http\Controllers;

use App\Actions\Merchants\VerificationReadiness;
use App\ViewModels\MerchantDashboardOverview;
use App\ViewModels\MerchantOnboardingSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MerchantDashboardController extends Controller
{
    public function show(Request $request): View
    {
        $merchant = $request->route('merchant');
        $user = $request->user();
        Gate::authorize('viewSummary', $merchant);
        $membership = $merchant->memberships()->where('user_id', $user->getKey())->where('status', 'active')->first(['role']);
        abort_unless($membership, 404);
        $application = $merchant->applications()->where('current_slot', 1)->first(['id', 'merchant_id', 'status', 'attempt_number', 'submitted_at', 'reviewed_at', 'public_feedback']);
        $canViewDetails = $application !== null && Gate::allows('view', [$application, $merchant]);

        return view('merchants.dashboard', [
            'verificationAvailable' => Gate::allows('manageVerification', $merchant) && app(VerificationReadiness::class)->ready(),
            'overview' => new MerchantDashboardOverview(
                MerchantOnboardingSummary::fromRecords($merchant, $application, $canViewDetails),
                $user->name,
                $user->email,
                $membership->role->value,
            ),
            'merchantId' => $merchant->getKey(),
        ]);
    }
}
