@php
    $format = \App\ViewModels\MerchantFinancialPresentation::class;
    $position = $financialOverview['position'];
    $states = $financialOverview['payment_states'];
    $rows = $financialHistory['transactions'];
    $clock = \Carbon\CarbonImmutable::createFromTimestampMs($financialClock, 'Africa/Nairobi');
    $today = $analytics['trends'][$clock->format('Y-m-d')] ?? ['count'=>0,'value_tzs'=>0];
    $confirmedRows = array_filter($rows, fn ($row) => $row['status']==='confirmed' && $row['confirmed_at'] >= $clock->startOfDay()->getTimestampMs() && $row['confirmed_at'] < $clock->addDay()->startOfDay()->getTimestampMs());
    if (!isset($analytics)) { $today = ['count'=>count($confirmedRows), 'value_tzs'=>array_sum(array_column($confirmedRows,'amount_tzs'))]; }
    $chartStart = isset($analytics) ? \Carbon\CarbonImmutable::createFromTimestampMs($analytics['from_ms'], 'Africa/Nairobi') : $clock->startOfDay();
    $daily = [];
    for ($date = $chartStart; $date <= $clock->startOfDay(); $date=$date->addDay()) {
        $key=$date->format('Y-m-d');
        $daily[$key] = isset($analytics) ? ($analytics['trends'][$key]['value_tzs'] ?? 0) : array_sum(array_column(array_filter($confirmedRows, fn ($row)=>\Carbon\CarbonImmutable::createFromTimestampMs($row['confirmed_at'],'Africa/Nairobi')->format('Y-m-d')===$key),'amount_tzs'));
    }
    $maximum=max(1,...array_values($daily));
    $points=[];
    foreach(array_values($daily) as $index=>$amount) { $points[]=(55+$index*815/max(1,count($daily)-1)).','.(155-125*$amount/$maximum); }
    $recordDimensions=[];
    foreach($analytics['cross'] ?? [] as $bucket) { foreach($bucket['transactions'] as $record) { $recordDimensions[$record]=['source'=>$analytics['sources'][$bucket['source']]['label'],'method'=>$analytics['methods'][$bucket['method']]['label']]; } }
    $sourceBuckets = $analytics['sources'] ?? [];
    $methodBuckets = $analytics['methods'] ?? [];
    uasort($sourceBuckets,fn($a,$b)=>$b['value_tzs']<=>$a['value_tzs']);
    uasort($methodBuckets,fn($a,$b)=>$b['value_tzs']<=>$a['value_tzs']);
    $bestSource=reset($sourceBuckets);
    $bestMethod=reset($methodBuckets);
