@if(!$overview->accessPage)
<div class="premium-admin-overview">
    <div class="premium-admin-kpis">
    @if($overview->chief())
        @foreach(['Reviewable applications'=>isset($reviewScope)?array_sum($reviewScope):null,'Pending applications'=>$reviewScope['Pending applications'] ?? null,'Approved applications'=>$reviewScope['Approved applications'] ?? null,'Rejected applications'=>$reviewScope['Rejected applications'] ?? null] as $label=>$value)
        <section class="premium-kpi"><span class="premium-metric-icon is-check">@include('components.premium-icon',['name'=>'people'])</span><div><h2>{{ $label }}</h2><strong>{{ $value ?? '—' }}</strong><small>{{ $value===null?'Authorized data not provisioned':'Within your permitted review scope' }}</small></div></section>
        @endforeach
    @else
        <section class="premium-staff-summary" aria-label="Staff account summary"><span class="premium-metric-icon">@include('components.premium-icon',['name'=>'people'])</span><div><h2>Staff Admin</h2><p>Email verified · Read-only account access</p></div><a href="{{ route('admin.access') }}">View permissions</a></section>
    @endif
    </div>
    <div class="premium-admin-main">
        <section class="premium-panel"><div class="premium-panel-heading"><div><h2>@include('components.premium-icon',['name'=>'chart']) {{ $overview->chief()?'Application Review Workload':'Your Workspace' }}</h2><p>{{ $overview->chief()?'Current authorized application counts · not payment volume or revenue':'Focused tools for your current role' }}</p></div><a href="{{ route('admin.access') }}">Access scope</a></div>
        @if($overview->chief() && isset($reviewScope))
            <div class="premium-workload">@foreach($reviewScope as $label=>$value)<div><span>{{ $label }}</span><span class="premium-bar"><i style="--share:{{ array_sum($reviewScope)?100*$value/array_sum($reviewScope):0 }}%"></i></span><strong>{{ $value }}</strong></div>@endforeach</div>
            <p class="product-muted">Submitted applications in your permitted scope. No historical trend is available.</p>
        @else
            <div class="premium-empty"><h3>{{ $overview->chief()?'Review data not provisioned':'A focused workspace for your role.' }}</h3><p>{{ $overview->chief()?'Open application review for records within your current permissions. No platform merchant or financial totals are assumed.':'No assigned-task or case-management interface is available. Chief application review and platform financial records remain outside your role.' }}</p></div>
        @endif
        <div class="product-toolbar">@if($overview->chief())<a href="{{ route('admin.applications') }}">Review applications</a>@endif<a href="{{ route('admin.access') }}">Roles and permissions</a><a href="{{ route('account') }}">Your account</a></div>
        </section>
        <section class="premium-panel"><div class="premium-panel-heading"><h2>@include('components.premium-icon',['name'=>'wallet']) Financial Oversight</h2></div><div class="premium-empty"><h3>Operationally disabled</h3><p>Live financial operations are disabled. Application approval does not grant payment authority.</p></div>
        @if($overview->chief() && isset($fundsOversightMerchant) && Route::has('isolated.admin.funds'))<a class="premium-detail-link" href="{{ route('isolated.admin.funds',['merchant'=>$fundsOversightMerchant]) }}">Inspect authorized fictional financial evidence</a>@else<p class="product-muted">No financial reporting grant is provisioned for this overview.</p>@endif
        </section>
    </div>
    <div class="premium-admin-bottom">
        <section class="premium-panel"><div class="premium-panel-heading"><h2>{{ $overview->chief()?'Merchant Applications':'Available Account Tools' }}</h2>@if($overview->chief())<a href="{{ route('admin.applications') }}">View all</a>@endif</div>
        @if($overview->chief())
        <div class="product-data-scroll" role="region" aria-label="Scoped applications" tabindex="0"><table class="product-table"><thead><tr><th>Business name</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($reviewApplications ?? [] as $application)<tr><td>{{ $application->business_name }}</td><td><span class="product-badge" data-state="{{ $application->status }}">{{ \App\ViewModels\ApplicationHistoryEntry::label($application->status) }}</span></td><td><a href="{{ route('admin.applications.show',['application'=>$application->id]) }}">Review details</a></td></tr>@empty<tr><td colspan="3">Open review to retrieve authorized application records.</td></tr>@endforelse</tbody></table></div>
        @else<p>Use your account and permission summary. Unassigned merchant records and financial tools are unavailable.</p><a href="{{ route('account') }}">Open your account</a>@endif
        </section>
        <section class="premium-panel"><div class="premium-panel-heading"><h2>Recorded Activity</h2></div>
        @if($overview->chief())@forelse($reviewApplications ?? [] as $application)<article class="premium-activity"><span class="premium-metric-icon">@include('components.premium-icon',['name'=>'check'])</span><div><b>{{ \App\ViewModels\ApplicationHistoryEntry::label($application->status) }}</b><p>{{ $application->business_name }}</p><small>{{ $application->reviewed_at ?? $application->submitted_at ?? 'Time not recorded' }} · UTC</small></div></article>@empty<p class="product-muted">No scoped activity records are provisioned here. No system-wide audit access is implied.</p>@endforelse
        @else<p class="product-muted">Your current role assignment is active. No assigned-case activity feed is provisioned.</p>@endif
        </section>
        <section class="premium-panel"><div class="premium-panel-heading"><h2>Access &amp; Safety Alerts</h2></div><div class="premium-alert-row"><span class="premium-metric-icon is-pending">@include('components.premium-icon',['name'=>'pending'])</span><div><b>Operational NO-GO</b><p>Live payments, withdrawals, APIs and external webhooks remain disabled.</p></div></div>
        @if($overview->chief() && isset($reviewScope))<div class="premium-alert-row"><div><b>{{ $reviewScope['Pending applications'] ?? 0 }} applications awaiting review</b><p>Within your current authorized scope.</p></div></div>@endif
        <p class="product-muted">Service health is not monitored in this preview.</p></section>
    </div>
</div>
@endif
