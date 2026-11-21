@extends('merchants.financial-layout')
@section('title', 'Transaction history')
@section('description', 'Actual payment requests, checkout associations and accounting evidence.')
@section('content')
@php($format = \App\ViewModels\MerchantFinancialPresentation::class)
<section class="dash-panel" aria-label="Filter transactions">
    <form method="get" action="{{ route('isolated.transactions', ['merchant' => $merchantId]) }}" class="wallet-filters">
        <div><label for="from">From date (EAT)</label><input id="from" type="date" name="from" value="{{ $filters['from'] }}"></div>
        <div><label for="to">Through date (EAT)</label><input id="to" type="date" name="to" value="{{ $filters['to'] }}"></div>
        <div><label for="status">Payment status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['pending', 'confirmed', 'failed', 'unresolved'] as $state)<option value="{{ $state }}" @selected($filters['status'] === $state)>{{ $format::status($state) }}</option>@endforeach</select></div>
        <div><label for="reference">Payment reference starts with</label><input id="reference" name="reference" type="text" maxlength="36" value="{{ $filters['reference'] }}" autocomplete="off"></div>
        <div><label for="per_page">Rows per page</label><select id="per_page" name="per_page">@foreach([10, 20, 50] as $size)<option value="{{ $size }}" @selected($pagination['per_page'] === $size)>{{ $size }}</option>@endforeach</select></div>
        <button type="submit" class="dash-button dash-button-outline">Apply filters</button>
        <a href="{{ route('isolated.transactions', ['merchant' => $merchantId]) }}">Clear filters</a>
    </form>
</section>
<section class="dash-panel wallet-section" aria-labelledby="history-title"><h2 id="history-title">Recorded activity</h2>
    <div class="wallet-table-scroll" role="region" aria-label="Transaction records" tabindex="0"><table class="wallet-table"><caption>{{ $pagination['total'] }} matching records. Amounts are whole TZS.</caption><thead><tr><th scope="col">Reference / description</th><th scope="col">Initiated (EAT)</th><th scope="col">Requested amount</th><th scope="col">Payment status</th><th scope="col">Accounting</th></tr></thead><tbody>
        @forelse($transactions as $transaction)
            <tr><td><a href="{{ route('isolated.transactions.show', ['merchant' => $merchantId, 'record' => $transaction['record_id']]) }}">{{ $transaction['payment_reference'] ?? 'Unresolved checkout reservation' }}</a><p>{{ $transaction['description'] ?? 'No payment link description' }}</p>@if($transaction['payment_link_id'])<small>Link {{ $transaction['payment_link_id'] }}</small>@endif</td>
                <td>{{ $format::time($transaction['created_at']) }}</td><td class="wallet-money">{{ $format::tzs($transaction['amount_tzs']) }}</td><td><span class="wallet-state" data-state="{{ $transaction['status'] }}">{{ $format::status($transaction['status']) }}</span><p>{{ $transaction['provider'] === 'fictional_mock' ? 'Fictional mock provider' : ($transaction['provider'] ?? 'No provider request recorded') }}</p></td>
                <td>{{ $format::status($transaction['accounting_status']) }}@if($transaction['journal_id'])<p class="wallet-reference">{{ $transaction['journal_id'] }}</p>@endif</td></tr>
        @empty<tr><td colspan="5">No recorded transactions match these filters.</td></tr>@endforelse
    </tbody></table></div>
    <nav class="wallet-pagination" aria-label="Transaction pages"><span>Page {{ $pagination['page'] }} of {{ $pagination['last_page'] }}</span>
        @if($pagination['page'] > 1)<a class="dash-button dash-button-outline" rel="prev" href="{{ route('isolated.transactions', ['merchant' => $merchantId, ...$filters, 'per_page' => $pagination['per_page'], 'page' => $pagination['page'] - 1]) }}">Previous page</a>@endif
        @if($pagination['page'] < $pagination['last_page'])<a class="dash-button dash-button-outline" rel="next" href="{{ route('isolated.transactions', ['merchant' => $merchantId, ...$filters, 'per_page' => $pagination['per_page'], 'page' => $pagination['page'] + 1]) }}">Next page</a>@endif
    </nav>
</section>
@endsection
