@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Merchant onboarding status')
@section('content')
<p><a href="{{ route('account') }}">Your XLipa account</a></p>
<h2>{{ $summary->businessName }}</h2>
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
@can('manageVerification', $merchant)<p><a href="{{ route('merchants.applications', ['merchant' => $merchant->id]) }}">Application history and submission availability</a></p>@endcan
@can('manageVerification', $merchant)<p><a href="{{ route('merchants.verification', ['merchant' => $merchant->id]) }}">Private contact and identity verification</a></p><p>Capture and delivery remain unavailable until their operational prerequisites are approved and ready.</p>@endcan
@endsection
