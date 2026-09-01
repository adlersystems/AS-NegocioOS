<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory()->manager(),
            'type' => fake()->randomElement([InventoryMovement::TYPE_IN, InventoryMovement::TYPE_OUT]),
            'quantity' => fake()->numberBetween(1, 40),
            'reason' => fake()->randomElement(['Compra inicial', 'Reposición', 'Venta', 'Ajuste', 'Merma']),
            'reference' => fake()->optional()->bothify('REF-####'),
        ];
    }
}
