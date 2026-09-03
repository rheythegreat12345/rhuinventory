@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-header">
    <div><div class="breadcrumb">Overview <x-icon name="chevron" class="icon-sm" /> Dashboard</div><h1 class="page-title">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ str(auth()->user()->name)->before(' ') }}</h1><p class="page-subtitle">Here’s the live inventory position for {{ $systemSettings['facility_name'] ?? 'your health facility' }}.</p></div>
    <div class="page-actions">
        @if(auth()->user()->hasPermission('reports.view'))<a class="btn btn-secondary" href="{{ route('reports.index') }}"><x-icon name="report" class="icon-sm" /> Generate report</a>@endif
        @if(auth()->user()->hasPermission('stock.receive'))<a class="btn btn-primary" href="{{ route('stock.in') }}"><x-icon name="stock-in" class="icon-sm" /> Receive stock</a>@endif
    </div>
</div>

<section class="stats-grid" aria-label="Inventory statistics">
    @php
        $cards = [
            ['Total Medicines',$statistics['total_medicines'],'medicine','',route('medicines.index'),'Active catalog records'],
            ['Total Stock',$statistics['total_stock'],'boxes','teal',route('medicines.index'),'All batches combined'],
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
        ['data' => $movement['received'], 'color' => '#0d9488'],
        ['data' => $movement['dispensed'], 'color' => '#2885f6'],
        ['data' => $movement['adjusted'], 'color' => '#d97706'],
    ]];
    $inventoryChart = ['type' => 'doughnut', 'data' => $inventoryStatus->values(), 'colors' => ['#1cad71', '#e8a22d', '#ef6b5e', '#aeb8c5'], 'centerLabel' => 'Medicines'];
    $expirationChart = ['type' => 'doughnut', 'data' => array_values($expiration), 'colors' => ['#e24d5b', '#ef9f2f', '#efcf54', '#9aa8b5'], 'centerLabel' => 'Batches'];
@endphp
<div class="grid grid-3" style="grid-template-columns:2fr 1fr 1fr;margin-bottom:18px">
    <section class="card">
        <div class="card-header"><div><h2 class="card-title">Stock movement</h2><p class="card-subtitle">Received, dispensed, and adjusted over the last 14 days</p></div><div class="legend"><span class="legend-item"><i class="legend-dot" style="background:#0d9488"></i>Received</span><span class="legend-item"><i class="legend-dot" style="background:#2885f6"></i>Released</span><span class="legend-item"><i class="legend-dot" style="background:#d97706"></i>Adjusted</span></div></div>
        <div class="card-body"><div class="chart-wrap"><canvas class="chart" data-chart='{{ json_encode($movementChart) }}'></canvas></div></div>
    </section>
    <section class="card"><div class="card-header"><div><h2 class="card-title">Inventory health</h2><p class="card-subtitle">Status by medicine</p></div></div><div class="card-body"><div class="chart-wrap small"><canvas class="chart" data-chart='{{ json_encode($inventoryChart) }}'></canvas></div><div class="legend" style="justify-content:center"><span class="legend-item"><i class="legend-dot" style="background:#1cad71"></i>Normal</span><span class="legend-item"><i class="legend-dot" style="background:#e8a22d"></i>Low</span><span class="legend-item"><i class="legend-dot" style="background:#ef6b5e"></i>Critical</span><span class="legend-item"><i class="legend-dot" style="background:#aeb8c5"></i>Out</span></div></div></section>
    <section class="card"><div class="card-header"><div><h2 class="card-title">Expiration outlook</h2><p class="card-subtitle">Batches requiring attention</p></div></div><div class="card-body"><div class="chart-wrap small"><canvas class="chart" data-chart='{{ json_encode($expirationChart) }}'></canvas></div><div class="legend" style="justify-content:center">@foreach($expiration as $label=>$count)<span class="legend-item"><i class="legend-dot" style="background:{{ ['#e24d5b','#ef9f2f','#efcf54','#9aa8b5'][$loop->index] }}"></i>{{ $label }}</span>@endforeach</div></div></section>
