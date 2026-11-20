@extends('merchants.financial-layout')
@section('title', 'Wallet')
@section('description', 'Evidence from your mock payments and sealed financial ledger.')
@section('content')
@php($format = \App\ViewModels\MerchantFinancialPresentation::class)
<section class="wallet-grid" aria-label="Fictional financial position">
    @foreach([
        ['Confirmed fictional payments', 'confirmed_fictional_payment_tzs', 'Authenticated mock confirmations, including value not yet accounted.'],
        ['Ledger-posted pending obligation', 'pending_merchant_obligation_tzs', 'Merchant liability recorded in sealed journals. Not available funds.'],
        ['Simulated clearing position', 'simulated_clearing_receivable_tzs', 'The asset side of the same journals. Do not add it to the obligation.'],
        ['Confirmed but not yet accounted', 'confirmed_unaccounted_tzs', 'A subset of confirmed payments awaiting ledger posting.'],
        ['Withdrawable balance', 'withdrawable_tzs', 'Withdrawals are unavailable in this isolated scope.'],
    ] as [$label, $key, $explanation])
        <article class="dash-panel wallet-metric"><h2>{{ $label }}</h2><p class="wallet-value">{{ $format::tzs($position[$key]) }}</p><p class="dash-panel-note">{{ $explanation }}</p></article>
    @endforeach
    <article class="dash-panel wallet-metric"><h2>Settlement status</h2><p class="wallet-value wallet-unproven">Unproven</p><p class="dash-panel-note">No settlement evidence or real cash availability is established.</p></article>
</section>
<section class="dash-panel wallet-section" aria-labelledby="payment-states"><h2 id="payment-states">Payment request states</h2><p class="dash-panel-note">Requested amounts describe payment activity. They are not additional balances or available funds.</p>
    <dl class="dash-status-list">
        @foreach(['pending', 'unknown', 'failed', 'confirmed'] as $state)
            <div><dt>{{ $format::status($state) }}</dt><dd>{{ $payment_states[$state]['count'] ?? 0 }} requests · {{ $format::tzs($payment_states[$state]['requested_tzs'] ?? 0) }}</dd></div>
        @endforeach
        <div><dt>Unresolved checkout reservations without a payment intent</dt><dd>{{ $unresolved_checkout_reservations }}</dd></div>
    </dl>
    <p class="dash-panel-note">Unresolved requests require review. Reading this page never retries payments or posts accounting.</p>
    <a class="dash-button dash-button-outline" href="{{ route('isolated.transactions', ['merchant' => $merchantId]) }}">View transaction history</a>
</section>
@endsection
