@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Chief Admin application review preparation')
@section('content')
<p><a href="{{ route('admin.chief') }}">Chief Admin workspace</a></p>
<h2>Application review preparation</h2>
<p>Redacted submitted application history · Tanzania · TZS only</p>
<p>Your own and membership-linked applications are excluded. Drafts are private to merchants.</p>
<p>Submitted / awaiting review is one supported state. A separate under-review state is unavailable. Recorded approval does not grant payment eligibility.</p>
@forelse($applications as $application)
<section aria-labelledby="application-{{ $application->id }}">
    <h3 id="application-{{ $application->id }}">{{ $application->business_name }} · Attempt {{ $application->attempt_number }}</h3>
    <dl>
        <dt>Status</dt><dd>{{ \App\ViewModels\ApplicationHistoryEntry::label($application->status) }}</dd>
        <dt>Submitted</dt><dd>{{ $application->submitted_at ?? 'Not recorded' }} (UTC)</dd>
        <dt>Reviewed</dt><dd>{{ $application->reviewed_at ?? 'No review recorded' }} (UTC)</dd>
    </dl>
</section>
@empty
<p>No applications are available in your review scope.</p>
@endforelse
{{ $applications->links() }}
<h3>Review actions unavailable</h3>
<p>Approval, rejection, requests for information and protected evidence access require separately approved review authorization and evidence controls.</p>
@endsection
