@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Complete business name')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/xlipa-onboarding.css') }}">
@endpush
@section('content')
<div class="onboarding-page">
    <nav class="onboarding-navigation"><a href="{{ route('merchants.dashboard', ['merchant' => $merchant->id]) }}">Merchant overview</a><a href="{{ route('merchants.onboarding', ['merchant' => $merchant->id]) }}">Onboarding status</a></nav>
    <section class="onboarding-completion">
        <p class="onboarding-eyebrow">FICTIONAL STAGING ONLY</p><h2>Complete business name</h2>
        <p>This initial completion does not submit an application or enable financial services.</p>
        @if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
        @if($command && ($merchant->business_name === '' || $resolving))
        <form method="post" action="{{ route($resolving ? 'staging.onboarding.resolve' : 'staging.onboarding.name', ['merchant' => $merchant->id]) }}">
            @csrf
            <input type="hidden" name="command" value="{{ $command['command'] }}">
            <label for="business-name">Business name authorized by the local custodian</label>
            <input id="business-name" name="business_name" value="{{ $command['name'] }}" maxlength="200" required>
            <button type="submit">{{ $resolving ? 'Resolve previous command' : 'Save business name' }}</button>
        </form>
        @elseif($merchant->business_name !== '')<p role="status">Business name completed. General name editing is unavailable.</p>
        @else<p role="status">A scoped fictional command must be issued by the local custodian before saving.</p>@endif
    </section>
</div>
@endsection
