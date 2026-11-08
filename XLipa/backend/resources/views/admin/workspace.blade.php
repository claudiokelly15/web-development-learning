<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $overview->roleLabel() }} {{ $overview->accessPage ? 'access scope' : 'overview' }} | XLipa</title>
    <link rel="icon" href="{{ asset('assets/xlipa-favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-admin.css') }}">
</head>
<body class="merchant-dashboard admin-workspace">
<a class="dash-skip" href="#admin-content">Skip to workspace</a>
<div class="dash-shell">
    <aside class="dash-sidebar" aria-label="{{ $overview->roleLabel() }} workspace">
        <a class="dash-brand" href="{{ route('home') }}" aria-label="XLipa home"><img src="{{ asset('assets/xlipa-favicon.svg') }}" alt="" width="34" height="34"><span>XLipa<span class="dash-brand-dot">.</span></span></a>
        <div class="dash-workspace"><span class="dash-workspace-mark" aria-hidden="true">{{ $overview->chief() ? 'C' : 'S' }}</span><div><span class="dash-eyebrow">Platform administration</span><strong>{{ $overview->roleLabel() }}</strong></div></div>
        <nav aria-label="Administrative navigation" class="dash-navigation">
            <p class="dash-nav-label">{{ $overview->chief() ? 'PLATFORM WORKSPACE' : 'YOUR WORKSPACE' }}</p>
            <ul>
                <li><a href="{{ route($overview->overviewRoute()) }}" @if(!$overview->accessPage)aria-current="page"@endif><span class="dash-nav-icon" aria-hidden="true">◫</span>Overview<span class="dash-nav-arrow" aria-hidden="true">↗</span></a></li>
                <li><a href="{{ route('admin.access') }}" @if($overview->accessPage)aria-current="page"@endif><span class="dash-nav-icon" aria-hidden="true">↳</span>Access scope<span class="dash-nav-arrow" aria-hidden="true">↗</span></a></li>
                @foreach($overview->unavailableNavigation() as $feature)
                    <li><button type="button" disabled aria-disabled="true"><span class="dash-nav-icon" aria-hidden="true">○</span>{{ $feature }}<span class="dash-nav-unavailable">Unavailable</span></button></li>
                @endforeach
                <li><a href="{{ route('pages.contact') }}"><span class="dash-nav-icon" aria-hidden="true">?</span>Support<span class="dash-nav-arrow" aria-hidden="true">↗</span></a></li>
            </ul>
        </nav>
        <div class="dash-sidebar-note"><span class="dash-eyebrow">Clear roles. Clear boundaries.</span><p>Platform access is separate<br>from merchant membership.</p></div>
    </aside>

    <div class="dash-main">
        <header class="dash-topbar">
            <div class="dash-breadcrumb"><span>{{ $overview->roleLabel() }}</span><span aria-hidden="true">/</span><strong>{{ $overview->accessPage ? 'Access scope' : 'Overview' }}</strong></div>
            <div class="dash-topbar-actions"><a href="{{ route('account') }}" class="dash-switch">Your account <span aria-hidden="true">↗</span></a><span class="dash-avatar" aria-hidden="true">{{ mb_substr($overview->accountName, 0, 1) }}</span></div>
        </header>
        <main class="dash-content" id="admin-content" tabindex="-1">
            <div class="dash-page-heading"><div><p class="dash-eyebrow">{{ $overview->chief() ? 'CHIEF ADMIN WORKSPACE' : 'STAFF ADMIN WORKSPACE' }}</p><h1>{{ $overview->accessPage ? 'Access scope' : 'Overview' }}<span>.</span></h1><p>{{ $overview->accessPage ? 'Understand the access attached to your account.' : 'A clear view of your administrative workspace.' }}</p></div><span class="dash-currency">TZS only</span></div>

            @if(!$overview->accessPage)
                <section class="dash-hero" aria-labelledby="workspace-title">
                    <div><span class="dash-hero-label"><span aria-hidden="true">◉</span> {{ $overview->chief() ? 'PLATFORM OVERSIGHT' : 'SCOPED ADMINISTRATION' }}</span><h2 id="workspace-title">{{ $overview->chief() ? 'Your platform workspace starts here.' : 'A focused workspace for your role.' }}</h2><p>Your current access covers this overview and your own access scope. Merchant records, review actions and financial tools are unavailable.</p><a class="dash-button dash-button-light" href="{{ route('admin.access') }}">View your access scope <span aria-hidden="true">↗</span></a></div>
                    <div class="dash-hero-aside" aria-hidden="true"><span class="dash-orbit"></span><span class="dash-orbit dash-orbit-inner"></span><span class="dash-orbit-label">{{ $overview->chief() ? 'CLEAR' : 'FOCUSED' }}<br>OVERSIGHT.</span></div>
                </section>

                <div class="dash-facts" aria-label="Your administrative access">
                    <section class="dash-fact"><span class="dash-fact-symbol" aria-hidden="true">↳</span><div><h2>Your platform role</h2><p>{{ $overview->roleLabel() }}</p><span>Current, active assignment</span></div></section>
                    <section class="dash-fact"><span class="dash-fact-symbol" aria-hidden="true">✓</span><div><h2>Account access</h2><p>Email verified</p><span>Your signed-in account is active</span></div></section>
                    <section class="dash-fact"><span class="dash-fact-symbol" aria-hidden="true">—</span><div><h2>Available capabilities</h2><p>Read-only</p><span>Overview and your own access scope</span></div></section>
                </div>
            @endif

            <div class="dash-detail-grid admin-detail-grid">
                <section class="dash-panel" aria-labelledby="scope-title">
                    <div class="dash-panel-heading"><div><p class="dash-eyebrow">EXPLICIT PERMISSIONS</p><h2 id="scope-title">{{ $overview->accessPage ? 'Your capability scope' : 'Access at a glance' }}</h2></div><span class="dash-badge">{{ $overview->roleLabel() }}</span></div>
                    <dl class="dash-status-list admin-capability-list">
                        @foreach($overview->capabilities() as $capability)
                            <div><dt>{{ $capability['label'] }}</dt><dd>{{ $capability['state'] }}</dd></div>
                        @endforeach
                    </dl>
                    <p class="dash-panel-note">A platform role does not grant access to another workspace or enable a future action. Merchant access still requires a separate active merchant membership.</p>
                </section>

                <section class="dash-panel dash-account" aria-labelledby="account-title">
                    <div class="dash-panel-heading"><div><p class="dash-eyebrow">SIGNED IN AS</p><h2 id="account-title">Your account</h2></div><span class="dash-account-mark" aria-hidden="true">{{ mb_substr($overview->accountName, 0, 1) }}</span></div>
                    <p class="dash-account-name">{{ $overview->accountName }}</p><p class="dash-account-email">{{ $overview->accountEmail }}</p><div class="dash-account-status"><span aria-hidden="true">✓</span> Email verified</div>
                    <p class="dash-panel-note">This workspace shows your own account and access facts. No merchant identity, application evidence or financial records are displayed.</p>
                    <a class="dash-button dash-button-outline" href="{{ route('account') }}">View your account <span aria-hidden="true">↗</span></a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="dash-button dash-button-outline" type="submit">Sign out <span aria-hidden="true">↗</span></button></form>
                </section>
            </div>

            @if(!$overview->accessPage)
                <section class="dash-resources" aria-labelledby="availability-title">
                    <div class="dash-section-heading"><div><p class="dash-eyebrow">OPERATIONS AVAILABILITY</p><h2 id="availability-title">What’s available today</h2></div><p>Only approved, implemented capabilities.</p></div>
                    <div class="dash-resource-grid admin-availability-grid">
                        <article class="dash-resource admin-unavailable"><span class="dash-badge">Unavailable</span><h3>Merchant operations</h3><p>Merchant directories, application review and identity processing are not available in this workspace.</p></article>
                        <article class="dash-resource admin-unavailable"><span class="dash-badge">Unavailable</span><h3>Financial operations</h3><p>Transactions, revenue, wallets, withdrawals and settlements are not connected. No financial totals are shown.</p></article>
                        <article class="dash-resource admin-unavailable"><span class="dash-badge">Unavailable</span><h3>Platform management</h3><p>Staff changes, audit-record browsing, provider tools and system settings are not enabled.</p></article>
                    </div>
                </section>
            @endif

            <section class="dash-financial-notice" aria-labelledby="boundary-title"><span class="dash-financial-mark" aria-hidden="true">◇</span><div><h2 id="boundary-title">Administrative access stays separate from financial authority.</h2><p>This workspace cannot approve merchants, process identity information, move funds or provision administrative accounts. Contact support for account assistance.</p><a class="admin-support-link" href="{{ route('pages.contact') }}">View support contact information <span aria-hidden="true">↗</span></a></div></section>
            <footer class="dash-footer"><span>XLipa · {{ $overview->roleLabel() }} workspace</span><span>Built for Tanzania. Supporting TZS.</span></footer>
        </main>
    </div>
</div>
</body>
</html>
