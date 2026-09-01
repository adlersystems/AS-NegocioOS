<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('AS-NegocioOS');
    }

    public function test_dashboard_renders_metrics_and_chart_markup(): void
    {
        $user = User::factory()->admin()->create();
        $client = Client::factory()->create(['pending_balance' => 150.50]);
        $product = Product::factory()->create([
            'name' => 'Producto Estrella',
            'stock' => 12,
            'min_stock' => 4,
            'production_cost' => 10,
            'sale_price' => 20,
        ]);
        $seller = User::factory()->seller()->create();

        $sale = Sale::create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'subtotal' => 100,
            'tax_amount' => 12,
            'total' => 112,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 20,
            'total' => 60,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Producto Estrella', false)
            ->assertSee('Q 150.50', false)
            ->assertSee('Q 112.00', false)
            ->assertSee('chart-sales-monthly', false)
            ->assertSee('chart-revenue-trend', false)
            ->assertSee('chart-top-products', false)
            ->assertSee('chart-by-seller', false);
    }

    public function test_arpu_and_revenue_aggregates_are_calculated(): void
    {
        $user = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $seller = User::factory()->seller()->create();

        foreach ([100, 200] as $total) {
            Sale::create([
                'client_id' => $client->id,
                'seller_id' => $seller->id,
                'subtotal' => $total,
                'tax_amount' => 0,
                'total' => $total,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Q 300.00', false)
            ->assertSee('Q 150.00', false);
    }

    public function test_low_stock_and_expiring_alerts_are_visible(): void
    {
        $user = User::factory()->admin()->create();

        Product::factory()->lowStock()->create([
            'stock' => 1,
            'min_stock' => 5,
        ]);

        Product::factory()->create([
            'stock' => 20,
            'expiration_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Stock bajo', false)
            ->assertSee('Por vencer', false);
    }
}
