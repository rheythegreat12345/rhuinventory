@extends('layouts.app')
@section('title', 'Register New Medicine')
@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('scanner') }}">Scanner</a><x-icon name="chevron" class="icon-sm" /> Register Medicine</div>
        <h1 class="page-title">Register new medicine</h1>
        <p class="page-subtitle">The scanned barcode was not found. Register the medicine with initial stock information.</p>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom: 20px; box-shadow: none">
    <x-icon name="info" />
    <span><strong>Barcode: {{ $barcode }}</strong> - This barcode is not in the system. Please complete the medicine registration below.</span>
</div>

@if(session('existing_medicine_id'))
<div class="alert alert-warning" style="margin-bottom: 20px; box-shadow: none">
    <x-icon name="warning" />
    <span><strong>Medicine already exists!</strong> A medicine with this barcode is already in the system. <a href="{{ route('medicines.show', session('existing_medicine_id')) }}" class="alert-link">View the existing medicine</a> instead.</span>
</div>
@endif

<form method="POST" action="{{ route('scanner.register.store') }}">
    @csrf
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Medicine information</h2>
                <p class="card-subtitle">Basic medicine details and identification.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label class="required" for="barcode">Barcode</label>
                    <input class="input mono" id="barcode" name="barcode" value="{{ old('barcode', $barcode) }}" required readonly>
                    <div class="help-text">This barcode was scanned and cannot be changed.</div>
                </div>
                <div class="field">
                    <label class="required" for="generic_name">Generic name</label>
                    <input class="input" id="generic_name" name="generic_name" value="{{ old('generic_name') }}" required placeholder="e.g. Paracetamol">
                    @error('generic_name')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="brand_name">Brand name</label>
                    <input class="input" id="brand_name" name="brand_name" value="{{ old('brand_name') }}" placeholder="e.g. Biogesic">
                    @error('brand_name')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="required" for="medicine_category_id">Category</label>
                    <select class="select" id="medicine_category_id" name="medicine_category_id" required>
                        <option value="">Choose category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string)old('medicine_category_id') === (string)$category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('medicine_category_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="strength">Strength</label>
                    <input class="input" id="strength" name="strength" value="{{ old('strength') }}" placeholder="e.g. 500 mg">
                    @error('strength')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="required" for="unit">Inventory unit</label>
                    <select class="select" id="unit" name="unit" required>
                        <option value="">Choose unit</option>
                        @foreach(['Tablets', 'Capsules', 'Bottles', 'Vials', 'Ampoules', 'Sachets', 'Tubes', 'Drops', 'Inhalers', 'Boxes'] as $unit)
                            <option value="{{ $unit }}" @selected(old('unit') === $unit)>{{ $unit }}</option>
                        @endforeach
                    </select>
                    @error('unit')<div class="error-text">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 18px">
        <div class="card-header">
            <div>
                <h2 class="card-title">Initial stock information</h2>
                <p class="card-subtitle">Details for the first batch of this medicine.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label class="required" for="quantity">Quantity</label>
                    <input class="input" id="quantity" name="quantity" type="number" min="1" value="{{ old('quantity') }}" required placeholder="Initial stock quantity">
                    <div class="help-text">Number of units being added to inventory.</div>
                    @error('quantity')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="required" for="supplier_id">Supplier</label>
                    <select class="select" id="supplier_id" name="supplier_id" required>
                        <option value="">Choose supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }} ({{ $supplier->code }})</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="required" for="expiration_date">Expiration date</label>
                    <input class="input" id="expiration_date" name="expiration_date" type="date" value="{{ old('expiration_date') }}" required min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                    <div class="help-text">Must be a future date.</div>
                    @error('expiration_date')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="required" for="arrival_date">Arrival date</label>
                    <input class="input" id="arrival_date" name="arrival_date" type="date" value="{{ old('arrival_date', date('Y-m-d')) }}" required max="{{ date('Y-m-d') }}">
                    <div class="help-text">Date when the medicine was received.</div>
                    @error('arrival_date')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="unit_cost">Unit cost (₱)</label>
                    <input class="input" id="unit_cost" name="unit_cost" type="number" min="0" step="0.01" value="{{ old('unit_cost') }}" placeholder="0.00">
                    @error('unit_cost')<div class="error-text">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 18px">
        <div class="card-header">
            <div>
                <h2 class="card-title">Stock thresholds</h2>
                <p class="card-subtitle">Used for alerts and reorder recommendations.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label for="minimum_stock_level">Minimum stock</label>
                    <input class="input" id="minimum_stock_level" name="minimum_stock_level" type="number" min="0" value="{{ old('minimum_stock_level', 10) }}" placeholder="10">
                    @error('minimum_stock_level')<div class="error-text">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 18px">
        <div class="card-header">
            <h2 class="card-title">Description</h2>
        </div>
        <div class="card-body">
            <div class="field">
                <label for="description">Medicine notes</label>
                <textarea class="textarea" id="description" name="description" placeholder="Additional information about this medicine">{{ old('description') }}</textarea>
                @error('description')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="form-actions">
                <a class="btn btn-secondary" href="{{ route('scanner') }}">Cancel</a>
                <button class="btn btn-primary" type="submit"><x-icon name="check" class="icon-sm" /> Register medicine</button>
            </div>
        </div>
    </div>
</form>
@endsection
