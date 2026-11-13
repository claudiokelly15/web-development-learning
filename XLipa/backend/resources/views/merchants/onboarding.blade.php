@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Merchant onboarding status')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/xlipa-onboarding.css') }}">
@endpush
@section('content')
<div class="onboarding-page">
    <nav class="onboarding-navigation" aria-label="Onboarding navigation">
        <a href="{{ route('merchants.dashboard', ['merchant' => $merchant->id]) }}"><span aria-hidden="true">&larr;</span> Merchant overview</a>
        <a href="{{ route('account') }}">Your XLipa account</a>
    </nav>
    <div class="onboarding-heading">
        <div><p class="onboarding-eyebrow">YOUR MERCHANT WORKSPACE</p><h2>{{ $summary->businessName }}</h2><p>Keep track of your business details and onboarding progress.</p></div>
        <span class="onboarding-badge">Read-only</span>
    </div>
    <div class="onboarding-grid {{ $completionPreview ? 'onboarding-grid-with-preview' : '' }}">
    @if($completionPreview)
        <section class="onboarding-completion" aria-labelledby="completion-title" data-completion-preview>
            <div class="onboarding-card-top"><span class="onboarding-step" aria-hidden="true">01</span><span class="onboarding-badge">Saving unavailable</span></div>
            <h2 id="completion-title">Add your business name</h2>
            <p>A name helps identify your business in your merchant workspace.</p>
            <div class="onboarding-availability" id="completion-unavailable" role="status"><strong>Saving is not available yet.</strong><p>Your onboarding remains read-only. You can return to your merchant overview at any time.</p></div>
            <fieldset disabled aria-describedby="completion-help completion-unavailable">
                <legend class="onboarding-sr-only">Business name completion preview</legend>
                <label for="completion-business-name">Business name</label>
                <input id="completion-business-name" type="text" maxlength="200" autocomplete="off" placeholder="Your business name" disabled aria-describedby="completion-help completion-unavailable">
                <p id="completion-help" class="onboarding-field-help">Up to 200 characters. This field is disabled while saving is unavailable.</p>
                <button type="button" disabled aria-disabled="true">Save business name</button>
            </fieldset>
            <p class="onboarding-completion-note">Adding a name will not submit your application. Application submission is currently unavailable.</p>
        </section>
    @endif
    <section class="onboarding-status" aria-labelledby="onboarding-status-title">
    <p class="onboarding-eyebrow">WHERE THINGS STAND</p>
    <h2 id="onboarding-status-title">Your onboarding status</h2>
    <dl>
    <dt>Merchant status</dt><dd>{{ $summary->operationalStatus }}</dd>
    <dt>Verification status</dt><dd>{{ $summary->verificationStatus }}</dd>
    <dt>Current application</dt><dd>{{ $summary->applicationStatus ?? 'No current application is available.' }}</dd>
    @if($summary->attemptNumber !== null)
        <dt>Attempt number</dt><dd>{{ $summary->attemptNumber }}</dd>
        @if($summary->submittedAt)<dt>Submitted at</dt><dd>{{ $summary->submittedAt }}</dd>@endif
        @if($summary->reviewedAt)<dt>Reviewed at</dt><dd>{{ $summary->reviewedAt }}</dd>@endif
        @if($summary->publicFeedback)<dt>Public feedback</dt><dd>{{ $summary->publicFeedback }}</dd>@endif
    @endif
    </dl>
    <p class="onboarding-status-note">Application submission is currently unavailable. Viewing this page does not submit an application or enable payments.</p>
    @can('manageVerification', $merchant)
        <div class="onboarding-related-links">
            <a href="{{ route('merchants.applications', ['merchant' => $merchant->id]) }}">Application history and submission availability <span aria-hidden="true">&rarr;</span></a>
            <a href="{{ route('merchants.verification', ['merchant' => $merchant->id]) }}">Private contact and identity verification <span aria-hidden="true">&rarr;</span></a>
        </div>
        <p>Capture and delivery remain unavailable until their operational prerequisites are approved and ready.</p>
    @endcan
    </section>
    </div>
</div>
@endsection
