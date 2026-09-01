<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'sku' => fake()->unique()->bothify('SKU-####??'),
            'stock' => fake()->numberBetween(0, 120),
            'min_stock' => fake()->numberBetween(3, 12),
            'expiration_date' => fake()->optional(0.6)->dateTimeBetween('-15 days', '+120 days')?->format('Y-m-d'),
            'production_cost' => fake()->randomFloat(2, 2, 80),
            'sale_price' => fake()->randomFloat(2, 5, 200),
            'is_active' => true,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => fake()->numberBetween(0, 2),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }
}
