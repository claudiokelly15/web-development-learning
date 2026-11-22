@php
    $isChiefNavigation = \Illuminate\Support\Facades\Gate::allows('viewChiefWorkspace', \App\Models\PlatformRoleAssignment::class);
    $fundsReadDestination = isset($fundsOversightMerchant) && Route::has('isolated.admin.funds') ? 'isolated.admin.funds' : null;
    $adminGroups = $isChiefNavigation ? [
        'Workspace' => [['Dashboard', 'admin.chief'], ['Roles and permissions', 'admin.access']],
        'Merchants' => [['Merchant Management', null], ['Applications', 'admin.applications'], ['Verification Review', null]],
        'Financial oversight' => [['Transactions', null], ['Withdrawals', $fundsReadDestination], ['Settlements', $fundsReadDestination], ['Reconciliation', $fundsReadDestination], ['Fees and Revenue', null]],
        'Governance' => [['Providers', null], ['Risk and Fraud', null], ['Disputes and Refunds', null], ['Audit Logs', null], ['API Governance', isset($fundsOversightMerchant) && Route::has('isolated.admin.api-governance') ? 'isolated.admin.api-governance' : null], ['Staff', null], ['System Settings', null]],
        'Account' => [['Your account', 'account'], ['Support', 'pages.contact']],
    ] : ['Workspace' => [['Dashboard', 'admin.staff'], ['Your access scope', 'admin.access']], 'Your work' => [['Assigned tasks', null], ['Merchant Applications', null], ['Support', 'pages.contact']], 'Account' => [['Your account', 'account']]];
@endphp
<input class="premium-nav-toggle" id="admin-menu-toggle" type="checkbox" aria-label="Toggle admin navigation"><label class="premium-menu-button" for="admin-menu-toggle">☰ <span>Menu</span></label><p class="product-mobile-hint">Workspace menu · scroll for more sections</p>
<nav aria-label="Administrative navigation" class="dash-navigation">
@foreach($adminGroups as $group => $items)
@php($visibleItems = $isChiefNavigation ? array_filter($items, fn($item) => $item[1] !== null) : $items)
@if(count($visibleItems)>0)
    <div class="product-nav-group"><p class="dash-nav-label">{{ strtoupper($group) }}</p><ul>
    @foreach($visibleItems as [$label, $destination])
        <li>@if($destination)<a href="{{ route($destination, in_array($destination, ['isolated.admin.funds','isolated.admin.api-governance']) ? ['merchant'=>$fundsOversightMerchant] : []) }}" @if(request()->routeIs($destination) && !in_array($label,['Withdrawals','Reconciliation'])) aria-current="page" @endif>@include('components.premium-icon',['name'=> match($label){'Dashboard'=>'home','Applications','Merchant Management','Staff'=>'people','Withdrawals','Settlements','Reconciliation','Transactions','Fees and Revenue'=>'wallet','API Governance','Providers'=>'code','Support'=>'link',default=>'settings'}]) <span>{{ $label }}</span>@if(in_array($destination,['admin.applications','isolated.admin.funds']))<span class="dash-nav-unavailable">Read-only</span>@endif</a>
        @else<button type="button" disabled aria-disabled="true"><span>{{ $label }}</span><span class="dash-nav-unavailable">{{ !$isChiefNavigation ? 'Not permitted' : 'Not yet implemented' }}</span></button>@endif</li>
    @endforeach
    </ul></div>
@endif
@endforeach
@if($isChiefNavigation)
<details class="premium-future-admin"><summary>Future administrative tools</summary>
@foreach($adminGroups as $group => $items)
@php($futureItems = array_filter($items, fn($item) => $item[1] === null))
@if(count($futureItems)>0)<div class="product-nav-group"><p class="dash-nav-label">{{ strtoupper($group) }}</p><ul>
@foreach($futureItems as [$label,$destination])<li><button type="button" disabled aria-disabled="true"><span>{{ $label }}</span><span class="dash-nav-unavailable">Not yet implemented</span></button></li>@endforeach
</ul></div>@endif
@endforeach
</details>
@endif
</nav>
