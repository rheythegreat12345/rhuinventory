<?php

namespace Database\Seeders;

use App\Models\InventoryTransaction;
use App\Models\MedicineBatch;
use App\Models\User;
use App\TransactionType;
use Illuminate\Database\Seeder;

class InventoryTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()->orderBy('id')->get();

        MedicineBatch::query()->with('medicine')->orderBy('id')->get()->each(function (MedicineBatch $batch, int $index) use ($users): void {
            $dispensed = $batch->quantity > 10 && $index % 3 !== 0 ? min(8 + ($index % 7), $batch->quantity) : 0;
            $received = $batch->quantity + $dispensed;

            InventoryTransaction::query()->create([
                'transaction_code' => 'TXN-SEED-IN-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'medicine_id' => $batch->medicine_id,
                'medicine_batch_id' => $batch->id,
                'user_id' => $users[$index % $users->count()]->id,
                'type' => TransactionType::StockIn,
                'quantity' => $received,
                'previous_stock' => 0,
                'new_stock' => $received,
                'transacted_at' => now()->subDays(40 - ($index % 35))->setTime(9 + ($index % 7), 15),
                'reference_number' => 'PO-2026-'.str_pad((string) ($index + 100), 4, '0', STR_PAD_LEFT),
                'remarks' => 'Opening stock receipt for demonstration.',
            ]);

            if ($dispensed > 0) {
                InventoryTransaction::query()->create([
                    'transaction_code' => 'TXN-SEED-OUT-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'medicine_id' => $batch->medicine_id,
                    'medicine_batch_id' => $batch->id,
                    'user_id' => $users[($index + 1) % $users->count()]->id,
                    'type' => $index % 2 === 0 ? TransactionType::Dispensed : TransactionType::StockOut,
                    'quantity' => $dispensed,
                    'previous_stock' => $received,
                    'new_stock' => $batch->quantity,
                    'recipient' => 'RHU Patient Ref '.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'purpose' => $index % 2 === 0 ? 'Patient dispensing' : 'Clinic program release',
                    'transacted_at' => now()->subDays($index % 14)->setTime(10 + ($index % 6), 30),
                    'reference_number' => 'RX-'.today()->format('ym').'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'remarks' => 'Sample historical release.',
                ]);
            }
        });
    }
}
