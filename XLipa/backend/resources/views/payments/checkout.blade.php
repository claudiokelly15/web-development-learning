<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Mock checkout | XLipa</title>
    <link rel="icon" href="{{ asset('assets/xlipa-favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-checkout.css') }}">
    <script src="{{ asset('assets/xlipa-checkout.js') }}" defer></script>
<link rel="stylesheet" href="{{ asset('assets/xlipa-product.css') }}">
</head>
<body class="payment-workspace">
<a class="payment-skip" href="#checkout">Skip to checkout</a>
<header><div class="wrap nav"><a class="logo" href="{{ route('home') }}"><img class="logo-mark" src="{{ asset('assets/xlipa-favicon.svg') }}" alt="" width="42" height="42"><span class="l">XLipa<span>.</span></span></a><span class="payment-tag">Fictional test checkout</span></div></header>
<main id="checkout" class="payment-container" tabindex="-1">
    <div class="payment-heading"><p class="payment-eyebrow">PAYMENT REQUEST</p><h1>Pay {{ $link['business_name'] }}</h1><p>This is an isolated mock payment. No real money will move.</p></div>
    <section class="payment-card" aria-labelledby="amount-title">
        <p id="amount-title" class="payment-eyebrow">AMOUNT TO PAY</p><p class="payment-amount"><span>TZS</span> {{ number_format($link['amount_tzs'], 0, '.', ',') }}</p>
        <p class="payment-description">{{ $link['description'] }}</p>
        @if($result === null)
            <form method="post" action="{{ route(($paymentRoutePrefix ?? 'isolated').'.checkout.initiate', ['token' => $link['public_token']]) }}" data-payment-submit>
                @csrf
                <input type="hidden" name="request_nonce" value="{{ $nonce }}">
                <button class="btn primary payment-submit" type="submit" data-loading-label="Creating mock payment…">Start mock payment</button>
                <p class="payment-help" role="status" aria-live="polite" data-payment-progress>Do not enter payment credentials. This uses a fictional provider only.</p>
            </form>
        @else
            @php
                $messages = [
                    'pending' => ['Payment pending', 'The payment request is recorded. Confirmation has not been received.'],
                    'confirmed' => ['Fictional payment confirmed', 'Authenticated mock-provider evidence confirms this payment. This does not prove settlement or create withdrawable funds.'],
                    'failed' => ['Mock payment failed', 'The fictional provider reported failure. This checkout will not automatically retry.'],
                    'unknown' => ['Payment outcome unknown', 'The provider acknowledgement is unresolved. Do not start another payment; check this status page.'],
                    'unresolved' => ['Request outcome unresolved', 'Your checkout request is retained for review. No payment failure or success is assumed.'],
                ];
                [$title, $message] = $messages[$result['status']];
            @endphp
            <div class="payment-status" data-state="{{ $result['status'] }}" role="status" aria-live="polite"><h2>{{ $title }}</h2><p>{{ $message }}</p></div>
            @if($result['payment_reference'])<p class="payment-reference">Payment reference <span>{{ $result['payment_reference'] }}</span></p>@endif
            <a class="btn ghost" href="{{ route($checkoutStatusRoute ?? 'isolated.checkout.status', ['token' => $link['public_token'], 'attempt' => $result['attempt_id']]) }}">Check current status</a>
        @endif
    </section>
    <p class="payment-footer">No PIN, NIN, bank details or mobile-money credentials are requested.</p>
</main>
</body>
</html>
