<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_and_reports_are_forbidden_for_sellers(): void
    {
        Sanctum::actingAs(User::factory()->seller()->create());

        $this->getJson('/api/inventory')->assertForbidden();
        $this->getJson('/api/reports')->assertForbidden();
    }

    public function test_inventory_and_reports_are_allowed_for_admin_and_manager(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_MANAGER] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/inventory')->assertOk();
            $this->getJson('/api/reports')->assertOk();
        }
    }

    public function test_seller_product_endpoints_hide_cost_margin_and_stock_value(): void
    {
        $product = Product::factory()->create([
            'name' => 'Costo Oculto',
            'sku' => 'COSTO-1',
            'sale_price' => 10,
            'production_cost' => 6,
            'stock' => 3,
        ]);

        Sanctum::actingAs(User::factory()->seller()->create());

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Costo Oculto')
            ->assertJsonPath('data.0.sale_price', 10)
            ->assertJsonMissingPath('data.0.production_cost')
            ->assertJsonMissingPath('data.0.margin');

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.sale_price', 10)
            ->assertJsonMissingPath('data.production_cost')
            ->assertJsonMissingPath('data.margin')
            ->assertJsonMissingPath('data.stock_value');
    }

    public function test_admin_product_endpoints_include_cost_margin_and_stock_value(): void
    {
        $product = Product::factory()->create([
            'name' => 'Costo Visible',
            'sku' => 'COSTO-2',
            'sale_price' => 10,
            'production_cost' => 6,
            'stock' => 3,
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.production_cost', 6)
            ->assertJsonPath('data.0.margin', 4);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.production_cost', 6)
            ->assertJsonPath('data.margin', 4)
            ->assertJsonPath('data.stock_value', 18);
    }

    public function test_seller_sale_show_hides_item_cost(): void
    {
        $seller = User::factory()->seller()->create();
        $sale = Sale::factory()->create(['seller_id' => $seller->id]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'cost' => 5,
            'unit_price' => 10,
            'total' => 10,
        ]);

        Sanctum::actingAs($seller);

        $this->getJson("/api/sales/{$sale->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.price', 10)
            ->assertJsonMissingPath('data.items.0.cost');
    }

    public function test_admin_sale_show_include_item_cost(): void
    {
        $sale = Sale::factory()->create();
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'cost' => 5,
            'unit_price' => 10,
            'total' => 10,
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/sales/{$sale->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.cost', 5);
    }

    public function test_seller_dashboard_hides_inventory_value_and_by_seller_chart(): void
    {
        $seller = User::factory()->seller()->create();
        Product::factory()->create(['stock' => 3, 'production_cost' => 20]);
        Sale::factory()->create(['seller_id' => $seller->id, 'total' => 100]);

        Sanctum::actingAs($seller);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('kpis.month_revenue', 100)
            ->assertJsonMissingPath('kpis.inventory_value')
            ->assertJsonMissingPath('charts.by_seller');
    }

    public function test_admin_dashboard_includes_inventory_value_and_by_seller_chart(): void
    {
        Product::factory()->create(['stock' => 3, 'production_cost' => 20]);
        Sale::factory()->create(['total' => 100]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('kpis.inventory_value', 60)
            ->assertJsonStructure(['charts' => ['by_seller' => ['labels', 'values']]]);
    }
}
