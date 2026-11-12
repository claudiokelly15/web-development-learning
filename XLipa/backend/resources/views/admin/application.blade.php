@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Chief Admin application review')
@section('content')
<p><a href="{{ route('admin.applications') }}">Application list</a> / <a href="{{ route('admin.chief') }}">Chief Admin workspace</a></p>
<h2>{{ $application->business_name }}</h2>
<p>Current business name; historical protected profile remains redacted. Tanzania · TZS only.</p>
<dl>
<dt>Status</dt><dd>{{ \App\ViewModels\ApplicationHistoryEntry::label($application->status) }}</dd>
<dt>Attempt</dt><dd>{{ $application->attempt_number }}</dd>
<dt>Submitted profile version</dt><dd>{{ $application->profile_version }}</dd>
</dl>
<h2>Verification and evidence integrity</h2>
<p>{{ $evidence }}</p>
<dl><dt>Recorded merchant verification status</dt><dd>{{ $application->verification_status }}</dd>
<dt>Recorded operational status</dt><dd>{{ $application->operational_status }}</dd>
<dt>Profile version comparison</dt><dd>{{ $application->profile_version === $application->current_profile_version ? 'Versions agree; current readiness unverified' : 'Profile changed since submission; review held' }}</dd></dl>
<p>Cryptographic integrity, trusted issuance and current verification eligibility are unverified in this read-only view. Recorded evidence and recorded approval do not establish identity or payment eligibility. Protected identity, phone, email, evidence envelopes and internal notes are withheld.</p>
<h2>Submission timeline</h2>
<ol><li>Submitted: {{ $application->submitted_at ?? 'Not recorded' }} (UTC)</li><li>Reviewed: {{ $application->reviewed_at ?? 'No review recorded' }} (UTC)</li></ol>
<h2>Read-only audit history</h2>
<p>Application submission and review events only. Actor identifiers, request identifiers and metadata are redacted.</p>
@forelse($history as $event)
<p>{{ $event->event_type === 'application.submitted' ? 'Submission recorded' : 'Review recorded' }} · {{ $event->occurred_at }} (UTC)</p>
@empty
<p>No application audit events recorded.</p>
@endforelse
{{ $history->links() }}
<h2>Review actions unavailable</h2>
<p>Approval, rejection and requests for information remain blocked pending an approved decision contract and operational admission. Live payments remain disabled.</p>
@endsection
