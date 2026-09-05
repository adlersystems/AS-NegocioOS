<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_dashboard_returns_summary_metrics(): void
    {
        $client = Client::factory()->create();
        Sale::factory()->create(['client_id' => $client->id, 'total' => 500, 'paid' => false]);
        Product::factory()->create(['stock' => 2, 'min_stock' => 5, 'production_cost' => 10]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'counts' => ['clients', 'products', 'sales', 'receivables'],
                'kpis' => ['today_revenue', 'month_revenue', 'arpu', 'inventory_value'],
                'alerts' => ['low_stock', 'out_of_stock', 'expiring_soon'],
                'recent_sales',
                'charts' => ['sales_monthly', 'revenue_trend', 'by_seller', 'top_products'],
                'currency' => ['code', 'symbol'],
            ])
            ->assertJsonPath('counts.clients', 1)
            ->assertJsonPath('counts.products', 1)
            ->assertJsonPath('counts.sales', 1)
            ->assertJsonPath('counts.receivables', 500)
            ->assertJsonPath('alerts.low_stock', 1)
            ->assertJsonPath('currency.code', 'GTQ');
    }

    public function test_clients_index_supports_search_and_pagination_meta(): void
    {
        Client::factory()->create(['name' => 'Cliente API Único']);
        Client::factory()->count(3)->create();

        $this->getJson('/api/clients')->assertOk()->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page', 'last_page']]);

        $response = $this->getJson('/api/clients?search=API Único');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Cliente API Único');
    }

    public function test_clients_show_returns_pending_balance_as_unpaid_sales(): void
    {
        $client = Client::factory()->create();
        Sale::factory()->create(['client_id' => $client->id, 'total' => 250, 'paid' => false]);
        Sale::factory()->create(['client_id' => $client->id, 'total' => 100, 'paid' => true]);

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.name', $client->name)
            ->assertJsonPath('data.pending_balance', 250)
            ->assertJsonCount(2, 'data.sales');
    }

    public function test_products_index_and_show(): void
    {
        $product = Product::factory()->create([
            'name' => 'Galleta Integral',
            'sku' => 'GLT-100',
            'stock' => 5,
            'min_stock' => 3,
            'sale_price' => 10,
            'production_cost' => 6,
        ]);

        $this->getJson('/api/products?search=GLT-100')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.sku', 'GLT-100')
            ->assertJsonPath('data.0.margin', 4);

        InventoryMovement::factory()->create(['product_id' => $product->id, 'type' => InventoryMovement::TYPE_IN, 'quantity' => 7]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Galleta Integral')
            ->assertJsonCount(1, 'data.recent_movements');
    }

    public function test_sales_index_filters_and_show_with_items(): void
    {
        $client = Client::factory()->create(['name' => 'Filtro Cliente']);
        $seller = User::factory()->seller()->create();

        $paidSale = Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'total' => 120,
            'paid' => true,
        ]);
        SaleItem::factory()->create(['sale_id' => $paidSale->id]);

        Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'total' => 80,
            'paid' => false,
        ]);

        $this->getJson("/api/sales?client_id={$client->id}&paid=paid")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', $paidSale->invoiceNumber())
            ->assertJsonPath('data.0.client', 'Filtro Cliente');

        $this->getJson("/api/sales/{$paidSale->id}")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', $paidSale->invoiceNumber())
            ->assertJsonCount(1, 'data.items');
    }

    public function test_inventory_index_filters_by_type(): void
    {
        $product = Product::factory()->create(['name' => 'Almacén X']);
        InventoryMovement::factory()->create(['product_id' => $product->id, 'type' => InventoryMovement::TYPE_IN]);
        InventoryMovement::factory()->create(['product_id' => $product->id, 'type' => InventoryMovement::TYPE_IN]);
        InventoryMovement::factory()->create(['product_id' => $product->id, 'type' => InventoryMovement::TYPE_OUT]);

        $this->getJson('/api/inventory?type=in')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/inventory?type=out')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_reports_sales_returns_paid_summary_and_filters(): void
    {
        $client = Client::factory()->create();
        Sale::factory()->create(['client_id' => $client->id, 'total' => 300, 'paid' => true]);
        Sale::factory()->create(['client_id' => $client->id, 'total' => 200, 'paid' => false]);

        $this->getJson('/api/reports?type=sales')
            ->assertOk()
            ->assertJsonPath('type', 'sales')
            ->assertJsonPath('count', 2)
            ->assertJsonPath('paid_count', 1)
            ->assertJsonPath('unpaid_count', 1)
            ->assertJsonPath('paid_total', 300);

        $this->getJson('/api/reports?type=sales&paid=pending')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.total', 200);
    }

    public function test_reports_supports_all_types_and_defaults_invalid_to_sales(): void
    {
        Client::factory()->create();
        Product::factory()->create();

        foreach (['inventory', 'clients', 'products'] as $type) {
            $this->getJson("/api/reports?type={$type}")->assertOk()->assertJsonPath('type', $type);
        }

        $this->getJson('/api/reports?type=bogus')
            ->assertOk()
            ->assertJsonPath('type', 'sales');
    }

    public function test_settings_returns_company_defaults_and_currency(): void
    {
        Setting::setMany([
            'company_name' => 'Nuk\'ay API',
            'currency' => 'USD',
            'iva_percentage' => '15',
        ]);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Nuk\'ay API')
            ->assertJsonPath('currency.code', 'USD')
            ->assertJsonPath('currency.symbol', '$')
            ->assertJsonPath('currency.iva_percentage', 15);
    }
}
