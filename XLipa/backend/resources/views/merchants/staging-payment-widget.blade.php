@extends('merchants.financial-layout')
@section('title', 'Fictional QR and Pay Button')
@section('description', 'An existing fixed-amount link with local fictional checkout only.')
@section('content')
<section class="payment-card"><h2>{{ $link['description'] }}</h2><p>TZS {{ number_format($link['amount_tzs'], 0, '.', ',') }}</p>
<div role="img" aria-label="Fictional checkout QR code">{!! $qr !!}</div>
<p>{!! $button !!}</p><p><a href="{{ $url }}">{{ $url }}</a></p><p>Use a separate, custodian-admitted fictional customer session. No real money, credentials or settlement.</p></section>
@endsection
