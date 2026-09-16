<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    /**
     * Generate realistic demo data: clients, products and sales history.
     */
    public function run(): void
    {
        $sellers = User::where('role', User::ROLE_SELLER)->get();
        $manager = User::where('role', User::ROLE_MANAGER)->first() ?? User::factory()->manager()->create();
        $iva = (float) Setting::get('iva_percentage', '12');

        $clients = Client::factory()->count(22)->create();

        $products = Product::factory()->count(28)->create();
        $products = $products->concat(Product::factory()->lowStock()->count(6)->create())->values();
        $products = $products->concat(Product::factory()->outOfStock()->count(3)->create())->values();

        foreach ($products as $product) {
            $initial = max($product->stock, 1);

            InventoryMovement::factory()->create([
                'product_id' => $product->id,
                'user_id' => $manager->id,
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => $initial + 20,
                'reason' => 'Compra inicial',
            ]);
        }

        if ($sellers->isEmpty()) {
            $sellers = collect([User::factory()->seller()->create()]);
        }

        $start = now()->subMonths(6)->startOfDay();

        for ($i = 0; $i < 180; $i++) {
            $date = $start->addDays(fake()->numberBetween(0, 30))->addHours(fake()->numberBetween(8, 18));
            $client = $clients->random();
            $seller = $sellers->random();
            $availableProducts = $products->filter(fn (Product $p) => $p->stock > 0);
            $itemCount = fake()->numberBetween(1, 4);
            $chosen = $availableProducts->random(min($itemCount, $availableProducts->count()));

            if ($chosen->isEmpty()) {
                continue;
            }

            $sale = Sale::create([
                'client_id' => $client->id,
                'seller_id' => $seller->id,
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'tax_rate' => $iva,
                'notes' => fake()->optional(0.2)->sentence(),
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $subtotal = 0;

            foreach ($chosen as $product) {
                $quantity = fake()->numberBetween(1, 5);
                $unitPrice = (float) $product->sale_price;
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'cost' => (float) $product->production_cost,
                    'total' => round($lineTotal, 2),
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                $product->decrement('stock', $quantity);

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $seller->id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'quantity' => $quantity,
                    'reason' => 'Venta #'.$sale->id,
                    'reference' => $sale->invoiceNumber(),
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }

            $taxAmount = round($subtotal * ($iva / 100), 2);

            $sale->update([
                'subtotal' => round($subtotal, 2),
                'tax_amount' => $taxAmount,
                'total' => round($subtotal + $taxAmount, 2),
            ]);
        }

        foreach ($clients->random(8) as $client) {
            $client->update([
                'pending_balance' => fake()->randomFloat(2, 50, 2000),
            ]);
        }
    }
}
