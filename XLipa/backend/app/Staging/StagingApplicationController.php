<?php

namespace App\Staging;

use App\Actions\Merchants\ApplicationDecisionService;
use App\Actions\Merchants\ApplicationSubmissionService;
use App\Actions\Merchants\SubmissionOutcomeUnknown;
use App\Http\Controllers\AdminApplicationController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class StagingApplicationController
{
    public function show(Request $request): View
    {
        Gate::authorize('manageVerification', $request->route('merchant'));
        $application = $request->route('merchant')->applications()->where('current_slot', 1)->sole();

        return view('merchants.staging-application', ['merchant' => $request->route('merchant'), 'application' => $application]);
    }

    public function submit(Request $request): JsonResponse
    {
        Gate::authorize('manageVerification', $request->route('merchant'));
        $c = StagingWorkflowAuthority::current();
        abort_unless(array_diff(array_keys($request->all()), ['_token']) === [], 422);
        try {
            $service = app(ApplicationSubmissionService::class);
            $result = str_ends_with($request->path(), '/resolve')
                ? $service->resolve($request->user(), $request->route('merchant')->id, $c['expected'])
                : $service->submit($request->user(), $request->route('merchant')->id, $c['expected']);

            return response()->json($result ?? ['status' => 'absent']);
        } catch (SubmissionOutcomeUnknown) {
            return response()->json(['status' => 'unresolved', 'message' => 'Do not retry; use separately authorized resolution.'], 202);
        }
    }

    public function review(Request $request, string $application): View
    {
        $record = app(AdminApplicationController::class)->scope($request)->where('applications.id', $application)->first(['applications.id', 'applications.merchant_id', 'applications.status', 'merchants.business_name']);
        abort_unless($record, 404);

        return view('admin.staging-decision', ['application' => $record]);
    }

    public function decide(Request $request, string $application): JsonResponse
    {
        $c = StagingWorkflowAuthority::current();
        abort_unless(array_diff(array_keys($request->all()), ['_token']) === [], 422);
        try {
            return response()->json(app(ApplicationDecisionService::class)->decide($request->user(), $c['merchant'], $application, $c['expected'], $c['outcome'], $c['reason']));
        } catch (SubmissionOutcomeUnknown) {
            return response()->json(['status' => 'unresolved', 'message' => 'Decision held. No retry; retain command and request custodian review.'], 202);
        }
    }
}
