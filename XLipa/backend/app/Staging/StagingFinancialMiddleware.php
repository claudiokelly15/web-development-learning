<?php

namespace App\Staging;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class StagingFinancialMiddleware
{
    public static function action(string $path, string $method): ?string
    {
        if (preg_match('~^fictional-checkout/[a-f0-9]{64}$~D', $path)) {
            return $method === 'POST' ? 'r24.checkout.initiate' : 'r24.checkout.read';
        }
        if (! preg_match('~^merchants/[A-Za-z0-9]{26}/payment-products(?:/(.*))?$~D', $path, $m)) {
            return null;
        }
        $suffix = $m[1] ?? '';

        return match (true) {
            $suffix === '' && $method === 'GET' => 'r24.products.read',
            $suffix === 'links' && $method === 'POST' => 'r24.links.create',
            $suffix === 'payments' && $method === 'POST' => 'r24.payment.create',
            $suffix === 'transactions' && $method === 'GET' => 'r24.transactions.read',
            (bool) preg_match('~^links/[a-f0-9-]{36}/disable$~D', $suffix) => 'r24.links.disable',
            (bool) preg_match('~^links/[a-f0-9-]{36}/widgets$~D', $suffix) => 'r24.widgets.create',
            (bool) preg_match('~^widgets/[a-f0-9-]{36}$~D', $suffix) => 'r24.widgets.read',
            (bool) preg_match('~^payments/[a-f0-9-]{36}/query$~D', $suffix) => 'r24.payment.query',
            (bool) preg_match('~^payments/[a-f0-9-]{36}/mock-confirmation$~D', $suffix) => 'r24.mock.event',
            (bool) preg_match('~^payments/[a-f0-9-]{36}$~D', $suffix) => 'r24.payment.read',
            (bool) preg_match('~^attempts/[a-f0-9-]{36}/post$~D', $suffix) => 'r24.ledger.post',
            (bool) preg_match('~^attempts/[a-f0-9-]{36}/reconcile$~D', $suffix) => 'r24.ledger.reconcile',
            default => null,
        };
    }

    public function handle(Request $request, Closure $next): Response
    {
        $action = self::action($request->path(), $request->method());
        if ($action === null) {
            return $next($request);
        }
        try {
            $context = StagingFinancialAuthority::runtime();
            $guest = str_starts_with($action, 'r24.checkout.');
            if ($guest) {
                abort_unless(auth()->guest(), 403);
                $token = explode('/', $request->path())[1];
                $bundle = session('r24.customer.'.$token);
                if ($bundle === null) {
                    $bundle = StagingIdentity::read($context['root'].'/checkout-'.$token.'.json');
                    StagingIdentity::check((bool) preg_match('/^[a-f0-9]{32}$/D', $bundle['customer']));
                    $read = StagingIdentity::verified($bundle['r24.checkout.read'], $context['public']);
                    StagingIdentity::check($read['customer'] === $bundle['customer']);
                    (new StagingCapability($context))->authorize($bundle['r24.checkout.read'], 0, $read['merchant'], 'r24.checkout.read', $request->getPathInfo(), $read['version']);
                    $binding = $context['root'].'/customer-'.$bundle['customer'].'.binding.json';
                    if (! file_exists($binding)) {
                        $handle = fopen($binding, 'x');
                        StagingIdentity::check($handle !== false);
                        try {
                            $body = StagingIdentity::encode(['session' => hash('sha256', session()->getId())]);
                            StagingIdentity::check(fwrite($handle, $body) === strlen($body) && fflush($handle) && fsync($handle));
                        } finally {
                            fclose($handle);
                        }
                    }
                    session(['r24.customer.'.$token => $bundle]);
                }
                $envelope = $bundle[$action];
            } else {
                abort_unless($request->user(), 403);
                $merchant = explode('/', $request->path())[1];
                $grants = StagingIdentity::read($context['root'].'/capabilities.json');
                $envelope = $grants[$request->user()->id.'|'.$action.'|'.$merchant] ?? null;
                StagingIdentity::check(is_array($envelope));
            }
            $c = StagingIdentity::verified($envelope, $context['public']);
            (new StagingCapability($context))->authorize($envelope, $guest ? 0 : $request->user()->id, $c['merchant'], $action, $request->getPathInfo(), $c['version'], $c['command']);
            $request->attributes->set('staging.financial.command', $envelope);
            StagingFinancialAuthority::current();
            if (! $guest && $request->isMethod('POST')) {
                StagingCommandState::begin($c);
            }
        } catch (\Throwable) {
            abort(403, 'Independent fictional payment admission is unavailable, invalid or consumed.');
        }
        try {
            $response = $next($request);
        } catch (HttpExceptionInterface $error) {
            if (! $guest && $request->isMethod('POST')) {
                StagingCommandState::finish($c, 'rejected');
            }
            throw $error;
        }
        if (! $guest && $request->isMethod('POST')) {
            StagingCommandState::finish($c, $response->getStatusCode() === 202 ? 'unresolved' : ($response->getStatusCode() < 400 ? 'completed' : 'rejected'));
        }

        return $response;
    }
}