@endphp
<div class="premium-overview" data-premium-overview>
    <div class="premium-kpis" aria-label="Primary payment metrics">
        @foreach([['Confirmed TZS today',$format::tzs($today['value_tzs']),'Confirmed value · EAT','money'],['Paid today',(string)$today['count'],'Confirmed · EAT','check'],['Pending payments',(string)($states['pending']['count'] ?? 0),'Awaiting confirmation','pending']] as [$label,$value,$help,$icon])
        <section class="premium-kpi" data-primary-kpi><span class="premium-metric-icon is-{{ $icon }}">@include('components.premium-icon',['name'=>$icon])</span><div><h2 @if($icon==='check') aria-label="Confirmed payments today" @endif>{{ $label }}</h2><strong>{{ $value }}</strong><small>{{ $help }}</small></div></section>
        @endforeach
    </div>
    <div class="premium-balance-actions">
        <section class="premium-balance" aria-label="Available balance"><span class="premium-balance-icon">@include('components.premium-icon',['name'=>'wallet'])</span><div><h2>Available to withdraw</h2><strong>{{ $format::tzs($position['withdrawable_tzs']) }}</strong><small>Operational balance · withdrawals disabled</small></div><div class="premium-simulation">@if(isset($funds))<span>Simulated available <b>{{ $format::tzs($funds['position']['simulated_available_tzs']) }}</b></span><span>Reserved <b>{{ $format::tzs($funds['position']['reserved_tzs']) }}</b></span>@else<span>Settlement unproven</span>@endif</div><button type="button" disabled class="premium-withdraw">Withdraw</button></section>
        <section class="premium-quick"><h2>Quick Actions</h2><div>
            @foreach([['Payment Links','isolated.links.index','link'],['All Payments','isolated.transactions','payments'],['Withdrawals','isolated.funds','bank'],['Developers','isolated.developers.api','code']] as [$label,$destination,$icon])
            @if(Route::has($destination))<a href="{{ route($destination,['merchant'=>$merchantId]) }}">@include('components.premium-icon',['name'=>$icon])<span>{{ $label }}</span></a>@else<button type="button" disabled>@include('components.premium-icon',['name'=>$icon])<span>{{ $label }}</span></button>@endif
            @endforeach
        </div></section>
    </div>
    <p class="premium-safety" role="note"><strong>Fictional evidence only.</strong> Live payments and withdrawals remain disabled. Confirmation is not settlement. Reporting date: {{ $clock->format('d M Y') }} · EAT.</p>
    <section class="premium-panel premium-performance" aria-labelledby="performance-title">
        <div class="premium-panel-heading"><div><h2 id="performance-title">@include('components.premium-icon',['name'=>'chart']) Daily Payment Performance</h2><p>Confirmed payment value (TZS) · confirmation time · EAT</p></div><div class="premium-periods" aria-label="Performance reporting periods">
            @if(Route::has('isolated.analytics'))
            @foreach(['today'=>'Today','seven'=>'7 Days','thirty'=>'30 Days','month'=>'This Month'] as $range=>$label)
            @php($query = in_array($range,['seven','thirty']) ? ['range'=>'custom','from'=>$clock->subDays($range==='seven'?6:29)->format('Y-m-d'),'to'=>$clock->format('Y-m-d')] : ['range'=>$range])
            <a class="{{ $range==='month'?'is-active':'' }}" href="{{ route('isolated.analytics',['merchant'=>$merchantId,...$query]) }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('isolated.analytics',['merchant'=>$merchantId]) }}#custom-range">Custom Date</a>
            @endif
        </div></div>
        <div class="premium-chart-total"><strong>{{ $format::tzs(array_sum($daily)) }}</strong><span>This reporting period · no assumed growth</span></div>
        <svg class="premium-chart" viewBox="0 0 900 185" role="img" aria-labelledby="trend-title trend-description" preserveAspectRatio="none"><title id="trend-title">Daily confirmed payment value</title><desc id="trend-description">{{ implode('; ',array_map(fn($date,$amount)=>$date.': '.$amount,array_keys($daily),$daily)) }}</desc>
        <defs><linearGradient id="premium-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#12b85b" stop-opacity=".3"/><stop offset="100%" stop-color="#12b85b" stop-opacity=".02"/></linearGradient></defs>
        @foreach([0,1,2,3] as $line)<path d="M55 {{ 30+$line*125/3 }}H870" stroke="#e4eaf3"/><text x="45" y="{{ 34+$line*125/3 }}" text-anchor="end">{{ number_format((int)round($maximum*(3-$line)/3)) }}</text>@endforeach
        <polygon points="55,155 {{ implode(' ',$points) }} 870,155" fill="url(#premium-area)"/>
        <polyline points="{{ implode(' ',$points) }}" fill="none" stroke="#0caf51" stroke-width="3" stroke-linejoin="round"/>
        @foreach($points as $point)@php([$cx,$cy]=explode(',',$point))<circle cx="{{ $cx }}" cy="{{ $cy }}" r="3" fill="white" stroke="#0caf51" stroke-width="2"/>@endforeach
        <text x="55" y="178">{{ $chartStart->format('d M') }}</text><text x="870" y="178" text-anchor="end">{{ $clock->format('d M') }}</text>
        </svg><div class="premium-chart-mobile-axis" aria-hidden="true"><span>{{ $chartStart->format('d M') }}</span><span>0–{{ $format::tzs($maximum) }}</span><span>{{ $clock->format('d M') }}</span></div>
        <p class="premium-chart-help">This month is shown. Period controls open the full authorized report. Zero dates contain no confirmed records in this report.</p>
    </section>
    <div class="premium-analytics" id="analytics">
        @foreach(['sources'=>['traffic-source','Traffic Sources',$sourceBuckets,'people'],'methods'=>['payment-method','Payment Methods',$methodBuckets,'card']] as $dimension=>[$slug,$heading,$buckets,$icon])
        <section class="premium-panel premium-summary" aria-labelledby="{{ $slug }}-title"><div class="premium-panel-heading"><h2 id="{{ $slug }}-title">@include('components.premium-icon',['name'=>$icon]) {{ $heading }}</h2><span>Total: <b>{{ $format::tzs($analytics['value_tzs'] ?? 0) }}</b></span></div>
        <div class="premium-breakdown">
        @forelse(array_filter($buckets,fn($bucket)=>$bucket['count']>0) as $key=>$bucket)
            <div class="premium-breakdown-row"><span class="premium-source-mark" data-source="{{ $key }}">@if(isset(['synthetic_mpesa'=>'mpesa.png','synthetic_mixx'=>'mixx-by-yas.png','synthetic_airtel'=>'airtel.png','synthetic_halo'=>'halopesa.png'][$key]))<img src="{{ asset('assets/payment-logos/'.['synthetic_mpesa'=>'mpesa.png','synthetic_mixx'=>'mixx-by-yas.png','synthetic_airtel'=>'airtel.png','synthetic_halo'=>'halopesa.png'][$key]) }}" width="20" height="20" alt="">@else
