<?php

namespace App\Staging;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StagingReadAdmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        $action = match (true) {
            (bool) preg_match('~^merchants/([A-Za-z0-9]{26})/dashboard$~D', $path) => 'dashboard.read',
            $path === 'admin/chief' => 'admin.chief.read',
            $path === 'admin/staff' => 'admin.staff.read',
            in_array($path, ['staging/admission', 'staging/command'], true) => 'foundation.probe',
            default => null,
        };
        if ($action && $request->user()) {
            $context = app('staging.context');
            $user = $request->user()->fresh();
            abort_unless($user && $user->getRawOriginal('account_status') === 'active' && $request->session()->get('auth_version') === $user->auth_version, 403);
            $merchant = $action === 'dashboard.read' ? explode('/', $path)[1] : null;
            $grants = StagingIdentity::read($context['root'].'/capabilities.json');
            $key = $user->id.'|'.$action.'|'.($merchant ?? '');
            abort_unless(isset($grants[$key]), 403, 'Fictional staging assurance is required.');
            try {
                (new StagingCapability($context))->authorize($grants[$key], (int) $user->id, $merchant, $action, '/'.$path, $user->auth_version, $path === 'staging/command' ? $request->input('command') : null);
            } catch (\Throwable) {
                abort(403, 'Fictional staging assurance is invalid.');
            }
        }

        return $next($request);
    }
}
