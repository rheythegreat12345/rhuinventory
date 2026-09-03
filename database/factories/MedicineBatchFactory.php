<?php

namespace Database\Factories;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicineBatch>
 */
class MedicineBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medicine_id' => Medicine::factory(),
            'supplier_id' => Supplier::factory(),
            'storage_location_id' => StorageLocation::factory(),
            'batch_number' => fake()->unique()->bothify('B-####-??'),
            'lot_number' => fake()->bothify('LOT-#####'),
            'manufacturing_date' => now()->subYear(),
            'expiration_date' => now()->addYear(),
            'quantity' => fake()->numberBetween(10, 100),
            'unit_cost' => fake()->randomFloat(2, 1, 50),
            'received_at' => now()->subMonth(),
            'status' => 'active',
        ];
    }
}
