<?php

namespace App\Services;

use App\AdjustmentReason;
use App\Models\InventoryNotification;
use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockAdjustment;
use App\Models\User;
use App\TransactionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        public AuditService $auditService,
        public AlertService $alertService,
    ) {}

    /** @param array<string, mixed> $data */
    public function stockIn(array $data, User $user): InventoryTransaction
    {
        return DB::transaction(function () use ($data, $user): InventoryTransaction {
            $batch = MedicineBatch::query()
                ->where('medicine_id', $data['medicine_id'])
                ->where('batch_number', $data['batch_number'])
                ->lockForUpdate()
                ->first();

            if ($batch && ! $batch->expiration_date->isSameDay($data['expiration_date'])) {
                throw ValidationException::withMessages([
                    'batch_number' => 'This batch already exists with a different expiration date.',
                ]);
            }

            $batch ??= MedicineBatch::query()->create([
                'medicine_id' => $data['medicine_id'],
                'supplier_id' => $data['supplier_id'],
                'storage_location_id' => $data['storage_location_id'],
                'batch_number' => $data['batch_number'],
                'lot_number' => $data['lot_number'] ?? null,
                'manufacturing_date' => $data['manufacturing_date'] ?? null,
                'expiration_date' => $data['expiration_date'],
                'quantity' => 0,
                'unit_cost' => $data['unit_cost'],
                'received_at' => $data['received_at'],
                'status' => 'active',
            ]);

            $previousStock = $batch->quantity;
            $batch->update([
                'quantity' => $batch->quantity + $data['quantity'],
                'supplier_id' => $data['supplier_id'],
                'storage_location_id' => $data['storage_location_id'],
                'unit_cost' => $data['unit_cost'],
                'received_at' => $data['received_at'],
            ]);

            $transaction = $this->createTransaction($batch, $user, TransactionType::StockIn, $data['quantity'], $previousStock, [
                'transacted_at' => $data['received_at'],
                'reference_number' => $data['reference_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            InventoryNotification::query()->create([
                'type' => 'stock_received',
                'title' => 'Stock received',
                'message' => "Received {$data['quantity']} {$batch->medicine->unit} of {$batch->medicine->generic_name}.",
                'level' => 'success',
                'data' => ['medicine_id' => $batch->medicine_id, 'url' => route('medicines.show', $batch->medicine_id)],
            ]);

            $this->auditService->record('stock_in', "Received stock for {$batch->medicine->generic_name} ({$batch->batch_number}).", $transaction, null, $transaction->toArray());
            $this->alertService->syncMedicine($batch->medicine);

            return $transaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, InventoryTransaction>
     */
    public function stockOut(array $data, User $user): Collection
    {
        return DB::transaction(function () use ($data, $user): Collection {
            $medicine = Medicine::query()->findOrFail($data['medicine_id']);
            $requestedQuantity = (int) $data['quantity'];
            $remaining = $requestedQuantity;
            $transactions = collect();

            $batches = MedicineBatch::query()
                ->where('medicine_id', $medicine->id)
                ->when($data['medicine_batch_id'] ?? null, fn ($query, $batchId) => $query->whereKey($batchId))
                ->availableFefo()
                ->lockForUpdate()
                ->get();

            if ($batches->sum('quantity') < $requestedQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$batches->sum('quantity')} non-expired {$medicine->unit} are available.",
                ]);
            }

            foreach ($batches as $batch) {
                if ($remaining === 0) {
                    break;
                }

                $deduction = min($remaining, $batch->quantity);
                $previousStock = $batch->quantity;
                $batch->decrement('quantity', $deduction);

                $transactions->push($this->createTransaction(
                    $batch->fresh(),
                    $user,
                    TransactionType::from($data['type']),
                    $deduction,
                    $previousStock,
                    $data,
                ));
                $remaining -= $deduction;
            }

            $batchList = $transactions->pluck('batch.batch_number')->filter()->join(', ');
            $this->auditService->record('stock_out', "Released {$requestedQuantity} {$medicine->unit} of {$medicine->generic_name} using FEFO batches: {$batchList}.", $medicine);
            $this->alertService->syncMedicine($medicine);

            return $transactions;
        });
    }

    /** @param array<string, mixed> $data */
    public function adjust(array $data, User $user): InventoryTransaction
    {
        return DB::transaction(function () use ($data, $user): InventoryTransaction {
            $batch = MedicineBatch::query()->with('medicine')->lockForUpdate()->findOrFail($data['medicine_batch_id']);
            $difference = (int) $data['quantity_difference'];

            if ($batch->quantity + $difference < 0) {
                throw ValidationException::withMessages([
                    'quantity_difference' => "The adjustment cannot reduce stock below zero. Current batch stock is {$batch->quantity}.",
                ]);
            }

            $previousStock = $batch->quantity;
            $batch->update(['quantity' => $batch->quantity + $difference]);

            $transaction = $this->createTransaction($batch, $user, TransactionType::Adjustment, abs($difference), $previousStock, [
                'reason' => AdjustmentReason::from($data['reason'])->label(),
                'remarks' => $data['notes'] ?? null,
                'transacted_at' => now(),
                'metadata' => ['quantity_difference' => $difference],
            ]);

            StockAdjustment::query()->create([
                'inventory_transaction_id' => $transaction->id,
                'reason' => $data['reason'],
                'quantity_difference' => $difference,
                'notes' => $data['notes'] ?? null,
            ]);

            if (abs($difference) >= 100) {
                InventoryNotification::query()->create([
                    'type' => 'large_adjustment',
                    'title' => 'Large stock adjustment',
                    'message' => "{$batch->medicine->generic_name} was adjusted by {$difference} {$batch->medicine->unit}.",
                    'level' => 'warning',
                    'data' => ['medicine_id' => $batch->medicine_id, 'url' => route('transactions.index', ['search' => $transaction->transaction_code])],
                ]);
            }

            $this->auditService->record('stock_adjustment', "Adjusted {$batch->medicine->generic_name} batch {$batch->batch_number} by {$difference}.", $transaction, ['quantity' => $previousStock], ['quantity' => $batch->quantity]);
            $this->alertService->syncMedicine($batch->medicine);

            return $transaction;
        });
    }

    /** @param array<string, mixed> $details */
    private function createTransaction(MedicineBatch $batch, User $user, TransactionType $type, int $quantity, int $previousStock, array $details): InventoryTransaction
    {
        return InventoryTransaction::query()->create([
            'transaction_code' => 'TXN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
            'medicine_id' => $batch->medicine_id,
            'medicine_batch_id' => $batch->id,
            'user_id' => $user->id,
            'type' => $type,
            'quantity' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $batch->quantity,
            'recipient' => $details['recipient'] ?? null,
            'purpose' => $details['purpose'] ?? null,
            'transacted_at' => $details['transacted_at'] ?? now(),
            'reference_number' => $details['reference_number'] ?? null,
            'reason' => $details['reason'] ?? null,
            'remarks' => $details['remarks'] ?? null,
            'metadata' => $details['metadata'] ?? null,
        ]);
    }

    public function recordTransaction(Medicine $medicine, int $quantity, string $type, string $remarks, ?int $batchId = null, ?string $transactedAt = null): InventoryTransaction
    {
        return DB::transaction(function () use ($medicine, $quantity, $type, $remarks, $batchId, $transactedAt): InventoryTransaction {
            $transactionType = TransactionType::tryFrom($type) ?? TransactionType::StockIn;
            $user = auth()->user();

            return InventoryTransaction::query()->create([
                'transaction_code' => 'TXN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
                'medicine_id' => $medicine->id,
                'medicine_batch_id' => $batchId,
                'user_id' => $user?->id,
                'type' => $transactionType,
                'quantity' => $quantity,
                'previous_stock' => 0,
                'new_stock' => $quantity,
                'transacted_at' => $transactedAt ? now()->parse($transactedAt) : now(),
                'remarks' => $remarks,
            ]);
        });
    }
}
