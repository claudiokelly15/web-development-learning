<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') | XLipa</title>
    <link rel="icon" href="{{ asset('assets/xlipa-favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-wallet.css') }}">
    @stack('styles')
<link rel="stylesheet" href="{{ asset('assets/xlipa-product.css') }}">
</head>
<body class="merchant-dashboard financial-workspace">
<a class="dash-skip" href="#financial-content">Skip to financial records</a>
<div class="dash-shell">
    <aside class="dash-sidebar" aria-label="Merchant workspace">
        <a class="dash-brand" href="{{ route('home') }}" aria-label="XLipa home"><img src="{{ asset('assets/xlipa-favicon.svg') }}" alt="" width="34" height="34"><span>XLipa<span class="dash-brand-dot">.</span></span></a>
        <div class="dash-workspace"><span class="dash-workspace-mark" aria-hidden="true">{{ mb_substr($businessName ?? request()->route('merchant')->businessNameLabel(), 0, 1) }}</span><div><span class="dash-eyebrow">Merchant workspace</span><strong>{{ $businessName ?? request()->route('merchant')->businessNameLabel() }}</strong></div></div>
        @include('merchants.navigation')
        <div class="dash-sidebar-note"><span class="dash-eyebrow">Isolated fictional accounting</span><p>No real funds movement.</p></div>
    </aside>
    <div class="dash-main">
        <header class="dash-topbar"><div class="dash-breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>@yield('title')</strong></div><a href="{{ route('account') }}" class="dash-switch">Switch merchant</a></header>
        <main id="financial-content" class="dash-content" tabindex="-1">
            <div class="dash-page-heading"><div><p class="dash-eyebrow">MERCHANT WORKSPACE</p><h1>@yield('title')<span>.</span></h1><p>@yield('description')</p></div><span class="dash-currency">TZS only</span></div>
            <div class="dash-restriction" role="note"><strong>Fictional accounting only</strong><p>Mock confirmation and ledger posting do not prove settlement. Operational withdrawable funds remain TZS 0. Independently authenticated simulated settlement is shown separately when available.</p></div>
            @yield('content')
            <footer class="dash-footer"><span>XLipa · Merchant workspace</span><span>Times shown in EAT (UTC+03:00).</span></footer>
        </main>
    </div>
</div>
</body>
</html>
