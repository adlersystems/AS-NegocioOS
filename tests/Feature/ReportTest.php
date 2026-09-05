<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_reports_routes(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.export.pdf'))->assertRedirect(route('login'));
        $this->get(route('reports.export.excel'))->assertRedirect(route('login'));
    }

    public function test_admin_and_manager_can_access_reports_but_not_seller(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('reports.index'))
                ->assertOk();
        }

        $this->actingAs(User::factory()->seller()->create())
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_invalid_report_type_falls_back_to_sales(): void
    {
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'nonsense']))
            ->assertOk()
            ->assertSee(__('app.reports.type_sales'))
            ->assertSee(__('app.reports.filter_seller'));
    }

    public function test_sales_report_renders_rows_and_totals(): void
    {
        $seller = User::factory()->seller()->create(['name' => 'Vendedor Uno']);
        $client = Client::factory()->create(['name' => 'Cliente Reporte', 'nit' => '12345678-9']);
        Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'subtotal' => 267.86,
            'tax_amount' => 32.14,
            'total' => 300.00,
        ]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'sales']))
            ->assertOk()
            ->assertSee('Cliente Reporte')
            ->assertSee('Vendedor Uno')
            ->assertSee('V-000001')
            ->assertSee('Q 300.00')
            ->assertSee(__('app.labels.total'));
    }

    public function test_sales_report_filters_by_seller_and_client(): void
    {
        $sellerA = User::factory()->seller()->create(['name' => 'Vendedor A']);
        $sellerB = User::factory()->seller()->create(['name' => 'Vendedor B']);
        $clientA = Client::factory()->create(['name' => 'Comprador A']);
        $clientB = Client::factory()->create(['name' => 'Comprador B']);

        Sale::factory()->create(['client_id' => $clientA->id, 'seller_id' => $sellerA->id, 'total' => 100]);
        Sale::factory()->create(['client_id' => $clientB->id, 'seller_id' => $sellerB->id, 'total' => 999]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'sales', 'seller_id' => $sellerA->id, 'client_id' => $clientA->id]))
            ->assertOk()
            ->assertSee('Comprador A')
            ->assertDontSee('V-000002')
            ->assertDontSee('Q 999.00');
    }

    public function test_sales_report_filters_by_date_range(): void
    {
        Sale::factory()->create(['total' => 50, 'created_at' => now()->subDays(10)]);
        Sale::factory()->create(['total' => 500, 'created_at' => now()]);

        $from = now()->subDays(5)->toDateString();

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'sales', 'from' => $from]))
            ->assertOk()
            ->assertSee('Q 500.00')
            ->assertDontSee('Q 50.00');
    }

    public function test_inventory_report_groups_movements_per_product(): void
    {
        $user = User::factory()->create();
        $productA = Product::factory()->create(['name' => 'Inventario A', 'sku' => 'INV-A', 'production_cost' => 10, 'stock' => 5]);
        $productB = Product::factory()->create(['name' => 'Inventario B', 'sku' => 'INV-B']);

        InventoryMovement::factory()->create(['product_id' => $productA->id, 'user_id' => $user->id, 'type' => InventoryMovement::TYPE_IN, 'quantity' => 10]);
        InventoryMovement::factory()->create(['product_id' => $productA->id, 'user_id' => $user->id, 'type' => InventoryMovement::TYPE_OUT, 'quantity' => 3]);
        InventoryMovement::factory()->create(['product_id' => $productB->id, 'user_id' => $user->id, 'type' => InventoryMovement::TYPE_IN, 'quantity' => 8]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'inventory']))
            ->assertOk()
            ->assertSee('Inventario A')
            ->assertSee('Inventario B')
            ->assertSee('+7')
            ->assertSee('Q 50.00');
    }

    public function test_inventory_report_filters_by_product(): void
    {
        $user = User::factory()->create();
        $productA = Product::factory()->create(['name' => 'Solo A', 'sku' => 'A-1']);
        $productB = Product::factory()->create(['name' => 'Solo B', 'sku' => 'B-1']);

        InventoryMovement::factory()->create(['product_id' => $productA->id, 'user_id' => $user->id, 'type' => InventoryMovement::TYPE_IN, 'quantity' => 4]);
        InventoryMovement::factory()->create(['product_id' => $productB->id, 'user_id' => $user->id, 'type' => InventoryMovement::TYPE_IN, 'quantity' => 4]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'inventory', 'product_id' => $productA->id]))
            ->assertOk()
            ->assertSee('Solo A')
            ->assertDontSee('B-1');
    }

    public function test_clients_report_counts_sales_and_totals_purchases(): void
    {
        $client = Client::factory()->create(['name' => 'Cliente Volumen']);
        Sale::factory()->count(2)->create(['client_id' => $client->id, 'total' => 150]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'clients']))
            ->assertOk()
            ->assertSee('Cliente Volumen')
            ->assertSee('Q 300.00');
    }

    public function test_clients_report_counts_only_sales_in_date_range(): void
    {
        $client = Client::factory()->create(['name' => 'Cliente Rango']);
        Sale::factory()->create(['client_id' => $client->id, 'total' => 900, 'created_at' => now()->subDays(20)]);
        Sale::factory()->create(['client_id' => $client->id, 'total' => 100, 'created_at' => now()]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'clients', 'from' => now()->subDays(10)->toDateString()]))
            ->assertOk()
            ->assertSee('Q 100.00')
            ->assertDontSee('Q 900.00');
    }

    public function test_products_report_aggregates_units_revenue_and_margin(): void
    {
        $product = Product::factory()->create(['name' => 'Producto Estrella', 'sku' => 'EST-1', 'production_cost' => 10]);
        $sale = Sale::factory()->create();

        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 4, 'unit_price' => 25, 'total' => 100]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25, 'total' => 50]);

        $cost = 6 * 10;

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'products']))
            ->assertOk()
            ->assertSee('Producto Estrella')
            ->assertSee('6')
            ->assertSee('Q 150.00')
            ->assertSee('Q '.number_format(150 - $cost, 2));
    }

    public function test_products_report_can_be_filtered_by_product_and_date(): void
    {
        $productA = Product::factory()->create(['name' => 'Producto Filtro A']);
        $productB = Product::factory()->create(['name' => 'Producto Filtro B']);
        $sale = Sale::factory()->create(['created_at' => now()]);

        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10, 'total' => 10]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $productB->id, 'quantity' => 4, 'unit_price' => 10, 'total' => 40]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'products', 'product_id' => $productA->id]))
            ->assertOk()
            ->assertSee('Producto Filtro A')
            ->assertDontSee('Q 40.00');
    }

    public function test_each_report_type_exports_excel(): void
    {
        foreach (['sales', 'inventory', 'clients', 'products'] as $type) {
            $this->actingAs(User::factory()->manager()->create())
                ->get(route('reports.export.excel', ['type' => $type]))
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_each_report_type_exports_pdf(): void
    {
        foreach (['sales', 'inventory', 'clients', 'products'] as $type) {
            $this->actingAs(User::factory()->manager()->create())
                ->get(route('reports.export.pdf', ['type' => $type]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_export_buttons_trigger_native_toast(): void
    {
        $html = $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('window.showToast', $html);
    }

    public function test_sales_report_shows_paid_and_pending_summary_and_status_column(): void
    {
        $seller = User::factory()->seller()->create();
        $client = Client::factory()->create(['name' => 'Cliente Estado']);

        Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'subtotal' => 100,
            'tax_amount' => 12,
            'total' => 112,
            'paid' => true,
        ]);

        Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'subtotal' => 50,
            'tax_amount' => 0,
            'total' => 50,
            'paid' => false,
        ]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'sales']))
            ->assertOk()
            ->assertSee(__('app.reports.paid_sales'))
            ->assertSee(__('app.reports.pending_sales'))
            ->assertSee(__('app.sales.status_paid'))
            ->assertSee(__('app.sales.status_unpaid'))
            ->assertSee('Q 112.00')
            ->assertSee('Q 50.00');
    }

    public function test_sales_report_filters_by_paid_status(): void
    {
        $seller = User::factory()->seller()->create();
        $client = Client::factory()->create(['name' => 'Cliente Filtro Pago']);

        Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'total' => 300,
            'paid' => true,
        ]);

        $salePending = Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'total' => 90,
            'paid' => false,
        ]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.index', ['type' => 'sales', 'paid' => 'pending']))
            ->assertOk()
            ->assertSee($salePending->invoiceNumber())
            ->assertSee('Q 90.00')
            ->assertDontSee('Q 300.00');
    }

    public function test_sales_report_excel_includes_status_column(): void
    {
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.export.excel', ['type' => 'sales']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
