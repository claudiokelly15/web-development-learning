<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Merchant overview | XLipa</title>
    <link rel="icon" href="{{ asset('assets/xlipa-favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-dashboard.css') }}">
</head>
<body class="merchant-dashboard">
<a class="dash-skip" href="#dashboard-content">Skip to overview</a>
<div class="dash-shell">
    <aside class="dash-sidebar" aria-label="Merchant workspace">
        <a class="dash-brand" href="{{ route('home') }}" aria-label="XLipa home"><img src="{{ asset('assets/xlipa-favicon.svg') }}" alt="" width="34" height="34"><span>XLipa<span class="dash-brand-dot">.</span></span></a>
        <div class="dash-workspace"><span class="dash-workspace-mark" aria-hidden="true">{{ mb_substr($overview->onboarding->businessName, 0, 1) }}</span><div><span class="dash-eyebrow">Merchant workspace</span><strong>{{ $overview->onboarding->businessName }}</strong></div></div>
        <nav aria-label="Merchant navigation" class="dash-navigation">
            <p class="dash-nav-label">WORKSPACE</p>
            <ul>
                <li><a href="{{ route('merchants.dashboard', ['merchant' => $merchantId]) }}" aria-current="page"><span aria-hidden="true" class="dash-nav-icon">◫</span>Overview<span class="dash-nav-arrow" aria-hidden="true">↗</span></a></li>
                @foreach(['Payments', 'Payment Links', 'Checkout', 'QR Payments', 'Wallet', 'Settlements', 'Reports', 'Developers / API', 'Settings'] as $feature)
                    <li><button type="button" disabled aria-disabled="true"><span aria-hidden="true" class="dash-nav-icon">○</span>{{ $feature }}<span class="dash-nav-unavailable">Unavailable</span></button></li>
                @endforeach
                <li><a href="{{ route('pages.contact') }}"><span aria-hidden="true" class="dash-nav-icon">?</span>Support<span class="dash-nav-arrow" aria-hidden="true">↗</span></a></li>
            </ul>
        </nav>
        <div class="dash-sidebar-note"><span class="dash-eyebrow">Built for your business</span><p>A clear view of your account.<br>Financial tools are not available yet.</p></div>
    </aside>

    <div class="dash-main">
        <header class="dash-topbar">
            <div class="dash-breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>Overview</strong></div>
            <div class="dash-topbar-actions"><a href="{{ route('account') }}" class="dash-switch">Switch merchant <span aria-hidden="true">↗</span></a><span class="dash-avatar" aria-hidden="true">{{ mb_substr($overview->accountName, 0, 1) }}</span></div>
        </header>

        <main id="dashboard-content" class="dash-content" tabindex="-1">
            <div class="dash-page-heading"><div><p class="dash-eyebrow">YOUR MERCHANT ACCOUNT</p><h1>Overview<span>.</span></h1><p>Your business, at a glance.</p></div><span class="dash-currency">TZS only</span></div>

            <section class="dash-hero" aria-labelledby="readiness-title">
                <div><span class="dash-hero-label"><span aria-hidden="true">◉</span> ACCOUNT READINESS</span><h2 id="readiness-title">{{ $overview->readinessTitle() }}</h2><p>{{ $overview->readinessDescription() }}</p><a class="dash-button dash-button-light" href="{{ route('merchants.onboarding', ['merchant' => $merchantId]) }}">View onboarding status <span aria-hidden="true">↗</span></a></div>
                <div class="dash-hero-aside" aria-hidden="true"><span class="dash-orbit"></span><span class="dash-orbit dash-orbit-inner"></span><span class="dash-orbit-label">YOUR NEXT<br>CHAPTER.</span></div>
            </section>

            @if($overview->restricted())
                <div class="dash-restriction" role="status"><strong>Merchant {{ $overview->onboarding->operationalStatus }}</strong><p>This is a read-only overview. Contact support for help with merchant access.</p></div>
            @endif

            <div class="dash-facts" aria-label="Account access facts">
                <section class="dash-fact"><span class="dash-fact-symbol" aria-hidden="true">✓</span><div><h2>Account access</h2><p>Email verified</p><span>Your signed-in account is active</span></div></section>
                <section class="dash-fact"><span class="dash-fact-symbol" aria-hidden="true">↳</span><div><h2>Your merchant role</h2><p>{{ ucfirst($overview->membershipRole) }}</p><span>Active membership in this workspace</span></div></section>
                <section class="dash-fact"><span class="dash-fact-symbol" aria-hidden="true">—</span><div><h2>Financial services</h2><p>Unavailable</p><span>No payments or balances to display</span></div></section>
            </div>

            <div class="dash-detail-grid">
                <section class="dash-panel" aria-labelledby="onboarding-title">
                    <div class="dash-panel-heading"><div><p class="dash-eyebrow">WHERE THINGS STAND</p><h2 id="onboarding-title">Merchant onboarding</h2></div><span class="dash-badge">Read-only</span></div>
                    <dl class="dash-status-list">
                        <div><dt><span class="dash-status-dot" aria-hidden="true"></span>Merchant status</dt><dd>{{ ucfirst($overview->onboarding->operationalStatus) }}</dd></div>
                        <div><dt><span class="dash-status-dot" aria-hidden="true"></span>Verification status</dt><dd>{{ ucfirst($overview->onboarding->verificationStatus) }}</dd></div>
                        <div><dt><span class="dash-status-dot" aria-hidden="true"></span>Current application</dt><dd>{{ $overview->onboarding->applicationStatus ?? 'No current application' }}</dd></div>
                        @if($overview->onboarding->attemptNumber !== null)
                            <div><dt>Attempt number</dt><dd>{{ $overview->onboarding->attemptNumber }}</dd></div>
                            @if($overview->onboarding->submittedAt)<div><dt>Submitted at</dt><dd><time datetime="{{ $overview->onboarding->submittedAt }}">{{ $overview->onboarding->submittedAt }}</time></dd></div>@endif
                            @if($overview->onboarding->reviewedAt)<div><dt>Reviewed at</dt><dd><time datetime="{{ $overview->onboarding->reviewedAt }}">{{ $overview->onboarding->reviewedAt }}</time></dd></div>@endif
                        @endif
                    </dl>
                    @if($overview->onboarding->publicFeedback)
                        <div class="dash-feedback"><h3>Application feedback</h3><p>{{ $overview->onboarding->publicFeedback }}</p></div>
                    @endif
                    <p class="dash-panel-note">This summary does not confirm phone ownership or eligibility to accept payments.</p>
                    @if($verificationAvailable)<p><a href="{{ route('merchants.verification', ['merchant' => $merchantId]) }}">Manage private contact and identity verification</a></p>@else<p class="dash-panel-note">Private verification requires separate operational readiness. Owners can find its availability on the onboarding status page.</p>@endif
                </section>

                <section class="dash-panel dash-account" aria-labelledby="account-title">
                    <div class="dash-panel-heading"><div><p class="dash-eyebrow">SIGNED IN AS</p><h2 id="account-title">Your account</h2></div><span class="dash-account-mark" aria-hidden="true">{{ mb_substr($overview->accountName, 0, 1) }}</span></div>
                    <p class="dash-account-name">{{ $overview->accountName }}</p><p class="dash-account-email">{{ $overview->accountEmail }}</p>
                    <div class="dash-account-status"><span aria-hidden="true">✓</span> Email verified</div>
                    <p class="dash-panel-note">Your merchant role applies to this workspace. Account verification and merchant approval are separate.</p>
                    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="dash-button dash-button-outline">Sign out <span aria-hidden="true">↗</span></button></form>
                </section>
            </div>

            <section class="dash-resources" aria-labelledby="actions-title">
                <div class="dash-section-heading"><div><p class="dash-eyebrow">KEEP MOVING</p><h2 id="actions-title">Available account actions</h2></div><p>Only the tools ready for your account.</p></div>
                <div class="dash-resource-grid">
                    <a class="dash-resource" href="{{ route('account') }}"><span class="dash-resource-icon" aria-hidden="true">⇄</span><h3>Choose a merchant <span aria-hidden="true">↗</span></h3><p>View the merchants your account can access.</p></a>
                    <a class="dash-resource" href="{{ route('pages.developers') }}"><span class="dash-resource-icon" aria-hidden="true">&lt;/&gt;</span><h3>Read the developer guide <span aria-hidden="true">↗</span></h3><p>Public product information. API tools remain unavailable.</p></a>
                    <a class="dash-resource" href="{{ route('pages.contact') }}"><span class="dash-resource-icon" aria-hidden="true">?</span><h3>Get in touch <span aria-hidden="true">↗</span></h3><p>Find contact information and account support.</p></a>
                </div>
            </section>

            <section class="dash-financial-notice" aria-labelledby="financial-title"><span class="dash-financial-mark" aria-hidden="true">◇</span><div><h2 id="financial-title">A workspace first. Financial tools when they’re ready.</h2><p>Payments, links, checkout, QR payments, wallet, settlements and reports are unavailable. No payment activity, balance or transaction totals are shown here.</p></div></section>
            <footer class="dash-footer"><span>XLipa · Merchant workspace</span><span>Built for Tanzania. Supporting TZS.</span></footer>
        </main>
    </div>
</div>
</body>
</html>
