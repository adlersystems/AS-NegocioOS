<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed demo users for each role.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Administrador',
            'email' => 'admin@as-negocios.com',
        ]);

        User::factory()->seller()->create([
            'name' => 'Vendedora Principal',
            'email' => 'ventas@as-negocios.com',
        ]);

        User::factory()->manager()->create([
            'name' => 'Encargado de Bodega',
            'email' => 'bodega@as-negocios.com',
        ]);
    }
}
