<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the company settings.
     */
    public function run(): void
    {
        Setting::setMany([
            'company_name' => 'AS-NegocioOS',
            'logo' => '',
            'address' => 'Ciudad de Guatemala, Guatemala',
            'phone' => '5555-0000',
            'nit' => '0000000-0',
            'email' => 'contacto@as-negocios.com',
            'currency' => 'GTQ',
            'iva_percentage' => '12',
            'default_language' => 'es',
        ]);
    }
}
