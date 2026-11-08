<?php

namespace App\Http\Controllers;

use App\Actions\Merchants\MerchantApplicationSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class MerchantApplicationController extends Controller
{
    public function index(Request $request, MerchantApplicationSubmission $submission): View
    {
        $merchant = $request->route('merchant');
        Gate::authorize('manageVerification', $merchant);

        return view('merchants.applications', [
            'merchant' => $merchant,
            'applications' => $merchant->applications()->orderByDesc('attempt_number')->paginate(20, ['id', 'attempt_number', 'status', 'current_slot', 'profile_version', 'submitted_at', 'reviewed_at']),
            'blockers' => $submission->blockers($request->user(), $merchant),
        ]);
    }

    public function submit(Request $request, MerchantApplicationSubmission $submission): never
    {
        $merchant = $request->route('merchant');
        Gate::authorize('manageVerification', $merchant);
        $input = $request->except('_token');
        $validator = Validator::make($input, ['application_id' => ['required', 'string', 'ulid'], 'profile_version' => ['required', 'integer', 'min:1'], 'context_version' => ['required', 'integer', 'min:0']]);
        abort_if(array_diff(array_keys($input), ['application_id', 'profile_version', 'context_version']) || $validator->fails(), 422, 'Invalid submission request. No application was submitted.');
        $submission->submit($request->user(), $merchant, $input['application_id'], (int) $input['profile_version'], (int) $input['context_version']);
    }
}
