<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'seller_id' => User::factory()->seller(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
