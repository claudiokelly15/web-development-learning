<?php

namespace App\Staging;

use App\Actions\Merchants\BusinessNameCompletionOutcomeUnknown;
use App\Actions\Merchants\BusinessNameCompletionResolver;
use App\Actions\Merchants\BusinessNameCompletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class StagingBusinessNameController
{
    public function show(Request $request): View
    {
        Gate::authorize('manageVerification', $request->route('merchant'));
        $context = app('staging.context');
        $grants = StagingIdentity::read($context['root'].'/capabilities.json');
        $key = $request->user()->id.'|onboarding.name|'.$request->route('merchant')->id;
        $resolutionKey = $request->user()->id.'|onboarding.name.resolve|'.$request->route('merchant')->id;
        $resolving = isset($grants[$resolutionKey]);
        if ($resolving) {
            $key = $resolutionKey;
        }
        $command = isset($grants[$key]) ? StagingIdentity::verified($grants[$key], $context['public']) : null;
        if ($command) {
            $request->session()->put('staging.name.'.$command['command'], true);
        }

        return view('merchants.staging-completion', ['merchant' => $request->route('merchant'), 'command' => $command, 'resolving' => $resolving]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manageVerification', $request->route('merchant'));
        $request->validate(['command' => ['required', 'regex:/^[a-f0-9]{32}$/D'], 'business_name' => ['required', 'string', 'max:200']]);
        $context = app('staging.context');
        $resolving = str_ends_with($request->path(), '/resolve');
        $action = $resolving ? 'onboarding.name.resolve' : 'onboarding.name';
        $grants = StagingIdentity::read($context['root'].'/capabilities.json');
        $envelope = $grants[$request->user()->id.'|'.$action.'|'.$request->route('merchant')->id] ?? null;
        abort_unless(is_array($envelope), 403);
        $command = StagingIdentity::verified($envelope, $context['public']);
        abort_unless($request->input('command') === $command['command'] && $request->input('business_name') === $command['name'] && $request->session()->get('staging.name.'.$command['command']) === true, 409);
        try {
            (new StagingCapability($context))->authorize($envelope, $request->user()->id, $request->route('merchant')->id, $action, $request->getPathInfo(), $request->user()->auth_version, $command['command']);
        } catch (\Throwable) {
            abort(403, 'Fictional command assurance is invalid or already consumed.');
        }
        $request->attributes->set('staging.name.command', $envelope);
        StagingCommandState::begin($command);
        try {
            $result = $resolving
                ? app(BusinessNameCompletionResolver::class)->resolve($request->user(), $request->route('merchant')->id, $command['expected'], $command['name'])
                : app(BusinessNameCompletionService::class)->complete($request->user(), $request->route('merchant')->id, $command['expected'], $command['name']);

            StagingCommandState::finish($command, 'completed');

            return response()->json($result);
        } catch (BusinessNameCompletionOutcomeUnknown) {
            StagingCommandState::finish($command, 'unresolved');

            return response()->json(['status' => 'unresolved', 'message' => 'Do not repeat. Custodian-authorized resolution required.'], 202);
        } catch (HttpExceptionInterface $error) {
            StagingCommandState::finish($command, 'rejected');
            throw $error;
        }
    }
}
