@php
    $format = \App\ViewModels\MerchantFinancialPresentation::class;
    $position = $financialOverview['position'];
    $states = $financialOverview['payment_states'];
    $rows = $financialHistory['transactions'];
    $count = array_sum(array_column($states, 'count'));
    $clock = \Carbon\CarbonImmutable::createFromTimestampMs($financialClock, 'Africa/Nairobi');
    $windows = ['Today' => $clock->startOfDay(), 'Yesterday' => $clock->subDay()->startOfDay(), 'This week' => $clock->startOfWeek(), 'This month' => $clock->startOfMonth(), 'Last 30 days' => $clock->subDays(29)->startOfDay()];
    $daily = [];
    for ($day = 29; $day >= 0; $day--) {
        $date = $clock->subDays($day)->format('Y-m-d');
        $daily[$date] = ['label' => $clock->subDays($day)->format('D'), 'amount' => 0];
    }
    foreach ($rows as $row) {
        $date = \Carbon\CarbonImmutable::createFromTimestampMs($row['created_at'], 'Africa/Nairobi')->format('Y-m-d');
        if ($row['status'] === 'confirmed' && isset($daily[$date])) {
            $daily[$date]['amount'] += $row['amount_tzs'];
        }
    }
    $maximum = max(1, ...array_column($daily, 'amount'));
    $points = [];
    foreach (array_values($daily) as $index => $day) {
        $points[] = (20 + $index * 860 / 29).','.(160 - 130 * $day['amount'] / $maximum);
    }
    $todayConfirmed = count(array_filter($rows, fn ($row) => $row['status'] === 'confirmed' && $row['confirmed_at'] >= $clock->startOfDay()->getTimestampMs() && $row['confirmed_at'] < $clock->addDay()->startOfDay()->getTimestampMs()));
@endphp
<div class="product-alert" role="note"><strong>Isolated functionality · fictional payment evidence</strong><p>These figures come from recorded mock payments and the ledger. Live payment services are operationally disabled. Operational settlement is unproven; independently authenticated simulated settlement is shown separately when available. Demo reporting date: {{ $clock->format('d M Y') }} (EAT).</p></div>
<div class="product-toolbar" aria-label="Payment quick actions">
    <a href="{{ route('isolated.links.index', ['merchant' => $merchantId]) }}">Create Payment Link <span aria-hidden="true">↗</span></a>
    <a href="{{ route('isolated.transactions', ['merchant' => $merchantId]) }}">View Payments</a>
    <a href="{{ route('isolated.wallet', ['merchant' => $merchantId]) }}">Wallet</a>
    <a href="{{ route('isolated.transactions', ['merchant' => $merchantId]) }}">Transactions</a>
    <a href="{{ route('pages.developers') }}">Developer Tools</a>
    @if(Route::has('isolated.funds'))<a href="{{ route('isolated.funds',['merchant'=>$merchantId]) }}">Settlement and withdrawals</a>@else<button class="product-quick-disabled" type="button" disabled>Withdrawals · Operationally disabled</button>@endif
</div>
<div class="product-kpis" aria-label="Recorded financial overview">
    @foreach([
        ['Confirmed payments today', (string) $todayConfirmed, 'Authenticated confirmations today · EAT'],
        ['Pending payments', (string) ($states['pending']['count'] ?? 0), 'Awaiting provider confirmation'],
        ['Failed payments', (string) ($states['failed']['count'] ?? 0), 'Recorded fictional provider failures'],
        ['Confirmed payment value', $format::tzs($position['confirmed_fictional_payment_tzs']), 'Not a settlement or an available balance'],
        ['Ledger pending obligations', $format::tzs(isset($funds) ? $funds['position']['pending_obligation_tzs'] : $position['pending_merchant_obligation_tzs']), isset($funds) ? 'Net of independently proven simulated settlement' : 'Same underlying payments; do not add to value'],
        ['Available to withdraw', $format::tzs($position['withdrawable_tzs']), 'No verified settlement or real funds'],
    ] as [$label, $value, $help])
        <section class="product-kpi {{ $loop->last ? 'is-primary' : '' }}"><h2>{{ $label }}</h2><strong>{{ $value }}</strong><small>{{ $help }}</small></section>
    @endforeach
</div>
@if(isset($funds))
<div class="product-kpis" aria-label="P8 simulated funds position">
    @foreach(['settled_simulated_tzs'=>'Independently settled simulated funds','reserved_tzs'=>'Reserved simulated withdrawals','simulated_available_tzs'=>'Simulated available balance'] as $field=>$label)
    <section class="product-kpi"><h2>{{ $label }}</h2><strong>{{ $format::tzs($funds['position'][$field]) }}</strong><small>Fictional evidence only · no operational funds</small></section>
    @endforeach
</div>
@endif
<details class="product-date-windows"><summary>Payment summaries by date · EAT</summary><dl>
    @foreach($windows as $label => $start)
        @php
            $end = $label === 'Yesterday' ? $clock->startOfDay() : $clock->addDay()->startOfDay();
            $selected = array_filter($rows, fn ($row) => $row['created_at'] >= $start->getTimestampMs() && $row['created_at'] < $end->getTimestampMs());
            $value = array_sum(array_column(array_filter($selected, fn ($row) => $row['status'] === 'confirmed'), 'amount_tzs'));
        @endphp
        <div><dt>{{ $label }}</dt><dd>{{ $format::tzs($value) }}<small>{{ count($selected) }} recorded requests · confirmed value only</small></dd></div>
    @endforeach
