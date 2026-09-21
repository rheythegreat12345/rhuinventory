<?php

namespace App\Http\Controllers;

use App\AdjustmentReason;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\TransactionType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function stockIn(Request $request): View
    {
        $selectedMedicine = Medicine::query()
            ->where('status', 'active')
            ->withInventory()
            ->find($request->integer('medicine'));

        return view('inventory.stock-in', [
            'medicines' => Medicine::query()->where('status', 'active')->orderBy('generic_name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'restockMedicine' => $request->boolean('restock') ? $selectedMedicine : null,
        ]);
    }

    public function storeStockIn(Request $request, InventoryService $inventoryService): RedirectResponse
    {
        $data = $request->validate([
            'medicine_id' => ['required', 'exists:medicines,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'storage_location_id' => ['nullable', 'exists:storage_locations,id'],
            'batch_number' => ['required', 'string', 'max:80'],
            'lot_number' => ['nullable', 'string', 'max:80'],
            'quantity' => ['required', 'integer', 'min:1'],
            'quantity_unit' => ['nullable', Rule::in(['unit', 'box'])],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'manufacturing_date' => ['nullable', 'date', 'before:expiration_date'],
            'expiration_date' => ['required', 'date', 'after_or_equal:today'],
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['received_at'] ??= today()->toDateString();
        $data['storage_location_id'] ??= StorageLocation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');

        if ($data['storage_location_id'] === null) {
            throw ValidationException::withMessages([
                'storage_location_id' => 'Add an active storage location before receiving stock.',
            ]);
        }

        $medicine = Medicine::query()->findOrFail($data['medicine_id']);
        $enteredQuantity = (int) $data['quantity'];
        $quantityUnit = $data['quantity_unit'] ?? 'unit';
        $data['quantity'] = $this->inventoryQuantityFromInput($medicine, $enteredQuantity, $quantityUnit);
        $data['metadata'] = $this->quantityMetadata($enteredQuantity, $quantityUnit, $medicine);

        $transaction = $inventoryService->stockIn($data, $request->user());

        return redirect()->route('transactions.index', ['search' => $transaction->transaction_code])->with('success', 'Stock received and batch inventory updated.');
    }

    public function stockOut(Request $request): View
    {
        return view('inventory.stock-out', [
            'medicines' => Medicine::query()->where('status', 'active')->withInventory()->orderBy('generic_name')->get(),
            'batches' => MedicineBatch::query()->with(['medicine', 'storageLocation'])->availableFefo()->get(),
            'selectedMedicine' => $request->integer('medicine'),
        ]);
    }

    public function storeStockOut(Request $request, InventoryService $inventoryService): RedirectResponse
    {
        $data = $request->validate([
            'medicine_id' => ['required', 'exists:medicines,id'],
            'medicine_batch_id' => ['nullable', 'exists:medicine_batches,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'quantity_unit' => ['nullable', Rule::in(['unit', 'box'])],
            'type' => ['required', Rule::in([TransactionType::StockOut->value, TransactionType::Dispensed->value])],
            'recipient' => ['required_if:type,dispensed', 'nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'transacted_at' => ['nullable', 'date', 'before_or_equal:now'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['transacted_at'] ??= now()->toDateTimeString();
        $medicine = Medicine::query()->findOrFail($data['medicine_id']);
        $enteredQuantity = (int) $data['quantity'];
        $quantityUnit = $data['quantity_unit'] ?? 'unit';
        $data['quantity'] = $this->inventoryQuantityFromInput($medicine, $enteredQuantity, $quantityUnit);
        $data['metadata'] = $this->quantityMetadata($enteredQuantity, $quantityUnit, $medicine);

        $transactions = $inventoryService->stockOut($data, $request->user());

        return redirect()->route('transactions.index', ['search' => $transactions->first()->reference_number ?: $transactions->first()->transaction_code])
            ->with('success', 'Stock released successfully using FEFO. '.$transactions->count().' batch transaction(s) recorded.');
    }

    public function adjustment(): View
    {
        return view('inventory.adjustment', [
            'batches' => MedicineBatch::query()->with(['medicine', 'storageLocation'])->where('quantity', '>', 0)->orderBy('expiration_date')->get(),
            'reasons' => AdjustmentReason::cases(),
        ]);
    }

    public function storeAdjustment(Request $request, InventoryService $inventoryService): RedirectResponse
    {
        $data = $request->validate([
            'medicine_batch_id' => ['required', 'exists:medicine_batches,id'],
            'quantity_difference' => ['required', 'integer', 'not_in:0'],
            'quantity_unit' => ['nullable', Rule::in(['unit', 'box'])],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $batch = MedicineBatch::query()->with('medicine')->findOrFail($data['medicine_batch_id']);
        $enteredQuantity = (int) $data['quantity_difference'];
        $quantityUnit = $data['quantity_unit'] ?? 'unit';
        $data['quantity_difference'] = $this->inventoryQuantityFromInput($batch->medicine, $enteredQuantity, $quantityUnit);
        $data['metadata'] = $this->quantityMetadata($enteredQuantity, $quantityUnit, $batch->medicine);

        $transaction = $inventoryService->adjust($data, $request->user());

        return redirect()->route('transactions.index', ['search' => $transaction->transaction_code])->with('success', 'Stock adjustment completed and audited.');
    }

    public function lowStock(Request $request): View
    {
        $alertMedicines = Medicine::query()->where('status', 'active')->with('category')->withInventory()->get()
            ->filter(fn (Medicine $medicine) => $medicine->stockStatus() !== 'normal')
            ->sortBy(fn (Medicine $medicine) => ['out' => 0, 'critical' => 1, 'low' => 2][$medicine->stockStatus()]);
        $search = trim($request->string('search')->toString());
        $medicines = $search === ''
            ? $alertMedicines
            : $alertMedicines->filter(function (Medicine $medicine) use ($search): bool {
                $searchableText = implode(' ', [
                    $medicine->generic_name,
                    $medicine->brand_name,
                    $medicine->medicine_code,
                    $medicine->category->name,
                ]);

                return str_contains(strtolower($searchableText), strtolower($search));
            })->values();

        return view('inventory.low-stock', compact('alertMedicines', 'medicines'));
    }

    public function expirations(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $batches = MedicineBatch::query()->with(['medicine', 'supplier', 'storageLocation'])->whereHas('medicine', fn ($medicineQuery) => $medicineQuery->where('status', 'active'))->where('quantity', '>', 0)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($batchQuery) use ($search): void {
                    $batchQuery->where('batch_number', 'like', "%{$search}%")
                        ->orWhere('lot_number', 'like', "%{$search}%")
                        ->orWhereHas('medicine', function ($medicineQuery) use ($search): void {
                            $medicineQuery->where('generic_name', 'like', "%{$search}%")
                                ->orWhere('brand_name', 'like', "%{$search}%")
                                ->orWhere('medicine_code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('storageLocation', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'expired', fn ($query) => $query->whereDate('expiration_date', '<', today()))
            ->when(in_array($status, ['7', '30', '60', '90'], true), fn ($query) => $query->whereBetween('expiration_date', [today(), today()->addDays((int) $status)]))
            ->when($status === 'safe', fn ($query) => $query->whereDate('expiration_date', '>', today()->addDays(90)))
            ->orderBy('expiration_date')
            ->paginate(15)
            ->withQueryString();

        return view('inventory.expirations', compact('batches'));
    }

    private function inventoryQuantityFromInput(Medicine $medicine, int $quantity, string $quantityUnit): int
    {
        if ($quantityUnit !== 'box') {
            return $quantity;
        }

        $unitsPerBox = $medicine->unitsPerBox();

        if ($unitsPerBox === null) {
            throw ValidationException::withMessages([
                'quantity_unit' => "{$medicine->generic_name} does not have a units-per-box value yet. Add it in the medicine record before using boxes.",
            ]);
        }

        if (abs($quantity) > intdiv(4_294_967_295, $unitsPerBox)) {
            throw ValidationException::withMessages([
                'quantity' => 'The box quantity is too large for the available inventory range.',
            ]);
        }

        return $quantity * $unitsPerBox;
    }

    /** @return array{entered_quantity: int, entered_unit: string, units_per_box: int|null} */
    private function quantityMetadata(int $enteredQuantity, string $quantityUnit, Medicine $medicine): array
    {
        return [
            'entered_quantity' => $enteredQuantity,
            'entered_unit' => $quantityUnit,
            'units_per_box' => $quantityUnit === 'box' ? $medicine->unitsPerBox() : null,
        ];
    }
}
