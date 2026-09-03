<?php

namespace Database\Factories;

use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\User;
use App\TransactionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_code' => 'TXN-'.now()->format('YmdHis').'-'.Str::upper(fake()->unique()->bothify('?????')),
            'medicine_id' => Medicine::factory(),
            'medicine_batch_id' => MedicineBatch::factory(),
            'user_id' => User::factory(),
            'type' => TransactionType::StockIn,
            'quantity' => 10,
            'previous_stock' => 0,
            'new_stock' => 10,
            'transacted_at' => now(),
        ];
    }
}