</dl></details>
<div class="product-analytics" id="analytics">
    <section class="dash-panel"><div class="dash-panel-heading"><div><p class="dash-eyebrow">LAST 30 DAYS · EAT</p><h2>Confirmed payment trend</h2></div><span class="product-badge">Fictional data</span></div>
        <p class="product-muted">Maximum daily value: {{ $format::tzs(max(array_column($daily, 'amount'))) }}</p>
        <svg class="product-trend" viewBox="0 0 900 200" preserveAspectRatio="none" role="img" aria-labelledby="trend-title trend-description"><title id="trend-title">Confirmed fictional payment value in TZS over 30 days</title><desc id="trend-description">{{ implode('; ', array_map(fn ($date, $day) => $date.': '.$day['amount'], array_keys($daily), $daily)) }}</desc>
            <path d="M20 30H880M20 95H880M20 160H880" fill="none" stroke="#e2eaf5"/>
            <polygon points="20,160 {{ implode(' ', $points) }} 880,160" fill="#edf4ff"/>
            <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="#2473cf" stroke-width="3" stroke-linejoin="round"/>
            <text x="20" y="20">{{ $format::tzs($maximum) }}</text><text x="20" y="185">{{ $clock->subDays(29)->format('d M') }}</text><text x="440" y="185" text-anchor="middle">{{ $clock->subDays(14)->format('d M') }}</text><text x="880" y="185" text-anchor="end">{{ $clock->format('d M') }}</text>
        </svg><div class="product-chart-axis" aria-hidden="true"><span>{{ $clock->subDays(29)->format('d M') }}</span><span>{{ $clock->subDays(14)->format('d M') }}</span><span>{{ $clock->format('d M') }}</span></div><p class="product-muted">Zero periods reflect no confirmed fixture records. Recorded initiation dates; confirmation does not prove settlement.</p>
    </section>
    <section class="dash-panel"><p class="dash-eyebrow">PAYMENT OUTCOMES</p><h2>Status distribution</h2><div class="product-distribution">
        @foreach(['confirmed' => 'Confirmed', 'pending' => 'Pending', 'failed' => 'Failed', 'unknown' => 'Unresolved'] as $state => $label)
            @php($stateCount = $states[$state]['count'] ?? 0)
            <div class="product-distribution-row"><span>{{ $label }}</span><span class="product-meter"><i style="--bar:{{ $count ? (int) round(100 * $stateCount / $count) : 0 }}%"></i></span><strong>{{ $stateCount }}</strong></div>
        @endforeach
        </div><p class="product-muted">{{ $financialOverview['unresolved_checkout_reservations'] }} unresolved checkout reservations. No mobile-money network attribution is inferred.</p>
    </section>
</div>
@if(isset($analytics))
@include('merchants.analytics-tables')
@else
<div class="product-analytics" aria-label="Merchant analytics availability">
    <section class="product-empty" aria-labelledby="traffic-source-title"><h2 id="traffic-source-title">Traffic Source Attribution</h2><p>Where customers came from. No attribution evidence is available in this workspace; no source counts or conversion values are assumed.</p><p>Supported source categories: Facebook, TikTok, Instagram, WhatsApp, Google, YouTube, Direct, Referral/Other and Unknown.</p></section>
    <section class="product-empty" aria-labelledby="payment-method-title"><h2 id="payment-method-title">Payment Method Analytics</h2><p>How customers paid. Network-level evidence is not available. These records identify only the fictional mock provider; no payment-method totals are inferred.</p><p>Supported isolated breakdown: M-Pesa, Mixx by Yas, Airtel Money, HaloPesa and supported banking/payment methods. Live payment services remain operationally disabled.</p></section>
</div>
@endif
<section class="dash-panel"><div class="dash-panel-heading"><div><p class="dash-eyebrow">YOUR RECORDED ACTIVITY</p><h2>Latest payments</h2></div><a href="{{ route('isolated.transactions', ['merchant' => $merchantId]) }}">View all</a></div>
    <div class="product-data-scroll" role="region" aria-label="Latest payments" tabindex="0"><table class="product-table"><caption>Whole TZS · fictional mock provider · times in EAT</caption><thead><tr><th scope="col">Reference / link</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col">Provider</th></tr></thead><tbody>
    @forelse(array_slice($rows, 0, 5) as $transaction)<tr><td><a href="{{ route('isolated.transactions.show', ['merchant' => $merchantId, 'record' => $transaction['record_id']]) }}">{{ $transaction['payment_reference'] ?? 'Unresolved request' }}</a><small>{{ $transaction['description'] }}</small><small>Link {{ $transaction['payment_link_id'] ?? 'Not associated' }}</small></td><td>{{ $format::tzs($transaction['amount_tzs']) }}</td><td><span class="product-badge" data-state="{{ $transaction['status'] }}">{{ $format::status($transaction['status']) }}</span></td><td>{{ $format::time($transaction['created_at']) }}</td><td>{{ $transaction['provider'] === 'fictional_mock' ? 'Fictional mock provider' : 'No provider evidence' }}</td></tr>
    @empty<tr><td colspan="5">No payment activity is recorded.</td></tr>@endforelse
    </tbody></table></div>
</section>
