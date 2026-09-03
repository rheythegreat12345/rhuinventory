<?php

namespace Database\Factories;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medicine_category_id' => MedicineCategory::factory(),
            'medicine_code' => fake()->unique()->bothify('MED-####'),
            'barcode' => fake()->unique()->ean13(),
            'generic_name' => fake()->unique()->word(),
            'brand_name' => fake()->company(),
            'dosage' => '1 tablet',
            'strength' => fake()->randomElement(['100 mg', '250 mg', '500 mg']),
            'dosage_form' => fake()->randomElement(['Tablet', 'Capsule', 'Syrup']),
            'unit' => fake()->randomElement(['tablets', 'capsules', 'bottles']),
            'minimum_stock_level' => 10,
            'maximum_stock_level' => 100,
            'reorder_level' => 20,
            'unit_cost' => fake()->randomFloat(2, 1, 50),
            'reference_price' => fake()->randomFloat(2, 1, 75),
            'storage_condition' => 'Store below 30°C in a dry place.',
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
