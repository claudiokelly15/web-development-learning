<?php

namespace App\Http\Controllers;

use App\Actions\Auth\ResolvePlatformRole;
use App\Enums\PlatformRole;
use App\Models\PlatformRoleAssignment;
use App\ViewModels\AdminWorkspaceOverview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminWorkspaceController extends Controller
{
    public function __construct(private readonly ResolvePlatformRole $roles) {}

    public function index(Request $request): RedirectResponse
    {
        abort_unless(Gate::allows('viewOwnWorkspaceAccess', PlatformRoleAssignment::class), 404);
        $role = $this->roles->execute($request->user());
        abort_unless($role, 404);

        return redirect()->route($role === PlatformRole::ChiefAdmin ? 'admin.chief' : 'admin.staff');
    }

    public function chief(Request $request): View
    {
        Gate::authorize('viewChiefWorkspace', PlatformRoleAssignment::class);

        return $this->workspace($request, PlatformRole::ChiefAdmin);
    }

    public function staff(Request $request): View
    {
        Gate::authorize('viewStaffWorkspace', PlatformRoleAssignment::class);

        return $this->workspace($request, PlatformRole::Admin);
    }

    public function access(Request $request): View
    {
        Gate::authorize('viewOwnWorkspaceAccess', PlatformRoleAssignment::class);

        return $this->workspace($request, accessPage: true);
    }

    private function workspace(Request $request, ?PlatformRole $required = null, bool $accessPage = false): View
    {
        $role = $this->roles->execute($request->user());
        abort_unless($role !== null && ($required === null || $role === $required), 403);

        return view('admin.workspace', ['overview' => new AdminWorkspaceOverview(
            $request->user()->name,
            $request->user()->email,
            $role,
            $accessPage,
        )]);
    }
}
