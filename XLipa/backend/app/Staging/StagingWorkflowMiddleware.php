<?php

namespace App\Staging;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class StagingWorkflowMiddleware
{
    public static function action(string $path, string $method): ?string
    {
        if (preg_match('~^merchants/[A-Za-z0-9]{26}/verification(?:/(contact|identity|request|code))?$~D', $path, $match)) {
            return match ($match[1] ?? '') {
                'contact' => 'onboarding.contact', 'identity' => 'onboarding.identity', 'request' => 'onboarding.otp.request', 'code' => 'onboarding.otp.verify', default => 'onboarding.verification.read',
            };
        }
        if (preg_match('~^merchants/[A-Za-z0-9]{26}/applications/integrated(?:/(submit|resolve))?$~D', $path, $match)) {
            return ($match[1] ?? '') === 'resolve' ? 'onboarding.submit.resolve' : (($match[1] ?? '') === 'submit' ? 'onboarding.submit' : 'onboarding.application.read');
        }
        if (preg_match('~^admin/applications/([A-Za-z0-9]{26})/decision$~D', $path)) {
            return $method === 'POST' ? 'onboarding.decision' : 'onboarding.decision.read';
        }

        return null;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $action = self::action($request->path(), $request->method());
        if ($action !== null) {
            abort_unless($request->user() && StagingWorkflowAuthority::web(), 403);
            $user = $request->user()->fresh();
            abort_unless($user && $user->getRawOriginal('account_status') === 'active' && $request->session()->get('auth_version') === $user->auth_version, 403);
            $context = app('staging.context');
            $grants = StagingIdentity::read($context['root'].'/capabilities.json');
            $merchant = str_starts_with($request->path(), 'merchants/') ? explode('/', $request->path())[1] : null;
            if ($merchant === null) {
                $merchant = DB::table('merchant_applications')->where('id', explode('/', $request->path())[2])->value('merchant_id');
            }
            $envelope = $grants[$request->user()->id.'|'.$action.'|'.$merchant] ?? null;
            abort_unless(is_array($envelope), 403, 'Scoped fictional staging admission required.');
            try {
                $c = StagingIdentity::verified($envelope, $context['public']);
                if (isset($c['membership_id'])) {
                    $membership = DB::table('merchant_memberships')->where('id', $c['membership_id'])->where('user_id', $user->id)->where('merchant_id', $merchant)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole();
                    StagingIdentity::check($membership->authority_version === $c['authority_version'] && $user->email_binding_version === $c['email_version']);
                }
                (new StagingCapability($context))->authorize($envelope, $request->user()->id, $merchant, $action, $request->getPathInfo(), $request->user()->auth_version, $request->isMethod('POST') ? $c['command'] : null);
                $request->attributes->set('staging.workflow.command', $envelope);
                if ($request->isMethod('POST')) {
                    StagingCommandState::begin($c);
                }
            } catch (\Throwable) {
                abort(403, 'Fictional staging admission invalid, expired, revoked or consumed.');
            }
        }

        try {
            $response = $next($request);
        } catch (HttpExceptionInterface $error) {
            if (isset($c) && $request->isMethod('POST')) {
                StagingCommandState::finish($c, 'rejected');
            }
            throw $error;
        }
        if (isset($c) && $request->isMethod('POST')) {
            StagingCommandState::finish($c, $response->getStatusCode() === 202 ? 'unresolved' : ($response->getStatusCode() < 400 ? 'completed' : 'rejected'));
        }

        return $response;
    }
}
