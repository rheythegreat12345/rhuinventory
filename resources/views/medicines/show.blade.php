@extends('layouts.app')
@section('title',$medicine->generic_name)
@section('content')
<div class="page-header"><div><div class="breadcrumb"><a href="{{ route('medicines.index') }}">Medicines</a><x-icon name="chevron" class="icon-sm" /> {{ $medicine->medicine_code }}</div><div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><h1 class="page-title">{{ $medicine->generic_name }}</h1><x-status-badge :status="$medicine->stockStatus()" /></div><p class="page-subtitle">{{ $medicine->brand_name ?: 'Generic medicine' }} · {{ $medicine->strength }} · {{ $medicine->dosage_form }}</p></div><div class="page-actions">
    <button class="btn btn-secondary" type="button" onclick="window.print()"><x-icon name="report" class="icon-sm" /> Print label</button>
    @if(auth()->user()->hasPermission('stock.release'))<a class="btn btn-secondary" href="{{ route('stock.out',['medicine'=>$medicine->id]) }}"><x-icon name="stock-out" class="icon-sm" /> Dispense</a>@endif
    @if(auth()->user()->hasPermission('stock.receive'))<a class="btn btn-primary" href="{{ route('stock.in',['medicine'=>$medicine->id]) }}"><x-icon name="stock-in" class="icon-sm" /> Receive batch</a>@endif
    @if(auth()->user()->hasPermission('medicines.edit'))<a class="btn btn-secondary" href="{{ route('medicines.edit',$medicine) }}"><x-icon name="edit" class="icon-sm" /> Edit</a>@endif
</div></div>

<div class="stats-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="card stat-card"><span class="stat-icon teal"><x-icon name="boxes" /></span><span class="stat-copy"><span class="stat-label">Current stock</span><span class="stat-value">{{ number_format($medicine->current_stock ?? 0) }}</span><span class="stat-hint">{{ $medicine->unit }} across {{ $medicine->batches->count() }} batches</span></span></div>
    <div class="card stat-card"><span class="stat-icon amber"><x-icon name="alert" /></span><span class="stat-copy"><span class="stat-label">Minimum level</span><span class="stat-value">{{ number_format($medicine->minimum_stock_level) }}</span><span class="stat-hint">Reorder at {{ $medicine->reorder_level }}</span></span></div>
    <div class="card stat-card"><span class="stat-icon"><x-icon name="stock-in" /></span><span class="stat-copy"><span class="stat-label">Recommended reorder</span><span class="stat-value">{{ number_format($medicine->recommendedReorderQuantity()) }}</span><span class="stat-hint">Target {{ $medicine->maximum_stock_level }}</span></span></div>
    <div class="card stat-card"><span class="stat-icon purple"><x-icon name="money" /></span><span class="stat-copy"><span class="stat-label">Inventory value</span><span class="stat-value">₱{{ number_format($medicine->batches->sum(fn($batch)=>$batch->quantity*(float)$batch->unit_cost),2) }}</span><span class="stat-hint">At received batch cost</span></span></div>
</div>