</div>

<div class="grid grid-3" style="grid-template-columns:1.6fr 1fr 1fr">
    <section class="card">
        <div class="card-header"><div><h2 class="card-title">Recent transactions</h2><p class="card-subtitle">Latest inventory activity across all batches</p></div><a class="link" href="{{ route('transactions.index') }}">View history</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Transaction</th><th>Medicine</th><th>Type</th><th>Quantity</th><th>When</th></tr></thead><tbody>
        @forelse($recentTransactions as $transaction)<tr><td><span class="table-primary mono">{{ $transaction->transaction_code }}</span><span class="table-secondary">{{ $transaction->batch?->batch_number }}</span></td><td><a class="table-primary" href="{{ route('medicines.show',$transaction->medicine) }}">{{ $transaction->medicine->generic_name }}</a><span class="table-secondary">{{ $transaction->user?->name ?? 'System' }}</span></td><td><span class="badge badge-info">{{ $transaction->type->label() }}</span></td><td><strong>{{ number_format($transaction->quantity) }}</strong> {{ $transaction->medicine->unit }}</td><td>{{ $transaction->transacted_at->diffForHumans() }}</td></tr>@empty<tr><td colspan="5"><div class="empty-state"><p>No transactions recorded yet.</p></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="card"><div class="card-header"><div><h2 class="card-title">Stock attention</h2><p class="card-subtitle">Lowest inventory levels</p></div><a class="link" href="{{ route('inventory.low-stock') }}">View all</a></div><div class="card-body" style="display:grid;gap:17px">
        @forelse($lowStockMedicines as $medicine)<a href="{{ route('medicines.show',$medicine) }}"><div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:6px"><span><strong>{{ $medicine->generic_name }}</strong><small class="table-secondary">{{ $medicine->current_stock }} {{ $medicine->unit }} left</small></span><x-status-badge :status="$medicine->stockStatus()" /></div><div class="progress {{ in_array($medicine->stockStatus(),['out','critical']) ? 'danger' : 'warning' }}"><span style="width:{{ min(100,($medicine->current_stock / max(1,$medicine->minimum_stock_level))*100) }}%"></span></div></a>@empty<div class="empty-state"><x-icon name="check" /><p>All stock levels are healthy.</p></div>@endforelse
    </div></section>
    <section class="card"><div class="card-header"><div><h2 class="card-title">Quick actions</h2><p class="card-subtitle">Common inventory tasks</p></div></div><div class="card-body"><div class="quick-actions" style="grid-template-columns:1fr 1fr">
        @if(auth()->user()->hasPermission('stock.receive'))<a class="quick-action" href="{{ route('stock.in') }}"><x-icon name="stock-in" /><strong>Stock in</strong><span>Receive a batch</span></a>@endif
        @if(auth()->user()->hasPermission('stock.release'))<a class="quick-action" href="{{ route('stock.out') }}"><x-icon name="stock-out" /><strong>Dispense</strong><span>FEFO release</span></a>@endif
        @if(auth()->user()->hasPermission('medicines.create'))<a class="quick-action" href="{{ route('medicines.create') }}"><x-icon name="plus" /><strong>Medicine</strong><span>Add a record</span></a>@endif
        <a class="quick-action" href="{{ route('scanner') }}"><x-icon name="scan" /><strong>Scan code</strong><span>Find medicine</span></a>
    </div><div style="margin-top:20px"><h3 class="card-title" style="margin-bottom:12px">Expiring next</h3>@forelse($expiringBatches as $batch)<a class="notification-item" href="{{ route('medicines.show',$batch->medicine) }}" style="padding:7px 0"><span class="notification-icon warning"><x-icon name="calendar" class="icon-sm" /></span><span class="notification-copy"><strong>{{ $batch->medicine->generic_name }}</strong><p>{{ $batch->batch_number }} · {{ $batch->expiration_date->format('M d, Y') }}</p></span></a>@empty<p class="card-subtitle">No batches expire within 90 days.</p>@endforelse</div></div></section>
</div>
@endsection
