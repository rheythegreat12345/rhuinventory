<?php

namespace Database\Factories;

use App\Models\InventoryNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryNotification>
 */
class InventoryNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'system_event',
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(),
            'level' => fake()->randomElement(['info', 'success', 'warning']),
            'data' => [],
            'read_at' => null,
        ];
    }
}
