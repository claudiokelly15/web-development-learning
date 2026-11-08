<?php

namespace App\Http\Controllers;

use App\Models\PlatformRoleAssignment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Read-only Chief preparation; excludes own and membership-linked applications. */
class AdminApplicationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewApplicationReviewQueue', PlatformRoleAssignment::class);
        $applications = DB::table('merchant_applications as applications')->join('merchants', 'merchants.id', '=', 'applications.merchant_id')
            ->where('applications.status', '!=', 'draft')->where('merchants.created_by_user_id', '!=', $request->user()->id)
            ->whereNotExists(function ($query) use ($request): void {
                $query->selectRaw('1')->from('merchant_memberships')->whereColumn('merchant_memberships.merchant_id', 'merchants.id')->where('merchant_memberships.user_id', $request->user()->id);
            })->where(function ($query) use ($request): void {
                $query->whereNull('applications.submitted_by_user_id')->orWhere('applications.submitted_by_user_id', '!=', $request->user()->id);
            })->orderByDesc('applications.submitted_at')->orderBy('applications.id')
            ->paginate(20, ['applications.id', 'merchants.business_name', 'applications.attempt_number', 'applications.status', 'applications.submitted_at', 'applications.reviewed_at']);

        return view('admin.applications', compact('applications'));
    }
}
