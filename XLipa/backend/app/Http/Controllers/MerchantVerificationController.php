<?php

namespace App\Http\Controllers;

use App\Actions\Merchants\MerchantVerification;
use App\Http\Requests\MerchantVerificationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MerchantVerificationController extends Controller
{
    public function show(Request $request, MerchantVerification $verification): View
    {
        $merchant = $request->route('merchant');

        return view('merchants.verification', ['merchant' => $merchant, 'verification' => $verification->status($request->user(), $merchant), 'requestId' => (string) Str::uuid()]);
    }

    private function back(MerchantVerificationRequest $request, string $message): RedirectResponse
    {
        return redirect()->route('merchants.verification', ['merchant' => $request->route('merchant')->id])->with('status', $message);
    }

    public function contact(MerchantVerificationRequest $request, MerchantVerification $verification): RedirectResponse
    {
        $verification->capture($request->user(), $request->route('merchant'), 'contact', $request->validated());

        return $this->back($request, 'Private contact recorded. Phone ownership is not verified.');
    }

    public function identity(MerchantVerificationRequest $request, MerchantVerification $verification): RedirectResponse
    {
        $verification->capture($request->user(), $request->route('merchant'), 'identity', $request->validated());

        return $this->back($request, 'Private declaration recorded. Identity has not been independently verified.');
    }

    public function requestCode(MerchantVerificationRequest $request, MerchantVerification $verification): RedirectResponse
    {
        $verification->requestCode($request->user(), $request->route('merchant'), $request->validated('request_id'), $request->ip() ?? '');

        return $this->back($request, 'If verification is available and within its limits, a code was requested. Check your current verification status.');
    }

    public function verify(MerchantVerificationRequest $request, MerchantVerification $verification): RedirectResponse
    {
        $verification->verify($request->user(), $request->route('merchant'), $request->validated('challenge'), $request->validated('code'));

        return $this->back($request,'Verification attempt processed. Check your current status; invalid, expired or already-used codes cannot verify this contact.');
    }
}
