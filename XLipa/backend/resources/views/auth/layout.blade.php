<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="noindex,nofollow">
    <title>@yield('title') | XLipa</title>
    <link rel="icon" href="{{ asset('assets/xlipa-favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/xlipa-legal.css') }}">
    @hasSection('workspace')<link rel="stylesheet" href="{{ asset('assets/xlipa-dashboard.css') }}">@endif
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('assets/xlipa-product.css') }}">
</head>
<body @hasSection('workspace')class="merchant-dashboard workspace-document"@endif>
@php($documentMerchant = request()->route('merchant'))
@php($documentAdmin = request()->is('admin/*'))
@if($documentMerchant || $documentAdmin)
<a class="dash-skip" href="#workspace-content">Skip to content</a>
<div class="product-document-shell">
    <aside class="dash-sidebar" aria-label="{{ $documentAdmin ? 'Administrative' : 'Merchant' }} workspace">
        <a class="dash-brand" href="{{ route('home') }}"><img src="{{ asset('assets/xlipa-favicon.svg') }}" alt="" width="34" height="34"><span>XLipa<span class="dash-brand-dot">.</span></span></a>
        <div class="dash-workspace"><span class="dash-workspace-mark" aria-hidden="true">{{ $documentAdmin ? 'A' : 'M' }}</span><div><span class="dash-eyebrow">{{ $documentAdmin ? 'Platform administration' : 'Merchant workspace' }}</span><strong>{{ $documentAdmin ? 'Application review' : $documentMerchant->businessNameLabel() }}</strong></div></div>
        @if($documentAdmin)
            @include('admin.navigation')
        @else
            @include('merchants.navigation')
        @endif
    </aside>
@else
<header class="legal-header"><nav class="legal-nav" aria-label="Account navigation"><a class="legal-brand" href="{{ route('home') }}"><img src="{{ asset('assets/xlipa-favicon.svg') }}" alt=""><span>XLipa</span></a>@hasSection('workspace')<a href="{{ route('account') }}">Your account</a>@else<a href="{{ route('login') }}">Sign in</a>@endif</nav></header>
@hasSection('workspace')<a class="dash-skip" href="#workspace-content">Skip to content</a>@endif
@endif
<main class="legal-main" id="workspace-content" tabindex="-1">
    <section class="legal-content">
        <p class="dash-eyebrow">{{ $documentAdmin ? 'PLATFORM WORKSPACE' : ($documentMerchant ? 'YOUR BUSINESS' : 'YOUR XLIPA ACCOUNT') }}</p>
        <h1>@yield('title')</h1>
        @if(session('status'))<p class="product-alert" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<ul role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
        @yield('content')
        <footer class="product-footer"><span>XLipa · Built for Tanzania</span><a href="{{ route('pages.contact') }}">Contact support</a></footer>
    </section>
</main>
@if($documentMerchant || $documentAdmin)</div>@endif
</body>
</html>
