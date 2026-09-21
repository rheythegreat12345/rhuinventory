@extends('layouts.app')
@section('title', 'Dispense Medicine')
@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('scanner') }}">Scanner</a><x-icon name="chevron" class="icon-sm" /> Dispense</div>
        <h1 class="page-title">Dispense medicine</h1>
        <p class="page-subtitle">Quick dispensing for scanned medicine: {{ $medicine->generic_name }}</p>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom: 20px; box-shadow: none">
    <x-icon name="info" />
    <span><strong>{{ $medicine->generic_name }} ({{ $medicine->medicine_code }})</strong> - Barcode: {{ $medicine->barcode }} | Available stock: {{ $availableStock }} {{ $medicine->unit }}</span>
</div>

<form method="POST" action="{{ route('scanner.dispense.store', $medicine) }}">
    @csrf
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Dispense information</h2>
                <p class="card-subtitle">Enter the quantity and details for dispensing.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label class="required" for="quantity">Quantity to dispense</label>
                    <input class="input" id="quantity" name="quantity" type="number" min="1" max="{{ $availableStock }}" value="{{ old('quantity', 1) }}" required placeholder="Enter quantity">
                    <div class="help-text">Maximum available: {{ $availableStock }} {{ $medicine->unit }}</div>
                    @error('quantity')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="required" for="recipient">Patient name or recipient</label>
                    <input class="input" id="recipient" name="recipient" value="{{ old('recipient') }}" required placeholder="Enter patient name or department">
                    <div class="help-text">Required so this dispensing can be traced safely.</div>
                    @error('recipient')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="purpose">Purpose</label>
                    <input class="input" id="purpose" name="purpose" value="{{ old('purpose', 'General dispensing') }}" placeholder="Reason for dispensing">
                    <div class="help-text">Why is this medicine being dispensed?</div>
                    @error('purpose')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field span-2">
                    <label for="notes">Additional notes</label>
                    <textarea class="textarea" id="notes" name="notes" placeholder="Any additional information about this dispensing">{{ old('notes') }}</textarea>
                    @error('notes')<div class="error-text">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 18px">
        <div class="card-header">
            <h2 class="card-title">Medicine details</h2>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label>Generic name</label>
                    <div class="input" style="background: var(--background-muted)">{{ $medicine->generic_name }}</div>
                </div>
                <div class="field">
                    <label>Brand name</label>
                    <div class="input" style="background: var(--background-muted)">{{ $medicine->brand_name ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Dosage form</label>
                    <div class="input" style="background: var(--background-muted)">{{ $medicine->dosage_form }}</div>
                </div>
                <div class="field">
                    <label>Strength</label>
                    <div class="input" style="background: var(--background-muted)">{{ $medicine->strength ?? 'N/A' }}</div>
                </div>
                <div class="field">
                    <label>Unit</label>
                    <div class="input" style="background: var(--background-muted)">{{ $medicine->unit }}</div>
                </div>
                <div class="field">
                    <label>Current stock</label>
                    <div class="input" style="background: var(--background-muted)">{{ $availableStock }} {{ $medicine->unit }}</div>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn btn-secondary" href="{{ route('scanner') }}">Cancel</a>
                <button class="btn btn-primary" type="submit"><x-icon name="check" class="icon-sm" /> Dispense medicine</button>
            </div>
        </div>
    </div>
</form>
@endsection
