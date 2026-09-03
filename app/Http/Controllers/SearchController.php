<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $query = $request->string('q')->trim()->toString();
        $medicines = strlen($query) >= 1
            ? Medicine::query()->with('category')->withInventory()->search($query)->limit(10)->get()
            : collect();
        $batches = strlen($query) >= 1
            ? MedicineBatch::query()->with('medicine')->where('batch_number', 'like', "%{$query}%")->orWhere('lot_number', 'like', "%{$query}%")->limit(6)->get()
            : collect();
        $suppliers = strlen($query) >= 1
            ? Supplier::query()->where('name', 'like', "%{$query}%")->orWhere('code', 'like', "%{$query}%")->limit(6)->get()
            : collect();

        if ($request->expectsJson()) {
            return response()->json([
                'medicines' => $medicines->map(fn (Medicine $medicine) => ['label' => $medicine->generic_name, 'meta' => $medicine->medicine_code.' · '.($medicine->current_stock ?? 0).' '.$medicine->unit, 'url' => route('medicines.show', $medicine)]),
                'batches' => $batches->map(fn (MedicineBatch $batch) => ['label' => $batch->batch_number, 'meta' => $batch->medicine->generic_name, 'url' => route('medicines.show', $batch->medicine)]),
                'suppliers' => $suppliers->map(fn (Supplier $supplier) => ['label' => $supplier->name, 'meta' => $supplier->code, 'url' => route('suppliers.show', $supplier)]),
            ]);
        }

        return view('search.index', compact('query', 'medicines', 'batches', 'suppliers'));
    }

    public function barcode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'min:3', 'max:80'],
            'scan_action' => ['nullable', Rule::in(['lookup', 'stock_in', 'dispense'])],
        ]);

        $searchTerm = trim($data['barcode']);
        $medicine = Medicine::query()
            ->where('barcode', $searchTerm)
            ->orWhere('medicine_code', $searchTerm)
            ->first();

        if (! $medicine) {
            if (! auth()->user()?->hasPermission('medicines.create')) {
                return back()
                    ->withInput()
                    ->withErrors(['barcode' => "No medicine matches barcode or medicine ID: {$searchTerm}. You do not have permission to register new medicines."]);
            }

            return redirect()->route('scanner.register', ['barcode' => $searchTerm])
                ->with('info', "No medicine found for barcode: {$searchTerm}. Please register the medicine.");
        }

        $action = $data['scan_action'] ?? 'lookup';

        if ($action === 'stock_in') {
            abort_unless($request->user()?->hasPermission('stock.receive'), 403);

            return redirect()->route('stock.in', ['medicine' => $medicine])
                ->with('success', "Medicine found: {$medicine->generic_name}. Complete the receipt details to add stock safely.");
        }

        if ($action === 'dispense') {
            abort_unless($request->user()?->hasPermission('stock.release'), 403);

            return redirect()->route('scanner.dispense', ['medicine' => $medicine->id])
                ->with('success', "Medicine found: {$medicine->generic_name}. Enter quantity to dispense.");
        }

        return redirect()->route('medicines.show', $medicine)
            ->with('success', "Medicine found: {$medicine->generic_name} ({$medicine->medicine_code})");
    }

    public function showScannerRegister(Request $request): View
    {
        $barcode = $request->query('barcode', '');
        $categories = MedicineCategory::all();
        $storageLocations = StorageLocation::where('is_active', true)->get();

        if (! auth()->user()?->hasPermission('medicines.create')) {
            abort(403, 'You do not have permission to register new medicines.');
        }

        return view('scanner.register', compact('barcode', 'categories', 'storageLocations'));
    }

    public function registerFromScanner(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('medicines.create'), 403);

        $data = $request->validate([
            'barcode' => ['required', 'string', 'min:3', 'max:80'],
            'generic_name' => ['required', 'string'],
            'brand_name' => ['nullable', 'string'],
            'medicine_category_id' => ['required', 'exists:medicine_categories,id'],
            'dosage' => ['nullable', 'string'],
            'strength' => ['nullable', 'string'],
            'dosage_form' => ['required', 'string'],
            'unit' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'expiration_date' => ['required', 'date', 'after:today'],
            'arrival_date' => ['required', 'date', 'before_or_equal:today'],
            'storage_location_id' => ['required', 'exists:storage_locations,id'],
            'unit_cost' => ['nullable', 'decimal:2'],
            'minimum_stock_level' => ['nullable', 'integer', 'min:0'],
            'maximum_stock_level' => ['nullable', 'integer', 'min:0'],
            'storage_condition' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $existingMedicine = Medicine::withTrashed()->where('barcode', $data['barcode'])->first();
        if ($existingMedicine) {
            if ($existingMedicine->trashed()) {
                $existingMedicine->restore();
                $existingMedicine->update([
                    'generic_name' => $data['generic_name'],
                    'brand_name' => $data['brand_name'] ?? null,
                    'medicine_category_id' => $data['medicine_category_id'],
                    'dosage' => $data['dosage'] ?? null,
                    'strength' => $data['strength'] ?? null,
                    'dosage_form' => $data['dosage_form'],
                    'unit' => $data['unit'],
                    'unit_cost' => $data['unit_cost'] ?? 0,
                    'minimum_stock_level' => $data['minimum_stock_level'] ?? 10,
                    'maximum_stock_level' => $data['maximum_stock_level'] ?? 100,
                    'storage_condition' => $data['storage_condition'] ?? null,
                    'description' => $data['description'] ?? null,
                    'status' => 'active',
                ]);

                $batch = MedicineBatch::create([
                    'medicine_id' => $existingMedicine->id,
                    'storage_location_id' => $data['storage_location_id'],
                    'batch_number' => 'BATCH-'.date('Ymd-His'),
                    'lot_number' => 'LOT-'.date('Ymd'),
                    'quantity' => $data['quantity'],
                    'expiration_date' => $data['expiration_date'],
                    'unit_cost' => $data['unit_cost'] ?? 0,
                    'received_at' => $data['arrival_date'],
                    'status' => 'active',
                ]);

                $this->inventoryService->recordTransaction(
                    $existingMedicine,
                    $data['quantity'],
                    'stock_in',
                    'Restored medicine with new stock from scanner registration',
                    $batch->id,
                    $data['arrival_date']
                );

                return redirect()->route('medicines.show', $existingMedicine)
                    ->with('success', "Medicine {$existingMedicine->generic_name} ({$existingMedicine->medicine_code}) was restored and registered with {$data['quantity']} units.");
            }

            return back()
                ->withInput()
                ->withErrors(['barcode' => "A medicine with barcode {$data['barcode']} already exists in the system. You can view or edit the existing medicine."])
                ->with('existing_medicine_id', $existingMedicine->id);
        }

        $medicineCode = 'MED-'.str_pad(Medicine::withTrashed()->count() + 1, 3, '0', STR_PAD_LEFT);

        $medicine = Medicine::create([
            'barcode' => $data['barcode'],
            'medicine_code' => $medicineCode,
            'generic_name' => $data['generic_name'],
            'brand_name' => $data['brand_name'] ?? null,
            'medicine_category_id' => $data['medicine_category_id'],
            'dosage' => $data['dosage'] ?? null,
            'strength' => $data['strength'] ?? null,
            'dosage_form' => $data['dosage_form'],
            'unit' => $data['unit'],
            'unit_cost' => $data['unit_cost'] ?? 0,
            'minimum_stock_level' => $data['minimum_stock_level'] ?? 10,
            'maximum_stock_level' => $data['maximum_stock_level'] ?? 100,
            'storage_condition' => $data['storage_condition'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'active',
        ]);

        $batch = MedicineBatch::create([
            'medicine_id' => $medicine->id,
            'storage_location_id' => $data['storage_location_id'],
            'batch_number' => 'BATCH-'.date('Ymd-His'),
            'lot_number' => 'LOT-'.date('Ymd'),
            'quantity' => $data['quantity'],
            'expiration_date' => $data['expiration_date'],
            'unit_cost' => $data['unit_cost'] ?? 0,
            'received_at' => $data['arrival_date'],
            'status' => 'active',
        ]);

        $this->inventoryService->recordTransaction(
            $medicine,
            $data['quantity'],
            'stock_in',
            'Initial stock from scanner registration',
            $batch->id,
            $data['arrival_date']
        );

        return redirect()->route('medicines.show', $medicine)
            ->with('success', "Medicine {$medicine->generic_name} ({$medicineCode}) registered successfully with {$data['quantity']} units.");
    }

    public function showScannerDispense(Request $request, Medicine $medicine): View
    {
        abort_unless(auth()->user()?->hasPermission('stock.release'), 403);

        $medicine->load('batches');
        $availableStock = $medicine->batches()->where('status', 'active')->whereDate('expiration_date', '>=', today())->sum('quantity');

        return view('scanner.dispense', compact('medicine', 'availableStock'));
    }

    public function dispenseFromScanner(Request $request, Medicine $medicine): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('stock.release'), 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string'],
            'recipient' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $availableStock = $medicine->batches()->where('status', 'active')->whereDate('expiration_date', '>=', today())->sum('quantity');

        if ($data['quantity'] > $availableStock) {
            return back()
                ->withInput()
                ->withErrors(['quantity' => "Only {$availableStock} units are available for dispensing."]);
        }

        $this->inventoryService->stockOut([
            'medicine_id' => $medicine->id,
            'quantity' => $data['quantity'],
            'type' => 'dispensed',
            'purpose' => $data['purpose'] ?? 'General dispensing',
            'recipient' => $data['recipient'] ?? null,
            'remarks' => $data['notes'] ?? null,
        ], auth()->user());

        return redirect()->route('medicines.show', $medicine)
            ->with('success', "Successfully dispensed {$data['quantity']} {$medicine->unit} of {$medicine->generic_name}.");
    }

    public function scanner(): View
    {
        return view('search.scanner');
    }
}
