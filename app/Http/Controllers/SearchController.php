<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private AuditService $auditService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $query = $request->string('q')->trim()->toString();
        $medicines = strlen($query) >= 1
            ? Medicine::query()->with('category')->withInventory()->search($query)->limit(10)->get()
            : collect();
        $batches = strlen($query) >= 1
            ? MedicineBatch::query()->with('medicine')->whereHas('medicine', fn ($medicineQuery) => $medicineQuery->where('status', 'active'))->where(function ($batchQuery) use ($query): void {
                $batchQuery->where('batch_number', 'like', "%{$query}%")
                    ->orWhere('lot_number', 'like', "%{$query}%");
            })->limit(6)->get()
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
            'scan_action' => ['nullable', Rule::in(['lookup', 'stock_in', 'restock', 'new_medicine', 'dispense'])],
        ]);

        $searchTerm = trim($data['barcode']);
        $medicine = Medicine::query()
            ->where('barcode', $searchTerm)
            ->orWhere('medicine_code', $searchTerm)
            ->first();

        if (! $medicine && preg_match('/^\d{13}$/', $searchTerm)) {
            $medicine = Medicine::query()
                ->whereNotNull('barcode')
                ->get()
                ->first(fn (Medicine $candidate): bool => $candidate->scannableBarcodeValue() === $searchTerm);
        }

        $action = $data['scan_action'] ?? 'lookup';

        if (! $medicine) {
            if ($action === 'new_medicine' && ! $request->user()?->hasPermission('stock.receive')) {
                return back()
                    ->withInput()
                    ->withErrors(['barcode' => 'You do not have permission to receive stock for a new medicine.']);
            }

            if (! auth()->user()?->hasPermission('medicines.create')) {
                return back()
                    ->withInput()
                    ->withErrors(['barcode' => "No medicine matches barcode or medicine ID: {$searchTerm}. You do not have permission to register new medicines."]);
            }

            return redirect()->route('scanner.register', ['barcode' => $searchTerm])
                ->with('info', "No medicine found for barcode: {$searchTerm}. Please register the medicine.");
        }

        if ($action === 'new_medicine') {
            abort_unless($request->user()?->hasPermission('medicines.create') && $request->user()?->hasPermission('stock.receive'), 403);

            return back()
                ->withInput()
                ->withErrors(['barcode' => "{$medicine->generic_name} is already registered. Select Restock this medicine to receive another batch."]);
        }

        if (in_array($action, ['stock_in', 'restock'], true)) {
            abort_unless($request->user()?->hasPermission('stock.receive'), 403);

            return redirect()->route('stock.in', ['medicine' => $medicine, 'restock' => 1])
                ->with('success', "Medicine found: {$medicine->generic_name}. Complete the receipt details to replenish stock safely.");
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
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        abort_unless(
            auth()->user()?->hasPermission('medicines.create') && auth()->user()?->hasPermission('stock.receive'),
            403,
            'You do not have permission to register and receive a new medicine.'
        );

        return view('scanner.register', compact('barcode', 'categories', 'suppliers'));
    }

    public function registerFromScanner(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('medicines.create') && auth()->user()?->hasPermission('stock.receive'), 403);

        $data = $request->validate([
            'barcode' => ['required', 'string', 'min:3', 'max:80'],
            'generic_name' => ['required', 'string'],
            'brand_name' => ['nullable', 'string'],
            'medicine_category_id' => ['required', 'exists:medicine_categories,id'],
            'strength' => ['nullable', 'string'],
            'unit' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'expiration_date' => ['required', 'date', 'after:today'],
            'arrival_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock_level' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $data['storage_location_id'] = StorageLocation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');

        if ($data['storage_location_id'] === null) {
            throw ValidationException::withMessages([
                'storage_location_id' => 'Add an active storage location before registering stock.',
            ]);
        }

        $data['maximum_stock_level'] = max(100, (int) ($data['minimum_stock_level'] ?? 10));

        $existingMedicine = Medicine::withTrashed()->where('barcode', $data['barcode'])->first();
        if ($existingMedicine) {
            if ($existingMedicine->trashed()) {
                DB::transaction(function () use ($data, $existingMedicine, $request): void {
                    $existingMedicine->restore();
                    $existingMedicine->update([
                        'generic_name' => $data['generic_name'],
                        'brand_name' => $data['brand_name'] ?? null,
                        'medicine_category_id' => $data['medicine_category_id'],
                        'dosage' => $existingMedicine->dosage,
                        'strength' => $data['strength'] ?? null,
                        'dosage_form' => $existingMedicine->dosage_form,
                        'unit' => $data['unit'],
                        'unit_cost' => $data['unit_cost'] ?? 0,
                        'minimum_stock_level' => $data['minimum_stock_level'] ?? 10,
                        'maximum_stock_level' => $data['maximum_stock_level'],
                        'reorder_level' => $data['minimum_stock_level'] ?? 10,
                        'storage_condition' => $existingMedicine->storage_condition,
                        'description' => $data['description'] ?? null,
                        'status' => 'active',
                    ]);

                    $this->auditService->record(
                        'medicine_restored',
                        "Restored {$existingMedicine->generic_name} from scanner registration.",
                        $existingMedicine,
                    );
                    $this->receiveScannedStock($existingMedicine, $data, $request->user(), 'Initial stock received after scanner restoration.');
                });

                return redirect()->route('medicines.show', $existingMedicine)
                    ->with('success', "Medicine {$existingMedicine->generic_name} ({$existingMedicine->medicine_code}) was restored and registered with {$data['quantity']} units.");
            }

            return back()
                ->withInput()
                ->withErrors(['barcode' => "A medicine with barcode {$data['barcode']} already exists in the system. You can view or edit the existing medicine."])
                ->with('existing_medicine_id', $existingMedicine->id);
        }

        $medicine = DB::transaction(function () use ($data, $request): Medicine {
            $medicine = Medicine::create([
                'barcode' => $data['barcode'],
                'medicine_code' => 'MED-'.Str::upper((string) Str::ulid()),
                'generic_name' => $data['generic_name'],
                'brand_name' => $data['brand_name'] ?? null,
                'medicine_category_id' => $data['medicine_category_id'],
                'dosage' => null,
                'strength' => $data['strength'] ?? null,
                'dosage_form' => 'Unspecified',
                'unit' => $data['unit'],
                'unit_cost' => $data['unit_cost'] ?? 0,
                'minimum_stock_level' => $data['minimum_stock_level'] ?? 10,
                'maximum_stock_level' => $data['maximum_stock_level'],
                'reorder_level' => $data['minimum_stock_level'] ?? 10,
                'storage_condition' => null,
                'description' => $data['description'] ?? null,
                'status' => 'active',
            ]);

            $this->auditService->record(
                'medicine_created',
                "Created {$medicine->generic_name} from scanner registration.",
                $medicine,
                null,
                $medicine->toArray(),
            );
            $this->receiveScannedStock($medicine, $data, $request->user(), 'Initial stock received from scanner registration.');

            return $medicine;
        });

        return redirect()->route('medicines.show', $medicine)
            ->with('success', "Medicine {$medicine->generic_name} ({$medicine->medicine_code}) registered successfully with {$data['quantity']} units.");
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
            'recipient' => ['required', 'string', 'max:255'],
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
            'recipient' => $data['recipient'],
            'remarks' => $data['notes'] ?? null,
        ], auth()->user());

        return redirect()->route('medicines.show', $medicine)
            ->with('success', "Successfully dispensed {$data['quantity']} {$medicine->unit} of {$medicine->generic_name}.");
    }

    public function scanner(): View
    {
        return view('search.scanner');
    }

    /** @param array<string, mixed> $data */
    private function receiveScannedStock(Medicine $medicine, array $data, User $user, string $remarks): void
    {
        $this->inventoryService->stockIn([
            'medicine_id' => $medicine->id,
            'supplier_id' => $data['supplier_id'],
            'storage_location_id' => $data['storage_location_id'],
            'batch_number' => 'SCAN-'.Str::upper((string) Str::ulid()),
            'lot_number' => null,
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'] ?? 0,
            'expiration_date' => $data['expiration_date'],
            'received_at' => $data['arrival_date'],
            'reference_number' => 'SCANNER-'.Str::upper((string) Str::ulid()),
            'remarks' => $remarks,
        ], $user);
    }
}
