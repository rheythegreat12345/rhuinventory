@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-header">
    <div><div class="breadcrumb">Overview <x-icon name="chevron" class="icon-sm" /> Dashboard</div><h1 class="page-title" data-time-greeting data-user-name="{{ auth()->user()->name }}">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}</h1><p class="page-subtitle">Here’s the live inventory position for {{ $systemSettings['facility_name'] ?? 'your health facility' }}.</p></div>
    <div class="page-actions">
        @if(auth()->user()->hasPermission('reports.view'))<a class="btn btn-secondary" href="{{ route('reports.index') }}"><x-icon name="report" class="icon-sm" /> Generate report</a>@endif
        @if(auth()->user()->hasPermission('stock.receive'))<a class="btn btn-primary" href="{{ route('stock.in') }}"><x-icon name="stock-in" class="icon-sm" /> Receive stock</a>@endif
    </div>
</div>

<section class="stats-grid" aria-label="Inventory statistics">
    @php
        $cards = [
            ['Total Medicines',$statistics['total_medicines'],'medicine','',route('medicines.index'),'Active catalog records'],
            ['Total Stock',$statistics['total_stock'],'boxes','teal',route('medicines.index'),'Active, non-expired batches'],
            ['Low Stock',$statistics['low_stock'],'alert','amber',route('inventory.low-stock'),'Needs replenishment'],
            ['Out of Stock',$statistics['out_of_stock'],'stock-out','red',route('medicines.index',['stock_status'=>'out']),'Unavailable medicines'],
            ['Expiring Soon',$statistics['expiring_soon'],'calendar','amber',route('inventory.expirations',['status'=>'90']),'Within 90 days'],
            ['Expired Batches',$statistics['expired'],'alert','red',route('inventory.expirations',['status'=>'expired']),'Dispensing blocked'],
            ["Today's Activity",$statistics['today_transactions'],'transaction','purple',route('transactions.index',['date_from'=>today()->toDateString()]),'Transactions recorded'],
            ['Dispensed Today',$statistics['dispensed_today'],'stock-out','teal',route('transactions.index',['type'=>'dispensed','date_from'=>today()->toDateString()]),'Units released'],
            ['Received Today',$statistics['received_today'],'stock-in','',route('transactions.index',['type'=>'stock_in','date_from'=>today()->toDateString()]),'Units received'],
            ['Inventory Value','₱'.number_format($statistics['inventory_value'],2),'money','teal',route('reports.index'),'At batch cost'],
        ];
    @endphp
    @foreach($cards as [$label,$value,$icon,$color,$url,$hint])
        <a class="card stat-card" href="{{ $url }}"><span class="stat-icon {{ $color }}"><x-icon :name="$icon" /></span><span class="stat-copy"><span class="stat-label">{{ $label }}</span><span class="stat-value">{{ is_numeric($value) ? number_format($value) : $value }}</span><span class="stat-hint">{{ $hint }}</span></span></a>
    @endforeach
</section>

@php
    $movementChart = ['type' => 'bar', 'labels' => $movement['labels'], 'datasets' => [
        ['label' => 'Received', 'data' => $movement['received'], 'color' => '#0d9488'],
        ['label' => 'Released', 'data' => $movement['dispensed'], 'color' => '#2885f6'],
        ['label' => 'Adjusted', 'data' => $movement['adjusted'], 'color' => '#d97706'],
    ]];
    $inventoryChart = ['type' => 'doughnut', 'data' => $inventoryStatus->values(), 'colors' => ['#1cad71', '#e8a22d', '#ef6b5e', '#aeb8c5'], 'centerLabel' => 'Medicines'];
    $expirationChart = ['type' => 'doughnut', 'data' => array_values($expiration), 'colors' => ['#e24d5b', '#ef9f2f', '#efcf54', '#9aa8b5'], 'centerLabel' => 'Batches'];
@endphp
<div class="grid dashboard-overview-grid">
    <section class="card">
        <div class="card-header"><div><h2 class="card-title">Stock movement</h2><p class="card-subtitle">Received, dispensed, and adjusted over the last 14 days</p></div><div class="legend"><span class="legend-item"><i class="legend-dot" style="background:#0d9488"></i>Received</span><span class="legend-item"><i class="legend-dot" style="background:#2885f6"></i>Released</span><span class="legend-item"><i class="legend-dot" style="background:#d97706"></i>Adjusted</span></div></div>
        <div class="card-body"><div class="chart-wrap"><canvas class="chart" data-chart='{{ json_encode($movementChart) }}'></canvas></div></div>
    </section>
    <a class="card dashboard-summary-link" href="{{ route('inventory.low-stock') }}" aria-label="View low stock alerts"><div class="card-header"><div><h2 class="card-title">Inventory health</h2><p class="card-subtitle">Status by medicine</p></div></div><div class="card-body"><div class="chart-wrap small"><canvas class="chart" data-chart='{{ json_encode($inventoryChart) }}'></canvas></div><div class="legend" style="justify-content:center"><span class="legend-item"><i class="legend-dot" style="background:#1cad71"></i>Normal</span><span class="legend-item"><i class="legend-dot" style="background:#e8a22d"></i>Low</span><span class="legend-item"><i class="legend-dot" style="background:#ef6b5e"></i>Critical</span><span class="legend-item"><i class="legend-dot" style="background:#aeb8c5"></i>Out</span></div></div></a>
    <a class="card dashboard-summary-link" href="{{ route('inventory.expirations') }}" aria-label="View expiration monitoring"><div class="card-header"><div><h2 class="card-title">Expiration outlook</h2><p class="card-subtitle">Batches requiring attention</p></div></div><div class="card-body"><div class="chart-wrap small"><canvas class="chart" data-chart='{{ json_encode($expirationChart) }}'></canvas></div><div class="legend" style="justify-content:center">@foreach($expiration as $label=>$count)<span class="legend-item"><i class="legend-dot" style="background:{{ ['#e24d5b','#ef9f2f','#efcf54','#9aa8b5'][$loop->index] }}"></i>{{ $label }}</span>@endforeach</div></div></a>
