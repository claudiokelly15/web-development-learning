@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Merchant application history')
@section('content')
<p><a href="{{ route('merchants.onboarding', ['merchant' => $merchant->id]) }}">Onboarding status</a></p>
<h2>Application history</h2>
<p>{{ $merchant->business_name }} · Tanzania · TZS only</p>
<p>Submitted applications await review. There is no separate under-review state. Approval does not enable financial services.</p>
@forelse($applications as $application)
<section aria-labelledby="attempt-{{ $application->attempt_number }}">
    <h3 id="attempt-{{ $application->attempt_number }}">Attempt {{ $application->attempt_number }}</h3>
    <dl>
        <dt>Status</dt><dd>{{ \App\ViewModels\ApplicationHistoryEntry::label($application->getRawOriginal('status')) }}</dd>
        <dt>Application version</dt><dd>{{ $application->profile_version }}</dd>
        <dt>Current attempt</dt><dd>{{ $application->current_slot ? 'Yes' : 'No' }}</dd>
        <dt>Submitted</dt><dd>{{ $application->submitted_at?->toIso8601String() ?? 'Not submitted' }}</dd>
        <dt>Reviewed</dt><dd>{{ $application->reviewed_at?->toIso8601String() ?? 'No review recorded' }}</dd>
    </dl>
</section>
@empty
<p>No application history is available.</p>
@endforelse
{{ $applications->links() }}
<section aria-labelledby="submission-title">
    <h3 id="submission-title">Submission unavailable</h3>
    <ul>@foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
    <button type="button" disabled aria-disabled="true">Submit application — unavailable</button>
    <p><a href="{{ route('merchants.verification', ['merchant' => $merchant->id]) }}">Private verification status</a></p>
</section>
@endsection
