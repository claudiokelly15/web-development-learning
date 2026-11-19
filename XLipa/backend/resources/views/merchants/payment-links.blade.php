<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Payment links | XLipa</title>
    <link rel="icon" href="{{ asset('assets/xlipa-favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-checkout.css') }}">
    <script src="{{ asset('assets/xlipa-checkout.js') }}" defer></script>
</head>
<body class="payment-workspace">
<a class="payment-skip" href="#links">Skip to payment links</a>
<header><div class="wrap nav"><a class="logo" href="{{ route('home') }}"><img class="logo-mark" src="{{ asset('assets/xlipa-favicon.svg') }}" alt="" width="42" height="42"><span class="l">XLipa<span>.</span></span></a><a href="{{ route('merchants.dashboard', ['merchant' => $merchantId]) }}">Merchant overview</a></div></header>
<main id="links" class="payment-container payment-wide" tabindex="-1">
    <div class="payment-heading"><p class="payment-eyebrow">MERCHANT WORKSPACE · ISOLATED TESTING</p><h1>Payment links<span>.</span></h1><p>Create a fixed TZS request and follow its fictional payment outcome.</p></div>
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
</main>
</body>
</html>
