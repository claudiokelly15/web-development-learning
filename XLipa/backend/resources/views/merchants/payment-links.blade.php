@extends('merchants.financial-layout')
@section('title', 'Payment links')
@section('description', 'Create a fixed TZS request and follow its fictional payment outcome.')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/xlipa-checkout.css') }}">
<script src="{{ asset('assets/xlipa-checkout.js') }}" defer></script>
@endpush
@section('content')

<div class="payment-workspace">
    <div class="payment-grid">
        <section class="payment-card" aria-labelledby="create-title"><h2 id="create-title">Create a payment link</h2>
            <form method="post" action="{{ route('isolated.links.create', ['merchant' => $merchantId]) }}" data-payment-submit>
                @csrf
                <input type="hidden" name="request_key" value="{{ $requestKey }}"><input type="hidden" name="currency" value="TZS">
                <label for="amount_tzs">Amount in whole TZS</label><input id="amount_tzs" name="amount_tzs" type="number" inputmode="numeric" min="1" max="1000000000000" step="1" required>
                <label for="description">Payment description</label><input id="description" name="description" type="text" maxlength="200" required>
                <button type="submit" class="btn primary payment-submit" data-loading-label="Creating link…">Create mock payment link</button>
                <p class="payment-help" role="status" aria-live="polite" data-payment-progress>Fixed amount. No fees or settlement are calculated.</p>
            </form>
        </section>
        <section class="payment-card" aria-labelledby="existing-title"><h2 id="existing-title">Your links</h2>
            @forelse($links as $link)
                <article class="payment-link-row"><h3>{{ $link['description'] }}</h3><p><strong>TZS {{ number_format($link['amount_tzs'], 0, '.', ',') }}</strong> · {{ ucfirst($link['state']) }}</p>
                    <a class="payment-url" href="{{ route('isolated.checkout.open', ['token' => $link['public_token']]) }}">{{ route('isolated.checkout.open', ['token' => $link['public_token']]) }}</a>
                    <p class="payment-help">Open checkout in a separate fictional customer session.</p>
                    <details class="product-link-details"><summary>Link details</summary><dl><dt>Link reference</dt><dd>{{ $link['id'] }}</dd><dt>Created (EAT)</dt><dd>{{ \App\ViewModels\MerchantFinancialPresentation::time($link['created_at']) }}</dd><dt>Expiry (EAT)</dt><dd>{{ $link['expires_at'] === null ? 'No expiry set' : \App\ViewModels\MerchantFinancialPresentation::time($link['expires_at']) }}</dd><dt>Link state</dt><dd>{{ ucfirst($link['state']) }}. Expired or disabled links cannot start a new payment.</dd></dl></details>
                    @if($link['state'] === 'active')<form method="post" action="{{ route('isolated.links.disable', ['merchant' => $merchantId, 'link' => $link['id']]) }}">@csrf<button class="btn ghost" type="submit">Disable new payments</button></form>@endif
                </article>
            @empty<p class="payment-help">No payment links yet.</p>@endforelse
        </section>
    </div>
    <section class="payment-card payment-history" aria-labelledby="history-title"><h2 id="history-title">Payment history</h2>
        @forelse($attempts as $attempt)
            <article class="payment-link-row"><h3>{{ ucfirst($attempt['status'] ?? 'unresolved') }}</h3><p class="payment-reference">{{ $attempt['payment_reference'] ?? 'Payment creation unresolved' }}</p>
                <p>{{ $attempt['currency'] ?? 'TZS' }} {{ $attempt['amount_tzs'] === null ? '—' : number_format($attempt['amount_tzs'], 0, '.', ',') }}</p>
                @if($attempt['journal_id'])<p class="payment-help">Fictional pending accounting recorded: {{ $attempt['journal_id'] }}</p>
                @elseif($attempt['status'] === 'confirmed')
                    <form method="post" action="{{ route('isolated.links.consume', ['merchant' => $merchantId, 'attempt' => $attempt['id']]) }}">@csrf<button class="btn ghost" type="submit">Record confirmed mock payment</button></form>
                    <form method="post" action="{{ route('isolated.links.reconcile', ['merchant' => $merchantId, 'attempt' => $attempt['id']]) }}">@csrf<button class="btn ghost" type="submit">Reconcile accounting outcome</button></form>
                @endif
            </article>
        @empty<p class="payment-help">No checkout attempts yet.</p>@endforelse
        <p class="payment-help">Withdrawable amount: TZS 0. Mock confirmation does not prove settlement.</p>
    </section>

</div>
@endsection
