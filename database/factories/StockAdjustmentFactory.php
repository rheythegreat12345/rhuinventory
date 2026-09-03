<?php

namespace Database\Factories;

use App\Models\InventoryTransaction;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_transaction_id' => InventoryTransaction::factory(),
            'reason' => 'inventory_correction',
            'quantity_difference' => 1,
            'notes' => fake()->sentence(),
        ];
    }
}
