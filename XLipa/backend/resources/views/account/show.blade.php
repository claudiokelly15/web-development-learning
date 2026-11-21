@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Your XLipa account')
@section('content')
<p>Signed in as {{ auth()->user()->name }}.</p>
<div class="product-alert"><strong>Email verified</strong><p>Business profile completion, merchant review and live payment activation are separate steps.</p></div>
@can('viewOwnWorkspaceAccess', \App\Models\PlatformRoleAssignment::class)<div class="product-toolbar"><a href="{{ route('admin.index') }}">Open administrative workspace &rarr;</a></div>@endcan
<h2>Your merchant workspaces</h2><p>Choose a business to review its account and recorded activity.</p>
@forelse($merchants as $merchant)
<section><h3>{{ $merchant->businessNameLabel() }}</h3><p><span class="product-badge">{{ ucfirst($merchant->operational_status->value) }}</span> <span class="product-badge">{{ ucfirst($merchant->verification_status->value) }}</span></p><div class="product-toolbar"><a href="{{ route('merchants.dashboard', ['merchant' => $merchant->id]) }}">Open dashboard &rarr;</a><a href="{{ route('merchants.onboarding', ['merchant' => $merchant->id]) }}">Business profile</a></div></section>
@empty<div class="product-empty"><h2>No active merchant memberships.</h2><p>Contact support if you expected access to a business.</p></div>@endforelse
<section id="security"><h2>Account security</h2><dl><dt>Account email</dt><dd>{{ auth()->user()->email }}</dd><dt>Email verification</dt><dd>Verified</dd></dl><p>To recover your password, sign out and use “Forgot password?” on the sign-in page. A separate security-settings editor is not yet implemented.</p></section>
<section id="notifications"><h2>Notifications and preferences</h2><p>The notification center and preference settings are not yet implemented. No delivery or unread count is assumed.</p><a href="{{ route('pages.contact') }}">Contact XLipa support &rarr;</a></section>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
@endsection
