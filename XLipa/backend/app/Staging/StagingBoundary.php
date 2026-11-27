<?php

namespace App\Staging;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StagingBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app('staging.context');
        abort_unless(PHP_SAPI === 'cli-server' && ! app()->runningInConsole() && ! app()->runningUnitTests(), 503);
        abort_unless($request->getSchemeAndHttpHost() === StagingIdentity::ORIGIN, 403);
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            abort_unless($request->header('Origin') === StagingIdentity::ORIGIN, 403);
        }
        $path = $request->path();
        $allowed = in_array($path, ['/', 'xlipasignin.html', 'xlipacreateaccount.html', 'login', 'logout', 'account', 'email/verify', 'email/verification-notification', 'forgot-password', 'reset-password', 'admin', 'admin/chief', 'admin/staff', 'admin/access', 'staging/status', 'staging/admission', 'staging/command'], true)
            || preg_match('~^email/verify/[0-9]+/[a-f0-9]{40}$~D', $path)
            || preg_match('~^reset-password/[A-Za-z0-9]+$~D', $path)
            || preg_match('~^merchants/[A-Za-z0-9]{26}/dashboard$~D', $path);
        $onboarding = ($context['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION
            && (preg_match('~^merchants/[A-Za-z0-9]{26}/(?:onboarding(?:/complete(?:/resolve)?)?|verification(?:/(?:contact|identity|request|code))?|applications(?:/integrated(?:/(?:submit|resolve))?)?)$~D', $path)
                || preg_match('~^admin/applications(?:/[A-Za-z0-9]{26}(?:/decision)?)?$~D', $path));
        $payments = ($context['financial_program'] ?? null) === StagingFinancialAuthority::VERSION
            && (preg_match('~^merchants/[A-Za-z0-9]{26}/payment-products(?:/[a-z0-9/-]+)?$~D', $path) || preg_match('~^fictional-checkout/[a-f0-9]{64}$~D', $path));
        abort_unless($allowed || $onboarding || $payments, 403, 'This capability is unavailable in staging.');
        if (! $onboarding) {
            abort_if($request->hasAny(['nin', 'code', 'subject_claim', 'declaration']), 403, 'Identity collection is unavailable.');
        }
        if ($request->has('nin')) {
            abort_unless($onboarding && is_string($request->input('nin')) && preg_match('/^FICTIONAL-[A-Z]{8,32}$/D', $request->input('nin')), 403, 'Only synthetic identity declarations are permitted.');
        }
        if ($request->has('phone')) {
            abort_unless($onboarding && $request->input('phone') === '+255600000000', 403, 'Only the reserved fictional contact is permitted.');
        }
        if ($request->has('email')) {
            abort_unless(is_string($request->input('email')) && preg_match('/^[a-z0-9-]+@example\.test$/D', $request->input('email')), 403, 'Use fictional staging accounts only.');
        }
        $response = $next($request);
        $response->headers->set('X-XLipa-Staging', 'fictional-r21');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->setContent(str_replace('<body', '<body data-xlipa-staging="fictional"', $response->getContent()));
            $response->setContent(str_replace('</body>', '<aside role="note" style="position:fixed;top:0;left:0;right:0;z-index:99999;text-align:center;background:#fff3cd;color:#172554;font:12px sans-serif">Fictional disposable staging · No real identity, messages or funds</aside></body>', $response->getContent()));
        }

        return $response;
    }
}
