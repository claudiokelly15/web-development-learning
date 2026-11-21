@php
    $navigationMerchant = request()->route('merchant');
    $navigationId = $merchantId ?? $navigationMerchant?->id;
    $navigationOwner = $navigationMerchant && \Illuminate\Support\Facades\Gate::allows('manageVerification', $navigationMerchant);
    $navigationGroups = [
        'Overview' => [['Dashboard', 'merchants.dashboard']],
        'Business' => [['Business Profile', 'merchants.onboarding'], ['Verification', $navigationOwner ? 'merchants.verification' : null], ['Applications', $navigationOwner ? 'merchants.applications' : null]],
        'Payments' => [['Payments', $navigationOwner && Route::has('isolated.transactions') ? 'isolated.transactions' : null], ['Payment Links', $navigationOwner && Route::has('isolated.links.index') ? 'isolated.links.index' : null], ['Checkout', $navigationOwner && Route::has('isolated.links.index') ? 'isolated.links.index' : null], ['QR Payments', null], ['Pay Buttons', null]],
        'Finance' => [['Wallet', $navigationOwner && Route::has('isolated.wallet') ? 'isolated.wallet' : null], ['Transactions', $navigationOwner && Route::has('isolated.transactions') ? 'isolated.transactions' : null], ['Withdrawals', $navigationOwner && Route::has('isolated.funds') ? 'isolated.funds' : null], ['Settlements', $navigationOwner && Route::has('isolated.funds') ? 'isolated.funds' : null], ['Balance History', null], ['Reports', null], ['Analytics', $navigationOwner && Route::has('isolated.analytics') ? 'isolated.analytics' : (($financialSummaryPreview ?? false) && $navigationOwner ? 'merchants.dashboard' : null)]],
        'Developers' => [['API Keys', null], ['Webhooks', null], ['API Documentation', 'pages.developers']],
        'Account' => [['Notifications', null], ['Security', 'account'], ['Settings', 'account'], ['Support', 'pages.contact']],
    ];
@endphp
<p class="product-mobile-hint">Workspace menu · scroll for more sections</p>
<nav aria-label="Merchant navigation" class="dash-navigation">
@foreach($navigationGroups as $group => $items)
    <div class="product-nav-group"><p class="dash-nav-label">{{ strtoupper($group) }}</p><ul>
    @foreach($items as [$label, $destination])
        <li>@if($destination)
            <a href="{{ route($destination, str_starts_with($destination, 'merchants.') || str_starts_with($destination, 'isolated.') ? ['merchant' => $navigationId] : []) }}{{ $label === 'Analytics' && $destination === 'merchants.dashboard' ? '#analytics' : '' }}" @if(request()->routeIs($destination) && !in_array($label, ['Payments', 'Checkout', 'Security', 'Settlements'])) aria-current="page" @endif><span>{{ $label }}</span>@if(str_starts_with($destination, 'isolated.'))<span class="dash-nav-unavailable">Isolated functionality</span>@elseif($label === 'Analytics')<span class="dash-nav-unavailable">Preview summary</span>@endif</a>
        @else
            <button type="button" disabled aria-disabled="true"><span>{{ $label }}</span><span class="dash-nav-unavailable">{{ in_array($label, ['Payments', 'Payment Links', 'Checkout', 'Wallet', 'Verification', 'Applications']) ? 'Operationally disabled' : 'Not yet implemented' }}</span></button>
        @endif</li>
    @endforeach
    </ul></div>
@endforeach
</nav>