<section class="card" data-medicine-tabs>
    @php
        $medicineMovementChart = ['type' => 'line', 'labels' => $movement->pluck('movement_date'), 'datasets' => [
            ['data' => $movement->pluck('total'), 'color' => '#0d9488'],
        ]];
    @endphp
    <div class="tabs" data-tabs><button class="tab active" data-tab="overview">Overview</button><button class="tab" data-tab="batches">Batches ({{ $medicine->batches->count() }})</button><button class="tab" data-tab="transactions">Transactions</button><button class="tab" data-tab="history">Audit history</button></div>
    <div class="tab-panel active" data-tab-panel="overview">
        <div class="grid grid-2" style="grid-template-columns:1.3fr .7fr">
            <div><h2 class="card-title" style="padding:0 16px 8px">Medicine profile</h2><dl class="details-grid">
                <div class="detail"><dt>Medicine ID</dt><dd class="mono">{{ $medicine->medicine_code }}</dd></div><div class="detail"><dt>Category</dt><dd>{{ $medicine->category->name }}</dd></div><div class="detail"><dt>Status</dt><dd><x-status-badge :status="$medicine->status" /></dd></div>
                <div class="detail"><dt>Generic name</dt><dd>{{ $medicine->generic_name }}</dd></div><div class="detail"><dt>Brand name</dt><dd>{{ $medicine->brand_name ?: '—' }}</dd></div><div class="detail"><dt>Strength</dt><dd>{{ $medicine->strength ?: '—' }}</dd></div>
                <div class="detail"><dt>Dosage form</dt><dd>{{ $medicine->dosage_form }}</dd></div><div class="detail"><dt>Dosage reference</dt><dd>{{ $medicine->dosage ?: '—' }}</dd></div><div class="detail"><dt>Inventory unit</dt><dd>{{ $medicine->unit }}</dd></div>
                <div class="detail"><dt>Default cost</dt><dd>₱{{ number_format((float)$medicine->unit_cost,2) }}</dd></div><div class="detail"><dt>Reference price</dt><dd>{{ $medicine->reference_price ? '₱'.number_format((float)$medicine->reference_price,2) : '—' }}</dd></div><div class="detail"><dt>Last updated</dt><dd>{{ $medicine->updated_at->format('M d, Y g:i A') }}</dd></div>
                <div class="detail span-2" style="grid-column:span 3"><dt>Storage condition</dt><dd>{{ $medicine->storage_condition ?: 'No special condition recorded' }}</dd></div>
                <div class="detail" style="grid-column:span 3"><dt>Description</dt><dd>{{ $medicine->description ?: 'No description recorded.' }}</dd></div>
            </dl></div>
            <div><div class="card" style="box-shadow:none"><div class="card-header"><div><h3 class="card-title">Barcode / scan code</h3><p class="card-subtitle">Use this value with a hardware or camera scanner.</p></div></div><div class="card-body" style="text-align:center"><div style="height:72px;border-radius:8px;background:repeating-linear-gradient(90deg,var(--text) 0 2px,transparent 2px 5px,var(--text) 5px 6px,transparent 6px 10px);opacity:.82"></div><div class="mono" style="font-size:15px;letter-spacing:.15em;margin-top:10px">{{ $medicine->barcode ?: $medicine->medicine_code }}</div></div></div>
            <div class="card" style="box-shadow:none;margin-top:15px"><div class="card-header"><h3 class="card-title">30-day movement</h3></div><div class="card-body"><div class="chart-wrap small"><canvas class="chart" data-chart='{{ json_encode($medicineMovementChart) }}'></canvas></div></div></div></div>
        </div>
    </div>
    <div class="tab-panel" data-tab-panel="batches"><div class="table-wrap"><table class="data-table"><thead><tr><th>Batch / lot</th><th>Supplier</th><th>Location</th><th>Quantity</th><th>Received</th><th>Expiration</th><th>Status</th></tr></thead><tbody>
        @forelse($medicine->batches as $batch)<tr><td><span class="table-primary mono">{{ $batch->batch_number }}</span><span class="table-secondary">{{ $batch->lot_number ?: 'No lot number' }}</span></td><td>{{ $batch->supplier?->name ?? '—' }}</td><td>{{ $batch->storageLocation->name }}</td><td><strong>{{ number_format($batch->quantity) }}</strong> {{ $medicine->unit }}<span class="table-secondary">₱{{ number_format((float)$batch->unit_cost,2) }} each</span></td><td>{{ $batch->received_at?->format('M d, Y') ?? '—' }}</td><td>{{ $batch->expiration_date->format('M d, Y') }}<span class="table-secondary">{{ $batch->expiration_date->diffForHumans() }}</span></td><td><x-status-badge :status="$batch->expirationStatus()" /></td></tr>
        @empty<tr><td colspan="7"><div class="empty-state"><span class="empty-icon"><x-icon name="boxes" /></span><h3>No batches yet</h3><p>Receive stock to create the first batch for this medicine.</p></div></td></tr>@endforelse
    </tbody></table></div></div>
    <div class="tab-panel" data-tab-panel="transactions"><div class="table-wrap"><table class="data-table"><thead><tr><th>ID</th><th>Batch</th><th>Type</th><th>Quantity</th><th>Previous → New</th><th>User</th><th>Date</th></tr></thead><tbody>
        @forelse($medicine->transactions as $transaction)<tr><td class="mono">{{ $transaction->transaction_code }}</td><td>{{ $transaction->batch?->batch_number ?? '—' }}</td><td><span class="badge badge-info">{{ $transaction->type->label() }}</span></td><td>{{ $transaction->quantity }} {{ $medicine->unit }}</td><td>{{ $transaction->previous_stock }} → {{ $transaction->new_stock }}</td><td>{{ $transaction->user?->name ?? 'System' }}</td><td>{{ $transaction->transacted_at->format('M d, Y g:i A') }}</td></tr>@empty<tr><td colspan="7"><div class="empty-state"><p>No transactions yet.</p></div></td></tr>@endforelse
    </tbody></table></div></div>
    <div class="tab-panel" data-tab-panel="history"><div class="timeline">@forelse($audits as $audit)<div class="timeline-item"><span class="timeline-dot"></span><strong>{{ str($audit->action)->replace('_',' ')->headline() }}</strong><p>{{ $audit->description }}</p><time>{{ $audit->created_at->format('M d, Y g:i A') }} · {{ $audit->user?->name ?? 'System' }} · {{ $audit->ip_address ?: 'No IP' }}</time></div>@empty<div class="empty-state"><p>No audit entries for this medicine.</p></div>@endforelse</div></div>
</section>

@if(auth()->user()->hasPermission('medicines.edit'))<div class="card no-print" style="margin-top:18px;border-color:#ffd5d5"><div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:20px"><div><h3 class="card-title">Archive medicine</h3><p class="card-subtitle">The catalog record will be hidden, while all transaction history remains preserved.</p></div><form method="POST" action="{{ route('medicines.destroy',$medicine) }}" data-confirm="Archive {{ $medicine->generic_name }}? Its history will be kept, but it will no longer appear in active inventory.">@csrf @method('DELETE')<button class="btn btn-danger" type="submit"><x-icon name="trash" class="icon-sm" /> Archive</button></form></div></div>@endif
@endsection
