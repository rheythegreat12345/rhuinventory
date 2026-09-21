<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicineRequest;
use App\Http\Requests\UpdateMedicineRequest;
use App\Models\AuditLog;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $medicines = $this->filteredQuery($request)
            ->paginate(15)
            ->withQueryString();

        return view('medicines.index', [
            'medicines' => $medicines,
            'categories' => MedicineCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => StorageLocation::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('medicines.create', [
            'categories' => MedicineCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'medicine' => new Medicine(['status' => 'active', 'minimum_stock_level' => 10, 'maximum_stock_level' => 100, 'reorder_level' => 20]),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMedicineRequest $request, AuditService $auditService): RedirectResponse
    {
        $medicine = Medicine::query()->create($request->validated());
        $auditService->record('medicine_created', "Added medicine {$medicine->generic_name}.", $medicine, null, $medicine->toArray());

        return redirect()->route('medicines.show', $medicine)->with('success', 'Medicine added successfully. Add a batch through Stock In to make it available.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Medicine $medicine): View
    {
        $medicine->load([
            'category',
            'batches' => fn ($query) => $query->with(['supplier', 'storageLocation'])->orderBy('expiration_date'),
            'transactions' => fn ($query) => $query->with(['batch', 'user'])->latest('transacted_at')->limit(20),
        ])->loadSum('batches as current_stock', 'quantity');

        return view('medicines.show', [
            'medicine' => $medicine,
            'audits' => AuditLog::query()->with('user')->where('auditable_type', $medicine->getMorphClass())->where('auditable_id', $medicine->id)->latest()->limit(20)->get(),
            'movement' => $medicine->transactions()->selectRaw('DATE(transacted_at) as movement_date, type, SUM(quantity) as total')->whereDate('transacted_at', '>=', today()->subDays(29))->groupBy('movement_date', 'type')->orderBy('movement_date')->get(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Medicine $medicine): View
    {
        return view('medicines.edit', [
            'medicine' => $medicine,
            'categories' => MedicineCategory::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMedicineRequest $request, Medicine $medicine, AuditService $auditService): RedirectResponse
    {
        $oldValues = $medicine->toArray();
        $medicine->update($request->validated());
        $auditService->record('medicine_updated', "Updated medicine {$medicine->generic_name}.", $medicine, $oldValues, $medicine->fresh()->toArray());

        return redirect()->route('medicines.show', $medicine)->with('success', 'Medicine updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Medicine $medicine, AuditService $auditService): RedirectResponse
    {
        $medicine->update(['status' => 'archived']);
        $auditService->record('medicine_archived', "Archived medicine {$medicine->generic_name}.", $medicine, ['status' => 'active'], ['status' => 'archived']);
        $medicine->delete();

        return redirect()->route('medicines.index')->with('success', 'Medicine archived. Its transaction history was preserved.');
    }

    /**
     * Archive selected catalog records while preserving their inventory history.
     */
    public function bulkArchive(Request $request, AuditService $auditService): RedirectResponse
    {
        $validated = $request->validate([
            'medicine_ids' => ['required', 'array', 'min:1'],
            'medicine_ids.*' => ['integer', 'distinct', 'exists:medicines,id'],
        ]);
        $medicineIds = collect($validated['medicine_ids'])->map(fn (mixed $medicineId): int => (int) $medicineId)->unique()->values();
        $medicines = Medicine::query()->whereKey($medicineIds)->get();

        if ($medicines->count() !== $medicineIds->count()) {
            throw ValidationException::withMessages(['medicine_ids' => 'One or more selected medicines are no longer available. Refresh the list and try again.']);
        }

        DB::transaction(function () use ($medicines, $auditService): void {
            foreach ($medicines as $medicine) {
                $previousStatus = $medicine->status;
                $medicine->update(['status' => 'archived']);
                $auditService->record('medicine_archived', "Archived medicine {$medicine->generic_name}.", $medicine, ['status' => $previousStatus], ['status' => 'archived']);
                $medicine->delete();
            }
        });

        $count = $medicines->count();

        return back()->with('success', "Archived {$count} ".str('medicine')->plural($count).'. Their transaction history was preserved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $medicines = $this->filteredQuery($request)->get();

        return response()->streamDownload(function () use ($medicines): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Medicine ID', 'Generic Name', 'Brand Name', 'Category', 'Dosage Form', 'Strength', 'Unit', 'Current Stock', 'Minimum', 'Reorder', 'Unit Cost', 'Status']);
            foreach ($medicines as $medicine) {
                fputcsv($output, [
                    $medicine->medicine_code, $medicine->generic_name, $medicine->brand_name,
                    $medicine->category->name, $medicine->dosage_form, $medicine->strength,
                    $medicine->unit, $medicine->current_stock ?? 0, $medicine->minimum_stock_level,
                    $medicine->reorder_level, $medicine->unit_cost, $medicine->status,
                ]);
            }
            fclose($output);
        }, 'medicine-inventory-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['medicine_code', 'generic_name', 'brand_name', 'category_code', 'dosage', 'strength', 'dosage_form', 'unit', 'minimum_stock_level', 'maximum_stock_level', 'reorder_level', 'unit_cost', 'reference_price', 'storage_condition', 'description']);
            fputcsv($output, ['MED-021', 'Sample Generic Name', 'Sample Brand', 'OTHER', '1 tablet', '100 mg', 'Tablet', 'tablets', 10, 100, 20, 1.50, 2.00, 'Store below 30°C', 'Replace this sample row']);
            fclose($output);
        }, 'medicine-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, AuditService $auditService): RedirectResponse
    {
        $request->validate(['import_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $handle = fopen($request->file('import_file')->getRealPath(), 'rb');
        $headers = array_map(fn ($header) => trim((string) $header), fgetcsv($handle) ?: []);
        $required = ['medicine_code', 'generic_name', 'category_code', 'dosage_form', 'unit'];

        if (array_diff($required, $headers)) {
            throw ValidationException::withMessages(['import_file' => 'The CSV is missing required columns. Download the template and try again.']);
        }

        try {
            $count = DB::transaction(function () use ($handle, $headers): int {
                $imported = 0;
                $lineNumber = 1;

                while (($values = fgetcsv($handle)) !== false) {
                    $lineNumber++;

                    if (count($values) !== count($headers)) {
                        continue;
                    }

                    $row = array_combine($headers, $values);
                    if ($row === false) {
                        continue;
                    }

                    $value = static fn (string $field): string => trim((string) ($row[$field] ?? ''));
                    $category = MedicineCategory::query()->where('code', $value('category_code'))->first();
                    if (! $category || blank($value('medicine_code')) || blank($value('generic_name'))) {
                        continue;
                    }

                    $minimumStockLevel = $this->importInteger($value('minimum_stock_level'), 'minimum_stock_level', $lineNumber);
                    $maximumStockLevel = $this->importInteger($value('maximum_stock_level'), 'maximum_stock_level', $lineNumber, $minimumStockLevel);
                    $reorderLevel = $this->importInteger($value('reorder_level'), 'reorder_level', $lineNumber);

                    if ($maximumStockLevel < $minimumStockLevel) {
                        throw ValidationException::withMessages(['import_file' => "Row {$lineNumber}: maximum_stock_level cannot be lower than minimum_stock_level."]);
                    }

                    if ($reorderLevel > $maximumStockLevel) {
                        throw ValidationException::withMessages(['import_file' => "Row {$lineNumber}: reorder_level cannot be higher than maximum_stock_level."]);
                    }

                    Medicine::query()->updateOrCreate(
                        ['medicine_code' => $value('medicine_code')],
                        [
                            'medicine_category_id' => $category->id,
                            'generic_name' => $value('generic_name'),
                            'brand_name' => $value('brand_name') ?: null,
                            'dosage' => $value('dosage') ?: null,
                            'strength' => $value('strength') ?: null,
                            'dosage_form' => $value('dosage_form'),
                            'unit' => $value('unit'),
                            'minimum_stock_level' => $minimumStockLevel,
                            'maximum_stock_level' => $maximumStockLevel,
                            'reorder_level' => $reorderLevel,
                            'unit_cost' => $this->importAmount($value('unit_cost'), 'unit_cost', $lineNumber),
                            'reference_price' => $value('reference_price') !== '' ? $this->importAmount($value('reference_price'), 'reference_price', $lineNumber) : null,
                            'storage_condition' => $value('storage_condition') ?: null,
                            'description' => $value('description') ?: null,
                            'status' => 'active',
                        ],
                    );
                    $imported++;
                }

                return $imported;
            });
        } finally {
            fclose($handle);
        }
        $auditService->record('medicine_import', "Imported or updated {$count} medicines from CSV.");

        return back()->with('success', "Imported or updated {$count} medicine records.");
    }

    private function filteredQuery(Request $request): Builder
    {
        $today = today()->toDateString();
        $stockExpression = "(SELECT COALESCE(SUM(medicine_batches.quantity), 0) FROM medicine_batches WHERE medicine_batches.medicine_id = medicines.id AND medicine_batches.status = 'active' AND DATE(medicine_batches.expiration_date) >= '{$today}')";
        $query = Medicine::query()->with('category')->withInventory()->search($request->string('search')->toString());

        $query->when($request->integer('category'), fn (Builder $builder, int $categoryId) => $builder->where('medicine_category_id', $categoryId))
            ->when($request->integer('supplier'), fn (Builder $builder, int $supplierId) => $builder->whereHas('batches', fn (Builder $batchQuery) => $batchQuery->where('supplier_id', $supplierId)))
            ->when($request->integer('location'), fn (Builder $builder, int $locationId) => $builder->whereHas('batches', fn (Builder $batchQuery) => $batchQuery->where('storage_location_id', $locationId)))
            ->when($request->date('date_from'), fn (Builder $builder, $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($request->date('date_to'), fn (Builder $builder, $date) => $builder->whereDate('created_at', '<=', $date));

        match ($request->string('stock_status')->toString()) {
            'out' => $query->whereRaw("{$stockExpression} = 0"),
            'critical' => $query->whereRaw("{$stockExpression} > 0 AND ({$stockExpression} <= 1 OR {$stockExpression} * 2 <= minimum_stock_level)"),
            'low' => $query->whereRaw("{$stockExpression} > 0 AND {$stockExpression} <= minimum_stock_level AND {$stockExpression} > 1 AND {$stockExpression} * 2 > minimum_stock_level"),
            'normal' => $query->whereRaw("{$stockExpression} > minimum_stock_level"),
            default => null,
        };

        $expiration = $request->string('expiration')->toString();
        if ($expiration === 'expired') {
            $query->whereHas('batches', fn (Builder $batchQuery) => $batchQuery->where('quantity', '>', 0)->whereDate('expiration_date', '<', today()));
        } elseif (in_array($expiration, ['7', '30', '60', '90'], true)) {
            $query->whereHas('batches', fn (Builder $batchQuery) => $batchQuery->where('quantity', '>', 0)->whereBetween('expiration_date', [today(), today()->addDays((int) $expiration)]));
        }

        $sorts = ['generic_name', 'brand_name', 'medicine_code', 'created_at', 'unit_cost', 'current_stock'];
        $sort = in_array($request->string('sort')->toString(), $sorts, true) ? $request->string('sort')->toString() : 'generic_name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction);
    }

    private function importInteger(string $value, string $field, int $lineNumber, ?int $default = 0, int $minimum = 0): int
    {
        if ($value === '' && $default !== null) {
            return $default;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $minimum) {
            throw ValidationException::withMessages(['import_file' => "Row {$lineNumber}: {$field} must be a whole number of at least {$minimum}."]);
        }

        return (int) $value;
    }

    private function importAmount(string $value, string $field, int $lineNumber): float
    {
        if ($value === '') {
            return 0;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            throw ValidationException::withMessages(['import_file' => "Row {$lineNumber}: {$field} must be zero or a positive amount."]);
        }

        return (float) $value;
    }
}
