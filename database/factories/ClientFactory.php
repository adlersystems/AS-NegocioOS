<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('####-####'),
            'address' => fake()->address(),
            'nit' => fake()->numerify('########-#'),
            'pending_balance' => fake()->randomFloat(2, 0, 1500),
            'preferred_language' => fake()->randomElement(['es', 'en']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
