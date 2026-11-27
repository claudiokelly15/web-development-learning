@extends('merchants.financial-layout')
@section('title', 'Fictional transactions')
@section('description', 'Persisted payment evidence and immutable pending accounting, TZS only.')
@section('content')
<section class="payment-card"><h2>Financial position</h2><dl>
<dt>Confirmed fictional payments</dt><dd>TZS {{ number_format($overview['position']['confirmed_fictional_payment_tzs'], 0, '.', ',') }}</dd>
<dt>Pending merchant obligation</dt><dd>TZS {{ number_format($overview['position']['pending_merchant_obligation_tzs'], 0, '.', ',') }}</dd>
<dt>Operational withdrawable</dt><dd>TZS 0</dd></dl><p>Settlement remains unproven. No withdrawals, fees or operational funds are enabled.</p></section>
@foreach($transactions as $transaction)<section class="payment-card"><h2>{{ ucfirst($transaction['status']) }}</h2><p>TZS {{ number_format($transaction['amount_tzs'], 0, '.', ',') }}</p><p class="payment-reference">{{ $transaction['payment_reference'] ?? 'Unresolved checkout' }}</p><p>Accounting: {{ $transaction['accounting_status'] }}</p></section>@endforeach
@endsection
