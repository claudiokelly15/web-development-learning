@extends('auth.layout')
@section('workspace', 'true')
@section('title', 'Fictional financial oversight')
@section('content')
<p>Read-only, independently granted Chief scope. No operational financial approvals or payout controls.</p>
@include('merchants.funds-tables')
<section class="product-empty"><h2>Financial exceptions</h2>@forelse($funds['exceptions'] as $exception)<p>{{ $exception['withdrawal_reference'] }}: conflicting authenticated terminal outcomes. No automatic retry or release.</p>@empty<p>No conflicting terminal payout evidence in this authorized fixture.</p>@endforelse</section>
@endsection
