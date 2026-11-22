@php
    $navigationMerchant = request()->route('merchant');
    $navigationId = $merchantId ?? $navigationMerchant?->id;
    $navigationOwner = $navigationMerchant && \Illuminate\Support\Facades\Gate::allows('manageVerification', $navigationMerchant);
    $navigationGroups = [
        'Overview' => [['Dashboard', 'merchants.dashboard']],
        'Business' => [['Business Profile', 'merchants.onboarding'], ['Verification', $navigationOwner ? 'merchants.verification' : null], ['Applications', $navigationOwner ? 'merchants.applications' : null]],
        'Payments' => [['Payments', $navigationOwner && Route::has('isolated.transactions') ? 'isolated.transactions' : null], ['Payment Links', $navigationOwner && Route::has('isolated.links.index') ? 'isolated.links.index' : null], ['Checkout', $navigationOwner && Route::has('isolated.links.index') ? 'isolated.links.index' : null], ['QR Payments', $navigationOwner && Route::has('isolated.developers.qr') ? 'isolated.developers.qr' : null], ['Pay Buttons', $navigationOwner && Route::has('isolated.developers.buttons') ? 'isolated.developers.buttons' : null]],
        'Finance' => [['Wallet', $navigationOwner && Route::has('isolated.wallet') ? 'isolated.wallet' : null], ['Transactions', $navigationOwner && Route::has('isolated.transactions') ? 'isolated.transactions' : null], ['Withdrawals', $navigationOwner && Route::has('isolated.funds') ? 'isolated.funds' : null], ['Settlements', $navigationOwner && Route::has('isolated.funds') ? 'isolated.funds' : null], ['Balance History', null], ['Reports', null], ['Analytics', $navigationOwner && Route::has('isolated.analytics') ? 'isolated.analytics' : (($financialSummaryPreview ?? false) && $navigationOwner ? 'merchants.dashboard' : null)]],
        'Developers' => [['API Keys', $navigationOwner && Route::has('isolated.developers.credentials') ? 'isolated.developers.credentials' : null], ['Webhooks', $navigationOwner && Route::has('isolated.developers.webhooks') ? 'isolated.developers.webhooks' : null], ['API Documentation', $navigationOwner && Route::has('isolated.developers.docs') ? 'isolated.developers.docs' : 'pages.developers']],
        'Account' => [['Notifications', null], ['Security', 'account'], ['Settings', 'account'], ['Support', 'pages.contact']],
    ];
@endphp
<input class="premium-nav-toggle" id="merchant-menu-toggle" type="checkbox" aria-label="Toggle merchant navigation">
<label class="premium-menu-button" for="merchant-menu-toggle">☰ <span>Menu</span></label>
<nav class="dash-navigation premium-primary-navigation" aria-label="Primary merchant navigation">
@foreach([['Dashboard','merchants.dashboard','home'],['Payments','isolated.transactions','payments'],['Payment Links','isolated.links.index','link'],['Wallet','isolated.wallet','wallet'],['Withdrawals','isolated.funds','bank'],['Analytics','isolated.analytics','chart'],['Developers','isolated.developers.api','code'],['Business','merchants.onboarding','people'],['Settings','account','settings']] as [$label,$destination,$icon])
@if(Route::has($destination) && (!str_starts_with($destination,'isolated.') || $navigationOwner))
<a href="{{ route($destination,str_starts_with($destination,'isolated.') || str_starts_with($destination,'merchants.')?['merchant'=>$navigationId]:[]) }}" @if(request()->routeIs($destination)) aria-current="page" @endif>@include('components.premium-icon',['name'=>$icon])<span>{{ $label }}</span></a>
@else<button type="button" disabled>@include('components.premium-icon',['name'=>$icon])<span>{{ $label }}</span><span class="dash-nav-unavailable">Unavailable</span></button>@endif
@endforeach
<details class="premium-more-tools"><summary>All tools &amp; access details</summary>
<p class="product-mobile-hint">Workspace menu · scroll for more sections</p>
<nav aria-label="Merchant navigation" class="dash-navigation">
@foreach($navigationGroups as $group => $items)
    <div class="product-nav-group"><p class="dash-nav-label">{{ strtoupper($group) }}</p><ul>
    @foreach($items as [$label, $destination])
        <li>@if($destination)
            <a href="{{ route($destination, str_starts_with($destination, 'merchants.') || str_starts_with($destination, 'isolated.') ? ['merchant' => $navigationId] : []) }}{{ $label === 'Analytics' && $destination === 'merchants.dashboard' ? '#analytics' : '' }}"><span>{{ $label }}</span>@if(str_starts_with($destination, 'isolated.'))<span class="dash-nav-unavailable">Isolated functionality</span>@elseif($label === 'Analytics')<span class="dash-nav-unavailable">Preview summary</span>@endif</a>
        @else
            <button type="button" disabled aria-disabled="true"><span>{{ $label }}</span><span class="dash-nav-unavailable">{{ in_array($label, ['Payments', 'Payment Links', 'Checkout', 'Wallet', 'Verification', 'Applications']) ? 'Operationally disabled' : 'Not yet implemented' }}</span></button>
        @endif</li>
    @endforeach
    </ul></div>
@endforeach
</nav>

</details></nav>
<div class="premium-mobile-dock" aria-label="Mobile workspace navigation">
@foreach([['Dashboard','merchants.dashboard','home'],['Payments','isolated.transactions','payments'],['Analytics','isolated.analytics','chart'],['Wallet','isolated.wallet','wallet']] as [$label,$destination,$icon])
@if(Route::has($destination) && (!str_starts_with($destination,'isolated.') || $navigationOwner))<a href="{{ route($destination,['merchant'=>$navigationId]) }}">@include('components.premium-icon',['name'=>$icon])<span>{{ $label }}</span></a>@else<span aria-disabled="true">@include('components.premium-icon',['name'=>$icon])<span>{{ $label }}</span></span>@endif
@endforeach
<label for="merchant-menu-toggle">@include('components.premium-icon',['name'=>'settings'])<span>More</span></label>
</div>
