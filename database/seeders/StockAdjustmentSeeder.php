<?php

namespace Database\Seeders;

use App\Models\InventoryTransaction;
use App\Models\MedicineBatch;
use App\Models\StockAdjustment;
use App\Models\User;
use App\TransactionType;
use Illuminate\Database\Seeder;

class StockAdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $batch = MedicineBatch::query()->where('quantity', '>', 20)->first();
        $user = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'inventory-staff'))->first();

        if (! $batch || ! $user) {
            return;
        }

        $transaction = InventoryTransaction::query()->create([
            'transaction_code' => 'TXN-SEED-ADJ-0001',
            'medicine_id' => $batch->medicine_id,
            'medicine_batch_id' => $batch->id,
            'user_id' => $user->id,
            'type' => TransactionType::Adjustment,
            'quantity' => 2,
            'previous_stock' => $batch->quantity + 2,
            'new_stock' => $batch->quantity,
            'transacted_at' => now()->subDays(3),
            'reason' => 'Damaged medicine',
            'remarks' => 'Two damaged units removed during shelf inspection.',
            'metadata' => ['quantity_difference' => -2],
        ]);

        StockAdjustment::query()->create([
            'inventory_transaction_id' => $transaction->id,
            'reason' => 'damaged',
            'quantity_difference' => -2,
            'notes' => 'Packaging damaged during routine shelf inspection.',
        ]);
    }
}
