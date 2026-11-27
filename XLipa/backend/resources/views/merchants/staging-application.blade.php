@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Fictional application')
@section('content')
<section class="dash-panel" data-application="{{ $application->id }}"><h2>Fictional merchant application</h2>
<p role="status">Persisted status: {{ $application->status->value }}</p>
<p>Fictional verification does not establish real phone possession or identity, or enable payments.</p>
<p><a href="{{ route('merchants.verification', ['merchant' => $merchant->id]) }}">Private fictional verification</a></p>
<p><a href="{{ route('staging.onboarding.form', ['merchant' => $merchant->id]) }}">Business name completion</a></p>
<form method="post" action="{{ route('staging.application.submit', ['merchant' => $merchant->id]) }}">@csrf<button type="submit" @disabled($application->status->value !== 'draft')>Submit custodian-authorized fictional application</button></form>
<p>The local custodian must issue the exact submission command after verification. Repeating a consumed command is denied.</p></section>
@endsection
