@extends($chiefView ? 'auth.layout' : 'merchants.financial-layout')
@section('workspace', 'true')
@section('title', $chiefView ? 'API governance' : 'Developer tools')
@section('description', 'Verified fictional integrations. Operational APIs and external delivery remain disabled.')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/xlipa-checkout.css') }}">
@endpush
@section('content')
<div class="payment-workspace">
    <section class="payment-card"><h2>{{ $chiefView ? 'Chief Admin API governance' : 'Merchant integrations' }}</h2>
        <p>Isolated functionality · test credentials only · no live provider calls.</p>
        @unless($chiefView)
        <nav class="developer-tool-nav" aria-label="Developer tools">
            @foreach(['api'=>'Merchant API','credentials'=>'API credentials','qr'=>'QR payments','buttons'=>'Pay Buttons','webhooks'=>'Webhooks','docs'=>'Integration documentation'] as $key=>$label)
                <a class="btn ghost" href="{{ route('isolated.developers.'.$key, ['merchant'=>$merchantId]) }}" @if($tab===$key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        @endunless
    </section>
    @if(in_array($tab, ['api','docs']))
    <section class="payment-card"><h2>Merchant API v1</h2>
        <p>Test prefix: <code>/__isolated-api/v1/merchants/{merchant}</code>. These routes exist only in the isolated CLI harness. There is no production base URL.</p>
        <dl><dt>GET /links</dt><dd>Scope: links:read</dd><dt>POST /links</dt><dd>Scope: links:write. JSON amount_tzs, currency TZS, description and optional expires_at in Unix milliseconds. Send an Idempotency-Key of 16–80 letters, digits, underscores or hyphens.</dd><dt>GET /payments/{reference}</dt><dd>Scope: payments:read. Status does not prove settlement.</dd></dl>
        <p>Send Authorization: Bearer &lt;test token&gt; over a trusted connection. Keep tokens on your server. Identical retries return the same link; conflicting reuse returns 409. Check status after an uncertain response.</p>
        <pre class="payment-reference">POST /__isolated-api/v1/merchants/{merchant}/links
Authorization: Bearer &lt;test token&gt;
Idempotency-Key: order-example-0001
Content-Type: application/json

{"amount_tzs":12500,"currency":"TZS","description":"Example order"}</pre>
        <p>Responses wrap data with api_version: v1. 401: invalid, expired or revoked credential. 403: insufficient scope or authority. 404: unavailable resource. 409: conflict. 422: invalid input. 503: unavailable or uncertain; inspect state before retrying. Browser origins and query credentials are rejected. No CORS access is granted.</p>
    </section>
    @endif
    @if(in_array($tab, ['credentials','governance']))
    <section class="payment-card"><h2>API credential management</h2><p>Secrets are shown once on creation. Only token digests are stored. Credentials expire within 90 days. Rotation immediately revokes the old token. Merchant approval, owner membership and authentication versions are rechecked on every request.</p>
        @forelse($credentials as $credential)
            <article class="payment-link-row"><h3>Test credential ending {{ $credential['last_four'] }}</h3><p class="payment-reference">{{ $credential['id'] }}</p><p>{{ $credential['scopes'] }} · {{ $credential['revoked_at'] === null ? 'Active until expiry' : 'Revoked' }}</p><p>Expires: {{ \App\ViewModels\MerchantFinancialPresentation::time($credential['expires_at']) }}</p></article>
        @empty<p>No credentials issued.</p>@endforelse
        <p>Creation, rotation and revocation are exercised by isolated tests. This read-only preview never reveals credentials.</p>
    </section>
    @endif
    @if(in_array($tab, ['qr','buttons','governance']))
    <section class="payment-card"><h2>{{ $tab === 'buttons' ? 'Secure Pay Buttons' : 'QR checkout associations' }}</h2>
        <p>{{ $tab === 'buttons' ? 'Pay Buttons open an associated fixed-amount checkout in a separate customer session. The example uses fictional data; live payments remain disabled.' : 'Reusable QR codes accept independent customer sessions at a fixed amount. Dynamic QR codes require expiry and permit one reserved checkout attempt. Both retain the existing checkout and accounting controls.' }}</p>
        @forelse($widgets as $widget)
            @if($tab==='governance' || ($tab==='qr' && $widget['kind']==='qr') || ($tab==='buttons' && $widget['kind']==='button'))
            <article class="payment-link-row"><h3>{{ ucfirst($widget['mode']) }} {{ $widget['kind']==='qr' ? 'QR payment' : 'Pay Button' }}</h3><p>{{ $widget['description'] }} · TZS {{ number_format($widget['amount_tzs']) }} · {{ $widget['state'] }}</p><p class="payment-reference">Checkout association: {{ $widget['link_id'] }}</p>
            @unless($chiefView)
                @if($widget['kind']==='qr')
                    {!! \App\Actions\Payments\CheckoutQr::svg($tools->checkoutUrl($widget['public_token'])) !!}
                    <p><a href="{{ route('isolated.checkout.open', ['token'=>$widget['public_token']]) }}">Open associated checkout</a></p>
                @else
                    <p><a class="btn primary" href="{{ route('isolated.checkout.open', ['token'=>$widget['public_token']]) }}" target="_blank" rel="noopener noreferrer">Pay with XLipa</a></p>
                    <p>Embed a plain HTTPS link in production after operational approval. No script, iframe, merchant secret, customer identity or user-controlled HTML is embedded.</p>
                    <details class="premium-embed-details"><summary>View button markup</summary><pre class="payment-reference">{{ $tools->button($widget['id']) }}</pre></details><p class="payment-help">This is a read-only example. Unexported checkout destinations show the safe unavailable page; no payment is performed.</p>
                @endif
            @endunless
            </article>
            @endif
        @empty<p>No checkout tools created.</p>@endforelse
    </section>
    @endif
    @if(in_array($tab, ['webhooks','docs','governance']))
    <section class="payment-card"><h2>Signed merchant webhooks</h2><p>Only independently confirmed fictional P4 payments produce payment.confirmed events. Outbox sweeps are idempotent. Delivery is at least once, with a maximum of three attempts. A successful 2xx response completes delivery; redirects are never followed.</p>
        @foreach($endpoints as $endpoint)<article class="payment-link-row"><h3>{{ $endpoint['enabled'] ? 'Enabled test destination' : 'Disabled destination' }}</h3><p class="payment-reference">{{ $endpoint['url'] }}</p></article>@endforeach
        @forelse($deliveries as $delivery)<article class="payment-link-row"><h3>{{ ucfirst($delivery['status']) }}</h3><p class="payment-reference">Event: {{ $delivery['event_id'] }}</p><p>Attempts: {{ $delivery['attempts'] }} / 3 · HTTP {{ $delivery['http_status'] ?? 'not attempted' }}</p></article>@empty<p>No webhook deliveries queued.</p>@endforelse
        <p>Verify X-XLipa-Signature as v1=HMAC-SHA256(secret, timestamp + "." + exact raw body). Compare in constant time. Reject timestamps more than 300 seconds from trusted time. Match the signed event id to X-XLipa-Event. Atomically reserve the event id with business changes; identical duplicates acknowledge without processing, conflicting bodies reject. A delivery id is diagnostic, not a deduplication key.</p>
        <p>Destinations require HTTPS, default port, DNS hostnames and public IPv4 answers. Credentials, fragments, query strings, private addresses and mixed DNS answers are rejected. Revalidate DNS before every attempt and pin that address. This preview records requests in memory; it makes no external connection.</p>
    </section>
    @endif
</div>
@endsection
