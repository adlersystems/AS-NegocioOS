<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleHistoricalSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->seller()->create();
        $this->admin = User::factory()->admin()->create();
    }

    private function storeSale(array $items, array $extra = []): Sale
    {
        $this->actingAs($this->seller)->post(route('sales.store'), [
            'seller_id' => $this->seller->id,
            'items' => $items,
            ...$extra,
        ])->assertRedirect();

        return Sale::query()->firstOrFail();
    }

    public function test_new_sale_stores_tax_rate_and_cost_snapshots(): void
    {
        $product = Product::factory()->create([
            'name' => 'Café Gourmet',
            'sale_price' => 50,
            'production_cost' => 35.5,
            'stock' => 10,
        ]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $this->assertSame(12.0, (float) $sale->fresh()->tax_rate);

        $item = $sale->items()->first();
        $this->assertSame(50.0, (float) $item->unit_price);
        $this->assertSame(35.5, (float) $item->cost);
        $this->assertSame(150.0, (float) $item->total);
    }

    public function test_product_price_change_does_not_affect_existing_sale(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $product->update(['sale_price' => 15]);

        $sale->refresh();

        $this->assertSame(20.0, (float) $sale->subtotal);
        $this->assertSame(2.40, (float) $sale->tax_amount);
        $this->assertSame(22.40, (float) $sale->total);

        $item = $sale->items()->first();
        $this->assertSame(10.0, (float) $item->unit_price);
        $this->assertSame(3.0, (float) $item->cost);
    }

    public function test_product_cost_change_does_not_affect_historical_profitability(): void
    {
        $product = Product::factory()->create(['name' => 'Margen Histórico', 'sale_price' => 25, 'production_cost' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 4],
        ]);

        $product->update(['production_cost' => 14]);

        $report = app(ReportService::class)->data('products');
        $row = collect($report)->firstWhere('id', $product->id);

        $this->assertNotNull($row);
        $this->assertSame(4, $row['quantity']);
        $this->assertSame(100.0, $row['revenue']);
        $this->assertSame(40.0, $row['cost']);
        $this->assertSame(60.0, $row['margin']);
    }

    public function test_iva_setting_change_does_not_affect_existing_sale(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        Setting::set('iva_percentage', '15');

        $sale->refresh();

        $this->assertSame(12.0, (float) $sale->tax_rate);
        $this->assertSame(20.0, (float) $sale->subtotal);
        $this->assertSame(2.40, (float) $sale->tax_amount);
        $this->assertSame(22.40, (float) $sale->total);
    }

    public function test_notes_only_edit_preserves_all_monetary_values_and_does_not_rebuild_items(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ], ['client_id' => Client::factory()->create()->id]);

        $itemId = $sale->items()->first()->id;
        $movementCount = InventoryMovement::count();

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'notes' => 'Solo cambié la nota',
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertSame(20.0, (float) $sale->subtotal);
        $this->assertSame(2.40, (float) $sale->tax_amount);
        $this->assertSame(22.40, (float) $sale->total);
        $this->assertSame(12.0, (float) $sale->tax_rate);
        $this->assertSame($itemId, $sale->items()->first()->id);
        $this->assertSame($movementCount, InventoryMovement::count());
    }

    public function test_paid_status_edit_preserves_all_monetary_values(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $this->actingAs($this->admin)->patch(route('sales.paid.toggle', $sale))->assertRedirect();

        $sale->refresh();

        $this->assertTrue($sale->isPaid());
        $this->assertSame(20.0, (float) $sale->subtotal);
        $this->assertSame(2.40, (float) $sale->tax_amount);
        $this->assertSame(22.40, (float) $sale->total);
        $this->assertSame(12.0, (float) $sale->tax_rate);
    }

    public function test_quantity_edit_preserves_historical_price_and_cost(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $product->update(['sale_price' => 15, 'production_cost' => 9]);

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertSame(50.0, (float) $sale->subtotal);
        $this->assertSame(6.0, (float) $sale->tax_amount);
        $this->assertSame(56.0, (float) $sale->total);
        $this->assertSame(12.0, (float) $sale->tax_rate);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10,
            'cost' => 3,
            'total' => 50,
        ]);
    }

    public function test_new_line_added_during_edit_uses_current_price_cost_and_stored_tax_rate(): void
    {
        $existing = Product::factory()->create(['sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);
        $new = Product::factory()->create(['name' => 'Nuevo En Edición', 'sale_price' => 20, 'production_cost' => 7, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $existing->id, 'quantity' => 2],
        ]);

        $existing->update(['sale_price' => 15, 'production_cost' => 9]);

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $existing->id, 'quantity' => 2],
                ['product_id' => $new->id, 'quantity' => 1],
            ],
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $existing->id,
            'quantity' => 2,
            'unit_price' => 10,
            'cost' => 3,
            'total' => 20,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $new->id,
            'quantity' => 1,
            'unit_price' => 20,
            'cost' => 7,
            'total' => 20,
        ]);

        $this->assertSame(40.0, (float) $sale->subtotal);
        $this->assertSame(4.80, (float) $sale->tax_amount);
        $this->assertSame(44.80, (float) $sale->total);
        $this->assertSame(12.0, (float) $sale->tax_rate);
    }

    public function test_removed_line_keeps_historical_values_of_remaining_lines(): void
    {
        $a = Product::factory()->create(['name' => 'A', 'sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);
        $b = Product::factory()->create(['name' => 'B', 'sale_price' => 20, 'production_cost' => 7, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $a->id, 'quantity' => 2],
            ['product_id' => $b->id, 'quantity' => 1],
        ]);

        $a->update(['sale_price' => 15, 'production_cost' => 9]);

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $a->id, 'quantity' => 2],
            ],
        ])->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $a->id,
            'quantity' => 2,
            'unit_price' => 10,
            'cost' => 3,
            'total' => 20,
        ]);

        $this->assertDatabaseMissing('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $b->id,
        ]);

        $sale->refresh();

        $this->assertSame(20.0, (float) $sale->subtotal);
        $this->assertSame(2.40, (float) $sale->tax_amount);
        $this->assertSame(22.40, (float) $sale->total);

        $this->assertSame(10, $b->fresh()->stock);
    }

    public function test_invoice_pdf_label_uses_stored_tax_rate(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        Setting::set('iva_percentage', '15');

        $sale->load(['client', 'seller', 'items.product']);
        $this->actingAs($this->admin);

        $html = view('sales.invoice-pdf', [
            'sale' => $sale,
            'iva' => $sale->tax_rate !== null ? (float) $sale->tax_rate : (int) Setting::get('iva_percentage', 12),
            'company' => ['name' => 'AS-NegocioOS', 'nit' => null, 'address' => null, 'phone' => null, 'email' => null],
        ])->render();

        $this->assertStringContainsString('(12%)', $html);
        $this->assertStringNotContainsString('(15%)', $html);
    }

    public function test_api_returns_historical_unit_price_and_additive_snapshot_fields(): void
    {
        $product = Product::factory()->create(['sale_price' => 25.5, 'production_cost' => 6, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson(route('api.sales.show', $sale))
            ->assertOk()
            ->assertJsonPath('data.tax_rate', 12)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.price', 25.5)
            ->assertJsonPath('data.items.0.cost', 6)
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_soft_deleted_product_does_not_corrupt_historical_sale(): void
    {
        $product = Product::factory()->create(['name' => 'Producto Archivado', 'sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $product->delete();

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'notes' => 'El producto fue archivado',
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertSame(20.0, (float) $sale->subtotal);
        $this->assertSame(2.40, (float) $sale->tax_amount);
        $this->assertSame(22.40, (float) $sale->total);

        $item = $sale->items()->first();
        $this->assertSame(10.0, (float) $item->unit_price);
        $this->assertSame(3.0, (float) $item->cost);
    }

    public function test_legacy_null_tax_rate_preserved_on_non_monetary_edit(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = Sale::query()->forceCreate([
            'client_id' => null,
            'seller_id' => $this->seller->id,
            'subtotal' => 20,
            'tax_amount' => 2.4,
            'total' => 22.4,
            'tax_rate' => null,
            'notes' => 'Legacy',
        ]);
        SaleItem::query()->forceCreate([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
            'cost' => 3,
            'total' => 20,
        ]);

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'notes' => 'Nota nueva',
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertNull($sale->tax_rate);
        $this->assertSame(20.0, (float) $sale->subtotal);
        $this->assertSame(2.40, (float) $sale->tax_amount);
        $this->assertSame(22.40, (float) $sale->total);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
            'cost' => 3,
        ]);
    }

    public function test_legacy_null_tax_rate_captures_current_iva_when_lines_change(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = Sale::query()->forceCreate([
            'client_id' => null,
            'seller_id' => $this->seller->id,
            'subtotal' => 20,
            'tax_amount' => 2.4,
            'total' => 22.4,
            'tax_rate' => null,
            'notes' => 'Legacy',
        ]);
        SaleItem::query()->forceCreate([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
            'cost' => 3,
            'total' => 20,
        ]);

        Setting::set('iva_percentage', '15');

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertSame(15.0, (float) $sale->tax_rate);
        $this->assertSame(30.0, (float) $sale->subtotal);
        $this->assertSame(4.50, (float) $sale->tax_amount);
        $this->assertSame(34.50, (float) $sale->total);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10,
            'cost' => 3,
        ]);
    }

    public function test_edit_form_carries_historical_unit_price_and_stored_rate(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $product->update(['sale_price' => 15]);

        $html = $this->actingAs($this->admin)
            ->get(route('sales.edit', $sale))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('\u0022unit_price\u0022:\u002210.00\u0022', $html);
        $this->assertStringNotContainsString('\u0022unit_price\u0022:\u002215.00\u0022', $html);
        $this->assertStringContainsString('\u0022iva\u0022:12', $html);
    }

    public function test_backfill_reconstructs_legacy_rates_and_costs(): void
    {
        $product = Product::factory()->create(['name' => 'Legacy', 'sale_price' => 10, 'production_cost' => 8.25, 'stock' => 10]);

        $withTax = Sale::query()->forceCreate([
            'seller_id' => $this->seller->id,
            'subtotal' => 100,
            'tax_amount' => 12,
            'total' => 112,
            'tax_rate' => null,
        ]);
        $zeroTax = Sale::query()->forceCreate([
            'seller_id' => $this->seller->id,
            'subtotal' => 50,
            'tax_amount' => 0,
            'total' => 50,
            'tax_rate' => null,
        ]);
        $zeroSubtotal = Sale::query()->forceCreate([
            'seller_id' => $this->seller->id,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'tax_rate' => null,
        ]);

        SaleItem::query()->forceCreate([
            'sale_id' => $withTax->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10,
            'cost' => null,
            'total' => 100,
        ]);

        $migration = include database_path(
            'migrations/2026_09_16_120000_add_historical_snapshots_to_sales_and_sale_items.php'
        );

        $this->assertInstanceOf(Migration::class, $migration);

        $migration->backfillTaxRates();
        $migration->backfillItemCosts();

        $this->assertSame(12.0, (float) $withTax->fresh()->tax_rate);
        $this->assertNull($zeroTax->fresh()->tax_rate);
        $this->assertNull($zeroSubtotal->fresh()->tax_rate);

        $this->assertSame(8.25, (float) $withTax->items()->first()->cost);
    }

    public function test_store_snapshots_client_name_and_nit(): void
    {
        $client = Client::factory()->create(['name' => 'Cliente Instantáneo', 'nit' => '777-7']);
        $product = Product::factory()->create(['name' => 'Producto', 'sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ], ['client_id' => $client->id]);

        $this->assertSame('Cliente Instantáneo', $sale->client_name);
        $this->assertSame('777-7', $sale->client_nit);
        $this->assertSame('Cliente Instantáneo', $sale->buyerName());
        $this->assertSame('777-7', $sale->buyerNit());
    }

    public function test_walk_in_sale_stores_null_client_snapshot(): void
    {
        $product = Product::factory()->create(['name' => 'Mostrador', 'sale_price' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->assertNull($sale->client_name);
        $this->assertNull($sale->client_nit);
        $this->assertNull($sale->buyerName());
    }

    public function test_same_client_notes_only_edit_preserves_client_snapshot(): void
    {
        $client = Client::factory()->create(['name' => 'Nombre Viejo', 'nit' => '111-1']);
        $product = Product::factory()->create(['name' => 'Producto', 'sale_price' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 1],
        ], ['client_id' => $client->id]);

        $client->update(['name' => 'Nombre Nuevo', 'nit' => '222-2']);

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'client_id' => $client->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'notes' => 'Nota nueva',
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertSame('Nombre Viejo', $sale->client_name);
        $this->assertSame('111-1', $sale->client_nit);
    }

    public function test_changing_client_id_refreshes_the_client_snapshot(): void
    {
        $old = Client::factory()->create(['name' => 'Cliente Antes', 'nit' => '333-3']);
        $new = Client::factory()->create(['name' => 'Cliente Después', 'nit' => '444-4']);
        $product = Product::factory()->create(['name' => 'Producto', 'sale_price' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 1],
        ], ['client_id' => $old->id]);

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'client_id' => $new->id,
            'seller_id' => $this->seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertSame('Cliente Después', $sale->client_name);
        $this->assertSame('444-4', $sale->client_nit);
    }

    public function test_renamed_client_still_renders_the_recorded_snapshot_name(): void
    {
        $client = Client::factory()->create(['name' => 'Nombre Original', 'nit' => '555-5']);
        $product = Product::factory()->create(['name' => 'Producto', 'sale_price' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 1],
        ], ['client_id' => $client->id]);

        $client->update(['name' => 'Renombrado Total', 'nit' => '666-6']);

        $sale->load('client');

        $this->assertSame('Nombre Original', $sale->buyerName());
        $this->assertSame('555-5', $sale->buyerNit());
    }

    public function test_search_finds_renamed_clients_by_recorded_snapshot(): void
    {
        $client = Client::factory()->create(['name' => 'Comprador Antiguo', 'nit' => '888-8']);
        $product = Product::factory()->create(['name' => 'Producto', 'sale_price' => 10, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 1],
        ], ['client_id' => $client->id]);

        $client->update(['name' => 'Comprador Renombrado']);

        $this->actingAs($this->admin)
            ->get(route('sales.index', ['search' => 'Comprador Antiguo']))
            ->assertOk()
            ->assertSee('Comprador Antiguo');
    }

    public function test_client_identity_backfill_reconstructs_legacy_rows_and_keeps_walk_ins_null(): void
    {
        $client = Client::factory()->create(['name' => 'Legacy Backfill', 'nit' => '999-9']);
        $withClient = Sale::query()->forceCreate([
            'client_id' => $client->id,
            'seller_id' => $this->seller->id,
            'subtotal' => 100,
            'tax_amount' => 12,
            'total' => 112,
            'tax_rate' => 12,
        ]);
        $walkIn = Sale::query()->forceCreate([
            'client_id' => null,
            'seller_id' => $this->seller->id,
            'subtotal' => 50,
            'tax_amount' => 0,
            'total' => 50,
            'tax_rate' => null,
        ]);

        $migration = include database_path(
            'migrations/2026_09_17_100000_add_client_identity_snapshots_to_sales_table.php'
        );

        $this->assertInstanceOf(Migration::class, $migration);

        $migration->backfillClientIdentity();

        $this->assertSame('Legacy Backfill', $withClient->fresh()->client_name);
        $this->assertSame('999-9', $withClient->fresh()->client_nit);
        $this->assertNull($walkIn->fresh()->client_name);
        $this->assertNull($walkIn->fresh()->client_nit);
    }

    public function test_clients_nit_has_a_database_unique_index(): void
    {
        $columns = collect(Schema::getIndexes('clients'))
            ->filter(fn (array $index) => $index['unique'])
            ->flatMap(fn (array $index) => $index['columns'])
            ->all();

        $this->assertContains('nit', $columns);
    }

    public function test_product_cost_change_does_not_affect_existing_sale_at_all(): void
    {
        $product = Product::factory()->create(['sale_price' => 10, 'production_cost' => 3, 'stock' => 10]);

        $sale = $this->storeSale([
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $product->update(['production_cost' => 14]);

        $sale->refresh();

        $this->assertSame(20.0, (float) $sale->subtotal);
        $this->assertSame(2.40, (float) $sale->tax_amount);
        $this->assertSame(22.40, (float) $sale->total);
        $this->assertSame(3.0, (float) $sale->items()->first()->cost);
    }
}
