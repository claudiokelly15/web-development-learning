<?php

namespace App\Staging;

use App\Actions\Merchants\WorkflowSchemaReadiness;
use App\Http\Middleware\EnsureAuthenticationReady;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Additive binding in staging entry only. Existing operational restriction is unchanged. */
final class StagingAuthenticationReady extends EnsureAuthenticationReady
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->bound('staging.context') && app()->environment('staging') && PHP_SAPI === 'cli-server' && ! app()->runningInConsole(), 503);
        $profile = app(WorkflowSchemaReadiness::class)->profile();
        $expected = (app('staging.context')['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION ? WorkflowSchemaReadiness::BNC : WorkflowSchemaReadiness::FOUNDATION;
        abort_unless(config('mail.default') === 'array' && $profile === $expected, 503);

        return $next($request);
    }
}
