<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveMerchantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 401);
        $id = $request->route('merchant');
        abort_unless(is_string($id), 404);
        $merchant = Merchant::forMember($request->user())->findOrFail($id, ['id', 'business_name', 'operational_status', 'verification_status']);
        $request->route()->setParameter('merchant', $merchant);

        return $next($request);
    }
}
