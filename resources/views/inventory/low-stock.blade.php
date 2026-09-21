@extends('layouts.app')

@section('title', 'Low Stock Alerts')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><x-icon name="chevron" class="icon-sm" /> Low stock</div>
        <h1 class="page-title">Low stock alerts</h1>
        <p class="page-subtitle">Automatic reorder recommendations based on each medicine's current and target stock.</p>
    </div>
    <div class="page-actions">
        @if(auth()->user()->hasPermission('reports.view'))
            <a class="btn btn-secondary" href="{{ route('reports.index', ['report' => 'low_stock']) }}"><x-icon name="report" class="icon-sm" /> Open report</a>
        @endif
        @if(auth()->user()->hasPermission('stock.receive'))
            <a class="btn btn-primary" href="{{ route('stock.in') }}"><x-icon name="stock-in" class="icon-sm" /> Receive stock</a>
        @endif
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
    @foreach(['out' => 'Out of stock', 'critical' => 'Critical', 'low' => 'Low stock'] as $status => $label)
        <div class="card stat-card">
            <span class="stat-icon {{ $status === 'low' ? 'amber' : 'red' }}"><x-icon name="alert" /></span>
            <span class="stat-copy">
                <span class="stat-label">{{ $label }}</span>
                <span class="stat-value">{{ $alertMedicines->filter(fn ($medicine) => $medicine->stockStatus() === $status)->count() }}</span>
                <span class="stat-hint">Medicines</span>
            </span>
        </div>
    @endforeach
    <div class="card stat-card">
        <span class="stat-icon teal"><x-icon name="stock-in" /></span>
        <span class="stat-copy">
            <span class="stat-label">Suggested reorder</span>
            <span class="stat-value">{{ number_format($alertMedicines->sum(fn ($medicine) => $medicine->recommendedReorderQuantity())) }}</span>
            <span class="stat-hint">Total units</span>
        </span>
    </div>
</div>

<form class="card filter-bar" method="GET">
    <div class="filters" style="grid-template-columns:1fr auto">
        <div class="field filter-search">
            <label for="search">Search low-stock medicines</label>
            <div class="input-wrap">
                <x-icon name="search" class="icon-sm" />
                <input class="input" id="search" name="search" value="{{ request('search') }}" placeholder="Medicine, code, brand, or category">
            </div>
        </div>
        <button class="btn btn-primary" type="submit">Search</button>
    </div>
</form>

<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">{{ $medicines->count() }} medicines need attention</h2>
            <p class="card-subtitle">Critical status is at or below half the minimum level.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Current</th>
                    <th>Minimum</th>
                    <th>Reorder level</th>
                    <th>Recommended qty</th>
                    <th>Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($medicines as $medicine)
                    <tr>
                        <td><a class="table-primary" href="{{ route('medicines.show', $medicine) }}">{{ $medicine->generic_name }}</a><span class="table-secondary">{{ $medicine->medicine_code }} · {{ $medicine->strength }}</span></td>
                        <td>{{ $medicine->category->name }}</td>
                        <td><strong>{{ number_format($medicine->current_stock ?? 0) }}</strong> {{ $medicine->unit }}</td>
                        <td>{{ $medicine->minimum_stock_level }}</td>
                        <td>{{ $medicine->reorder_level }}</td>
                        <td><strong>{{ $medicine->recommendedReorderQuantity() }}</strong> {{ $medicine->unit }}</td>
                        <td><x-status-badge :status="$medicine->stockStatus()" /></td>
                        <td class="text-right">
                            @if(auth()->user()->hasPermission('stock.receive'))
                                <a class="btn btn-primary btn-sm" href="{{ route('stock.in', ['medicine' => $medicine->id]) }}">Restock</a>
                            @else
                                <a class="btn btn-secondary btn-sm" href="{{ route('medicines.show', $medicine) }}">View</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <span class="empty-icon"><x-icon name="check" /></span>
                                <h3>{{ request('search') ? 'No matching low-stock medicines' : 'Stock levels are healthy' }}</h3>
                                <p>{{ request('search') ? 'Try a different medicine name, code, brand, or category.' : 'No medicine is at or below its minimum stock level.' }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
