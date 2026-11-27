@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Fictional Chief decision')
@section('content')
<section class="dash-panel"><h2>Fictional application decision</h2><p>{{ $application->business_name }} · {{ $application->status }}</p>
<p><a href="{{ route('admin.applications.show', ['application' => $application->id]) }}">Review submitted evidence and history</a></p>
<p>The local custodian separately authorizes the exact outcome/reason and short-lived Chief assurance. This is not operational MFA.</p>
<form method="post" action="{{ route('staging.application.decision', ['application' => $application->id]) }}">@csrf<button type="submit">Execute authorized fictional decision</button></form>
<p>Approval cannot enable real identity, SMS, payments, withdrawals or settlements.</p></section>
@endsection
