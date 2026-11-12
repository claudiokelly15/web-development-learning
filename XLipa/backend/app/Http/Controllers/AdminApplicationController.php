<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\PlatformRoleAssignment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Read-only Chief review; even historical membership excludes conflicted records. */
class AdminApplicationController extends Controller
{
    private const COLUMNS = ['applications.id', 'applications.merchant_id', 'merchants.business_name', 'merchants.verification_status', 'merchants.operational_status', 'merchants.profile_version as current_profile_version', 'applications.attempt_number', 'applications.profile_version', 'applications.status', 'applications.submitted_at', 'applications.reviewed_at'];

    private function scope(Request $request): Builder
    {
        Gate::authorize('viewApplicationReviewQueue', PlatformRoleAssignment::class);

        return DB::table('merchant_applications as applications')->join('merchants', 'merchants.id', '=', 'applications.merchant_id')
            ->where('applications.status', '!=', 'draft')->where('merchants.created_by_user_id', '!=', $request->user()->id)
            ->whereNotExists(function ($query) use ($request): void {
                $query->selectRaw('1')->from('merchant_memberships')->whereColumn('merchant_memberships.merchant_id', 'merchants.id')->where('merchant_memberships.user_id', $request->user()->id);
            })->where(function ($query) use ($request): void {
                $query->whereNull('applications.submitted_by_user_id')->orWhere('applications.submitted_by_user_id', '!=', $request->user()->id);
            });
    }

    public function index(Request $request): View
    {
        $query = $this->scope($request);
        $validator = Validator::make($request->query(), [
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['pending_review', 'information_requested', 'approved', 'rejected'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);
        abort_if($validator->fails(), 422);
        $filters = $validator->validated();
        $status = $filters['status'] ?? null;
        $applications = $query->when($status, fn (Builder $query) => $query->where('applications.status', $status))
            ->orderByDesc('applications.submitted_at')->orderBy('applications.id')->paginate(20, self::COLUMNS)->appends(['status' => $status]);
        $statuses = array_filter(ApplicationStatus::cases(), fn (ApplicationStatus $status): bool => $status !== ApplicationStatus::Draft);

        return view('admin.applications', compact('applications', 'status', 'statuses'));
    }

    public function show(Request $request, string $application): View
    {
        $application = $this->scope($request)->where('applications.id', $application)->first(self::COLUMNS);
        abort_if($application === null, 404);
        abort_if(Validator::make($request->query(), ['page' => ['sometimes', 'integer', 'min:1', 'max:100000']])->fails(), 422);
        $history = DB::table('audit_logs')->where('merchant_id', $application->merchant_id)->where('application_id', $application->id)
            ->whereIn('event_type', ['application.submitted', 'application.reviewed'])->orderByDesc('occurred_at')->orderBy('id')
            ->paginate(20, ['event_type', 'occurred_at']);
        $evidence = 'Legacy / no SE-1.0 evidence recorded';
        if (Schema::hasTable('merchant_application_submission_evidence')) {
            $record = DB::table('merchant_application_submission_evidence')->where('application_id', $application->id)->where('merchant_id', $application->merchant_id)
                ->first(['submitted_at', 'profile_version', 'attempt_number', 'audit_event_id']);
            if ($record !== null) {
                $auditMatches = DB::table('audit_logs')->where('id', $record->audit_event_id)->where('merchant_id', $application->merchant_id)
                    ->where('application_id', $application->id)->where('event_type', 'application.submitted')->where('occurred_at', $record->submitted_at)->exists();
                $evidence = $auditMatches && $record->submitted_at === $application->submitted_at
                    && $record->profile_version === $application->profile_version && $record->attempt_number === $application->attempt_number
                    ? 'Evidence recorded; selected application and audit references agree'
                    : 'Evidence reference mismatch; review held';
            }
        } else {
            $evidence = 'SE-1.0 evidence schema unavailable; integrity unverified';
        }

        return view('admin.application', compact('application', 'history', 'evidence'));
    }
}