</div>

<div class="grid dashboard-workspace-grid">
    <section class="card">
        <div class="card-header"><div><h2 class="card-title">Recent transactions</h2><p class="card-subtitle">Latest inventory activity across all batches</p></div><a class="link" href="{{ route('transactions.index') }}">View history</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Transaction</th><th>Medicine</th><th>Type</th><th>Quantity</th><th>When</th></tr></thead><tbody>
        @forelse($recentTransactions as $transaction)<tr><td><span class="table-primary mono">{{ $transaction->transaction_code }}</span><span class="table-secondary">{{ $transaction->batch?->batch_number }}</span></td><td><a class="table-primary" href="{{ route('medicines.show',$transaction->medicine) }}">{{ $transaction->medicine->generic_name }}</a><span class="table-secondary">{{ $transaction->user?->name ?? 'System' }}</span></td><td><span class="badge badge-info">{{ $transaction->type->label() }}</span></td><td><strong>{{ number_format($transaction->quantity) }}</strong> {{ $transaction->medicine->unit }}</td><td>{{ $transaction->transacted_at->diffForHumans() }}</td></tr>@empty<tr><td colspan="5"><div class="empty-state"><p>No transactions recorded yet.</p></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="card"><div class="card-header"><div><h2 class="card-title">Stock attention</h2><p class="card-subtitle">Lowest inventory levels</p></div><a class="link" href="{{ route('inventory.low-stock') }}">View all</a></div><div class="card-body dashboard-stock-attention-list">
        @forelse($lowStockMedicines as $medicine)<a class="dashboard-stock-attention-item" href="{{ route('medicines.show',$medicine) }}"><div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:6px"><span><strong>{{ $medicine->generic_name }}</strong><small class="table-secondary">{{ $medicine->usableStockQuantity() }} {{ $medicine->unit }} left</small></span><x-status-badge :status="$medicine->stockStatus()" /></div><div class="progress {{ in_array($medicine->stockStatus(),['out','critical']) ? 'danger' : 'warning' }}"><span style="width:{{ min(100,($medicine->usableStockQuantity() / max(1,$medicine->minimum_stock_level))*100) }}%"></span></div></a>@empty<div class="empty-state"><x-icon name="check" /><p>All stock levels are healthy.</p></div>@endforelse
    </div></section>
    <section class="card dashboard-action-center">
        <div class="card-header dashboard-action-center-header">
            <div><h2 class="card-title">Quick actions</h2><p class="card-subtitle">Start a common inventory task</p></div>
            <span class="dashboard-action-center-hint"><x-icon name="bolt" class="icon-sm" /> Workspace shortcuts</span>
        </div>
        <div class="card-body dashboard-action-center-body">
            <div class="dashboard-action-area">
                <div class="quick-actions dashboard-quick-actions">
                    @if(auth()->user()->hasPermission('stock.receive'))<a class="quick-action" href="{{ route('stock.in') }}"><span class="quick-action-icon"><x-icon name="stock-in" /></span><span class="quick-action-copy"><strong>Stock in</strong><span>Receive a batch</span></span><x-icon name="chevron" class="quick-action-chevron icon-sm" /></a>@endif
                    @if(auth()->user()->hasPermission('stock.release'))<a class="quick-action" href="{{ route('stock.out') }}"><span class="quick-action-icon"><x-icon name="stock-out" /></span><span class="quick-action-copy"><strong>Dispense</strong><span>FEFO release</span></span><x-icon name="chevron" class="quick-action-chevron icon-sm" /></a>@endif
                    @if(auth()->user()->hasPermission('medicines.create'))<a class="quick-action" href="{{ route('medicines.create') }}"><span class="quick-action-icon"><x-icon name="plus" /></span><span class="quick-action-copy"><strong>Medicine</strong><span>Add a record</span></span><x-icon name="chevron" class="quick-action-chevron icon-sm" /></a>@endif
                    <a class="quick-action" href="{{ route('scanner') }}"><span class="quick-action-icon"><x-icon name="scan" /></span><span class="quick-action-copy"><strong>Scan code</strong><span>Find medicine</span></span><x-icon name="chevron" class="quick-action-chevron icon-sm" /></a>
                </div>
            </div>
            <aside class="dashboard-expiring-panel" aria-labelledby="expiring-next-title">
                <div class="dashboard-expiring-head"><div><h3 class="card-title" id="expiring-next-title">Expiring next</h3><p class="card-subtitle">Batches to review soon</p></div><a class="link" href="{{ route('inventory.expirations') }}">View all</a></div>
                <div class="dashboard-expiring-list">@forelse($expiringBatches as $batch)<a class="dashboard-expiring-item" href="{{ route('medicines.show',$batch->medicine) }}"><span class="notification-icon warning"><x-icon name="calendar" class="icon-sm" /></span><span class="notification-copy"><strong>{{ $batch->medicine->generic_name }}</strong><p>{{ $batch->batch_number }} · {{ $batch->expiration_date->format('M d, Y') }}</p></span><x-icon name="chevron" class="dashboard-expiring-chevron icon-sm" /></a>@empty<div class="dashboard-expiring-empty"><x-icon name="check" class="icon-sm" /><span>No batches expire within 90 days.</span></div>@endforelse</div>
            </aside>
        </div>
    </section>
</div>
@endsection
