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
                    <label class="required" for="dosage_form">Dosage form</label>
                    <select class="select" id="dosage_form" name="dosage_form" required>
                        <option value="">Choose form</option>
                        @foreach(['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Powder sachet', 'Ampoule', 'Vial', 'Cream', 'Ointment', 'Drops', 'Inhaler'] as $form)
                            <option value="{{ $form }}" @selected(old('dosage_form') === $form)>{{ $form }}</option>
                        @endforeach
                    </select>
                    @error('dosage_form')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="dosage">Dosage / instructions</label>
                    <input class="input" id="dosage" name="dosage" value="{{ old('dosage') }}" placeholder="e.g. 1 tablet">
                    @error('dosage')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="strength">Strength</label>
                    <input class="input" id="strength" name="strength" value="{{ old('strength') }}" placeholder="e.g. 500 mg">
                    @error('strength')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="required" for="unit">Inventory unit</label>
                    <input class="input" id="unit" name="unit" value="{{ old('unit') }}" required placeholder="tablets, bottles, vials">
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
                    <label class="required" for="storage_location_id">Storage location</label>
                    <select class="select" id="storage_location_id" name="storage_location_id" required>
                        <option value="">Choose location</option>
                        @foreach($storageLocations as $location)
                            <option value="{{ $location->id }}" @selected((string)old('storage_location_id') === (string)$location->id)>{{ $location->name }} ({{ $location->code }})</option>
                        @endforeach
                    </select>
                    @error('storage_location_id')<div class="error-text">{{ $message }}</div>@enderror
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
                <div class="field">
                    <label for="maximum_stock_level">Maximum / target stock</label>
                    <input class="input" id="maximum_stock_level" name="maximum_stock_level" type="number" min="0" value="{{ old('maximum_stock_level', 100) }}" placeholder="100">
                    @error('maximum_stock_level')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field span-2">
                    <label for="storage_condition">Storage condition</label>
                    <input class="input" id="storage_condition" name="storage_condition" value="{{ old('storage_condition') }}" placeholder="Store below 30°C in a dry place">
                    @error('storage_condition')<div class="error-text">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

        <div class="card">
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
                    <div class="field">
                        <label for="maximum_stock_level">Maximum / target stock</label>
                        <input class="input" id="maximum_stock_level" name="maximum_stock_level" type="number" min="0" value="{{ old('maximum_stock_level', 100) }}" placeholder="100">
                        @error('maximum_stock_level')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="field span-2">
                        <label for="storage_condition">Storage condition</label>
                        <input class="input" id="storage_condition" name="storage_condition" value="{{ old('storage_condition') }}" placeholder="Store below 30°C in a dry place">
                        @error('storage_condition')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
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
