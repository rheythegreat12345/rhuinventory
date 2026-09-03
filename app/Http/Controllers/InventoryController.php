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
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function stockIn(): View
    {
        return view('inventory.stock-in', [
            'medicines' => Medicine::query()->where('status', 'active')->orderBy('generic_name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => StorageLocation::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeStockIn(Request $request, InventoryService $inventoryService): RedirectResponse
    {
        $data = $request->validate([
            'medicine_id' => ['required', 'exists:medicines,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'storage_location_id' => ['required', 'exists:storage_locations,id'],
            'batch_number' => ['required', 'string', 'max:80'],
            'lot_number' => ['nullable', 'string', 'max:80'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'manufacturing_date' => ['nullable', 'date', 'before:expiration_date'],
            'expiration_date' => ['required', 'date', 'after_or_equal:today'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

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
            'type' => ['required', Rule::in([TransactionType::StockOut->value, TransactionType::Dispensed->value])],
            'recipient' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'transacted_at' => ['required', 'date', 'before_or_equal:now'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

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
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $transaction = $inventoryService->adjust($data, $request->user());

        return redirect()->route('transactions.index', ['search' => $transaction->transaction_code])->with('success', 'Stock adjustment completed and audited.');
    }

    public function lowStock(): View
    {
        $medicines = Medicine::query()->where('status', 'active')->with('category')->withInventory()->get()
            ->filter(fn (Medicine $medicine) => $medicine->stockStatus() !== 'normal')
            ->sortBy(fn (Medicine $medicine) => ['out' => 0, 'critical' => 1, 'low' => 2][$medicine->stockStatus()]);

        return view('inventory.low-stock', compact('medicines'));
    }

    public function expirations(Request $request): View
    {
        $status = $request->string('status')->toString();
        $batches = MedicineBatch::query()->with(['medicine', 'supplier', 'storageLocation'])->where('quantity', '>', 0)
            ->when($status === 'expired', fn ($query) => $query->whereDate('expiration_date', '<', today()))
            ->when(in_array($status, ['7', '30', '60', '90'], true), fn ($query) => $query->whereBetween('expiration_date', [today(), today()->addDays((int) $status)]))
            ->when($status === 'safe', fn ($query) => $query->whereDate('expiration_date', '>', today()->addDays(90)))
            ->orderBy('expiration_date')
            ->paginate(15)
            ->withQueryString();

        return view('inventory.expirations', compact('batches'));
    }
}
