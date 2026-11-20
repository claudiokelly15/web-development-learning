@extends('merchants.financial-layout')
@section('title', 'Transaction detail')
@section('description', 'Payment and accounting evidence for this merchant only.')
@section('content')
@php($format = \App\ViewModels\MerchantFinancialPresentation::class)
<section class="dash-panel" aria-labelledby="transaction-title"><h2 id="transaction-title">{{ $format::status($transaction['status']) }}</h2><p class="wallet-value">{{ $format::tzs($transaction['amount_tzs']) }}</p><p>{{ $transaction['description'] ?? 'No payment link description' }}</p>
    <dl class="dash-status-list wallet-detail">
        <div><dt>Payment reference</dt><dd>{{ $transaction['payment_reference'] ?? 'No payment intent recorded' }}</dd></div>
        <div><dt>Payment initiated / checkout reserved</dt><dd>{{ $format::time($transaction['created_at']) }}</dd></div>
        <div><dt>Dispatch state</dt><dd>{{ $transaction['dispatch_state'] ?? 'No provider request recorded' }}</dd></div>
        <div><dt>Provider identifier</dt><dd>{{ $transaction['provider'] === 'fictional_mock' ? 'Fictional mock provider' : ($transaction['provider'] ?? 'Not recorded') }}</dd></div>
        <div><dt>Payment link</dt><dd>{{ $transaction['payment_link_id'] ?? 'No link association' }}</dd></div>
        <div><dt>Checkout attempt</dt><dd>{{ $transaction['checkout_attempt_id'] ?? 'No checkout association' }}</dd></div>
        <div><dt>Authenticated mock confirmation</dt><dd>{{ $format::time($transaction['confirmed_at']) }}</dd></div>
        <div><dt>Accounting status</dt><dd>{{ $format::status($transaction['accounting_status']) }}</dd></div>
        <div><dt>Journal reference</dt><dd>{{ $transaction['journal_id'] ?? 'Ledger not yet posted' }}</dd></div>
        <div><dt>Ledger posted at</dt><dd>{{ $format::time($transaction['posted_at']) }}</dd></div>
        <div><dt>Settlement</dt><dd>Unproven</dd></div>
        <div><dt>Withdrawable funds</dt><dd>{{ $format::tzs($transaction['withdrawable_tzs']) }}</dd></div>
    </dl>
    <p class="dash-panel-note">Pending means confirmation has not been received. Failed is a recorded mock-provider failure. Unresolved means no terminal outcome is assumed. Ledger posting records fictional pending obligations only.</p>
    <a class="dash-button dash-button-outline" href="{{ route('isolated.transactions', ['merchant' => $merchantId]) }}">Back to transaction history</a>
</section>
@endsection
