@php
    $isChiefNavigation = \Illuminate\Support\Facades\Gate::allows('viewChiefWorkspace', \App\Models\PlatformRoleAssignment::class);
    $fundsReadDestination = isset($fundsOversightMerchant) && Route::has('isolated.admin.funds') ? 'isolated.admin.funds' : null;
    $adminGroups = $isChiefNavigation ? [
        'Workspace' => [['Dashboard', 'admin.chief'], ['Roles and permissions', 'admin.access']],
        'Merchants' => [['Merchant Management', null], ['Applications', 'admin.applications'], ['Verification Review', null]],
        'Financial oversight' => [['Transactions', null], ['Withdrawals', $fundsReadDestination], ['Settlements', $fundsReadDestination], ['Reconciliation', $fundsReadDestination], ['Fees and Revenue', null]],
        'Governance' => [['Providers', null], ['Risk and Fraud', null], ['Disputes and Refunds', null], ['Audit Logs', null], ['API Governance', null], ['Staff', null], ['System Settings', null]],
        'Account' => [['Your account', 'account'], ['Support', 'pages.contact']],
    ] : ['Workspace' => [['Dashboard', 'admin.staff'], ['Your access scope', 'admin.access']], 'Your work' => [['Assigned tasks', null], ['Merchant Applications', null], ['Support', 'pages.contact']], 'Account' => [['Your account', 'account']]];
@endphp
<p class="product-mobile-hint">Workspace menu · scroll for more sections</p>
<nav aria-label="Administrative navigation" class="dash-navigation">
@foreach($adminGroups as $group => $items)
    <div class="product-nav-group"><p class="dash-nav-label">{{ strtoupper($group) }}</p><ul>
    @foreach($items as [$label, $destination])
        <li>@if($destination)<a href="{{ route($destination, $destination === 'isolated.admin.funds' ? ['merchant'=>$fundsOversightMerchant] : []) }}" @if(request()->routeIs($destination) && !in_array($label,['Withdrawals','Reconciliation'])) aria-current="page" @endif><span>{{ $label }}</span>@if(in_array($destination,['admin.applications','isolated.admin.funds']))<span class="dash-nav-unavailable">Read-only</span>@endif</a>
        @else<button type="button" disabled aria-disabled="true"><span>{{ $label }}</span><span class="dash-nav-unavailable">{{ !$isChiefNavigation ? 'Not permitted' : 'Not yet implemented' }}</span></button>@endif</li>
    @endforeach
    </ul></div>
@endforeach
</nav>