@if(in_array($key,['facebook','tiktok','instagram','whatsapp','google','youtube'],true))
<img src="{{ asset('assets/social-'.$key.'.svg') }}" width="22" height="22" alt="">
@else
@include('components.premium-icon',['name'=>$dimension==='sources'?'link':'card'])
@endif
@endif</span><span>{{ $bucket['label'] }}<small>{{ $bucket['count'] }} payments</small></span><b>{{ $format::tzs($bucket['value_tzs']) }}</b><span class="premium-bar"><i style="--share:{{ $bucket['basis_points']/100 }}%"></i></span><span>{{ number_format($bucket['basis_points']/100,1) }}%</span></div>
        @empty<p class="product-muted">No confirmed {{ $dimension==='sources'?'source':'method' }} evidence. Missing attribution remains Unknown.</p>@endforelse
        </div>
        @if(Route::has('isolated.analytics'))<a class="premium-detail-link" href="{{ route('isolated.analytics',['merchant'=>$merchantId]) }}#{{ $slug }}-title">View full {{ $dimension==='sources'?'traffic source':'payment method' }} report</a>@endif
        </section>
        @endforeach
        <section class="premium-panel premium-insights"><div class="premium-panel-heading"><h2>@include('components.premium-icon',['name'=>'chart']) Payment Insights</h2></div><dl>
            <div><dt>Total confirmed value</dt><dd>{{ $format::tzs($analytics['value_tzs'] ?? $position['confirmed_fictional_payment_tzs']) }}</dd></div>
            <div><dt>Successful payments</dt><dd>{{ $analytics['count'] ?? ($states['confirmed']['count'] ?? 0) }}</dd></div>
            <div><dt>Top traffic source</dt><dd>{{ $bestSource && $bestSource['count'] ? $bestSource['label'] : 'Unavailable' }}</dd></div>
            <div><dt>Top payment method</dt><dd>{{ $bestMethod && $bestMethod['count'] ? $bestMethod['label'] : 'Unknown' }}</dd></div>
        </dl><p class="premium-chart-help">Unique Paying Customers: unavailable. No reliable payer identity is collected. Shares include Unknown.</p></section>
    </div>
    <section class="premium-panel premium-latest"><div class="premium-panel-heading"><h2>@include('components.premium-icon',['name'=>'payments']) Latest Payments</h2><a href="{{ route('isolated.transactions',['merchant'=>$merchantId]) }}">View all payments</a></div>
    <div class="product-data-scroll premium-payments-desktop" role="region" aria-label="Latest payments" tabindex="0"><table class="product-table"><caption>Whole TZS · fictional evidence · EAT</caption><thead><tr><th>Reference</th><th>Amount (TZS)</th><th>Method</th><th>Source</th><th>Status</th><th>Date &amp; Time</th></tr></thead><tbody>
    @forelse(array_slice($rows,0,5) as $transaction)
    @php($dimensions=$recordDimensions[$transaction['record_id']] ?? ['method'=>'Unknown','source'=>'Unknown'])
    <tr><td><a href="{{ route('isolated.transactions.show',['merchant'=>$merchantId,'record'=>$transaction['record_id']]) }}">{{ $transaction['payment_reference'] ? substr($transaction['payment_reference'],0,12) : 'Unresolved request' }}</a><small>{{ $transaction['description'] }}</small></td><td><b>{{ number_format($transaction['amount_tzs']) }}</b></td><td>{{ $dimensions['method'] }}</td><td>{{ $dimensions['source'] }}</td><td><span class="product-badge" data-state="{{ $transaction['status'] }}">{{ $format::status($transaction['status']) }}</span></td><td>{{ $format::time($transaction['created_at']) }}</td></tr>
    @empty<tr><td colspan="6">No recorded payments.</td></tr>@endforelse
    </tbody></table></div><ul class="premium-mobile-payments" aria-label="Latest payments">
 @forelse(array_slice($rows,0,5) as $transaction)
 @php($dimensions=$recordDimensions[$transaction['record_id']] ?? ['method'=>'Unknown','source'=>'Unknown'])
 <li><div class="premium-payment-heading"><a href="{{ route('isolated.transactions.show',['merchant'=>$merchantId,'record'=>$transaction['record_id']]) }}">{{ $transaction['payment_reference'] ? substr($transaction['payment_reference'],0,12) : 'Unresolved request' }}<span class="dash-sr-only"> · View transaction details</span></a><strong>{{ $format::tzs($transaction['amount_tzs']) }}</strong></div><div class="premium-payment-meta"><span>{{ $dimensions['method'] }}</span><span class="product-badge" data-state="{{ $transaction['status'] }}">{{ $format::status($transaction['status']) }}</span></div><small>{{ $format::time($transaction['created_at']) }} · {{ $dimensions['source'] }}</small><details><summary>Payment information</summary><p>{{ $transaction['description'] }}</p><a href="{{ route('isolated.transactions.show',['merchant'=>$merchantId,'record'=>$transaction['record_id']]) }}">Full transaction details</a></details></li>
 @empty<li>No recorded payments.</li>@endforelse
 </ul></section>
    <details class="premium-evidence product-date-windows"><summary>Financial evidence, date summaries and reporting limitations</summary><div class="premium-evidence-body"><p>Confirmed payment value: {{ $format::tzs($position['confirmed_fictional_payment_tzs']) }}. Ledger pending obligations: {{ $format::tzs(isset($funds)?$funds['position']['pending_obligation_tzs']:$position['pending_merchant_obligation_tzs']) }}. Net of independently proven simulated settlement when available. LAST 30 DAYS summaries remain available in Analytics using Custom Date.</p>@if(isset($funds))<p>Independently settled simulated funds: {{ $format::tzs($funds['position']['settled_simulated_tzs']) }}. Reserved simulated withdrawals: {{ $format::tzs($funds['position']['reserved_tzs']) }}. These are fictional, not operational funds.</p>@endif<p>Traffic Source Attribution records first-touch claims; social platforms do not verify these claims. Payment Method Analytics uses authenticated synthetic evidence. Confirmed Payment Value (TZS) is not settlement. Failed payments: {{ $states['failed']['count'] ?? 0 }}. Unresolved payments: {{ $states['unknown']['count'] ?? 0 }}. Unresolved checkout reservations: {{ $financialOverview['unresolved_checkout_reservations'] }}.</p></div></details>
</div>
