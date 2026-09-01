<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_product_routes(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('products.create'))->assertRedirect(route('login'));
        $this->get(route('products.show', Product::factory()->create()))->assertRedirect(route('login'));
    }

    public function test_all_roles_can_access_the_products_index(): void
    {
        foreach ([
            User::ROLE_ADMIN,
            User::ROLE_SELLER,
            User::ROLE_MANAGER,
        ] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('products.index'))
                ->assertOk();
        }
    }

    public function test_index_renders_products_with_stock_badges(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Producto Visible', 'stock' => 50]);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Producto Visible')
            ->assertSee('En stock');
    }

    public function test_index_search_filters_by_name_or_sku(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Café Especial', 'sku' => 'CAF-001']);
        $other = Product::factory()->create(['name' => 'Azúcar', 'sku' => 'AZU-001']);

        $this->actingAs($user)
            ->get(route('products.index', ['search' => 'Café']))
            ->assertOk()
            ->assertSee('Café Especial')
            ->assertDontSee($other->name);

        $this->actingAs($user)
            ->get(route('products.index', ['search' => 'AZU']))
            ->assertOk()
            ->assertSee('Azúcar');
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        $active = Product::factory()->create(['name' => 'Activo Filtrado']);
        $inactive = Product::factory()->create(['name' => 'Inactivo Filtrado', 'is_active' => false]);

        $this->actingAs($user)
            ->get(route('products.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Activo Filtrado')
            ->assertDontSee('Inactivo Filtrado');

        $this->actingAs($user)
            ->get(route('products.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Inactivo Filtrado')
            ->assertDontSee($active->name);
    }

    public function test_index_filters_by_low_stock_excluding_out_of_stock(): void
    {
        $user = User::factory()->create();
        $low = Product::factory()->lowStock()->create(['name' => 'Con stock bajo', 'stock' => 3, 'min_stock' => 5]);
        $out = Product::factory()->outOfStock()->create(['name' => 'Agotado Filtrado']);

        $this->actingAs($user)
            ->get(route('products.index', ['stock' => 'low']))
            ->assertOk()
            ->assertSee($low->name)
            ->assertDontSee($out->name);
    }

    public function test_index_filters_by_out_of_stock(): void
    {
        $user = User::factory()->create();
        $out = Product::factory()->outOfStock()->create(['name' => 'Solo Agotado']);
        $fine = Product::factory()->create(['name' => 'Con Existencia', 'stock' => 20]);

        $this->actingAs($user)
            ->get(route('products.index', ['stock' => 'out']))
            ->assertOk()
            ->assertSee('Solo Agotado')
            ->assertDontSee($fine->name);
    }

    public function test_index_filters_by_expiring_soon(): void
    {
        $user = User::factory()->create();
        $expiring = Product::factory()->create([
            'name' => 'Por Vencer Pronto',
            'expiration_date' => now()->addDays(10)->toDateString(),
        ]);
        $fine = Product::factory()->create(['name' => 'Sin Fecha Próxima', 'expiration_date' => null]);

        $this->actingAs($user)
            ->get(route('products.index', ['stock' => 'expiring']))
            ->assertOk()
            ->assertSee('Por Vencer Pronto')
            ->assertDontSee($fine->name);
    }

    public function test_store_creates_a_product(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Pan Integral',
            'sku' => 'PAN-001',
            'description' => 'Pan con harina integral',
            'stock' => 10,
            'min_stock' => 3,
            'expiration_date' => now()->addDays(60)->toDateString(),
            'production_cost' => 4.5,
            'sale_price' => 9,
            'is_active' => '1',
        ]);

        $product = Product::query()->where('sku', 'PAN-001')->firstOrFail();

        $response->assertRedirect(route('products.show', $product));
        $this->assertSame(10, $product->stock);
        $this->assertTrue($product->is_active);

        $response->assertSessionHas('success');
    }

    public function test_store_defaults_is_active_to_false_when_unchecked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Producto Inactivo',
            'production_cost' => 1,
            'sale_price' => 2,
            'stock' => 5,
            'min_stock' => 1,
        ]);

        $product = Product::query()->where('name', 'Producto Inactivo')->firstOrFail();

        $this->assertFalse($product->is_active);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('products.store'), ['name' => '', 'sale_price' => '', 'stock' => ''])
            ->assertSessionHasErrors(['name', 'sale_price', 'stock']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_negative_stock(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('products.store'), [
                'name' => 'Stock Erróneo',
                'stock' => -5,
                'min_stock' => 1,
                'production_cost' => 1,
                'sale_price' => 2,
            ])
            ->assertSessionHasErrors('stock');
    }

    public function test_store_rejects_duplicate_sku(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['sku' => 'DUP-001']);

        $this->actingAs($user)
            ->post(route('products.store'), [
                'name' => 'Producto Duplicado',
                'sku' => 'DUP-001',
                'stock' => 5,
                'min_stock' => 1,
                'production_cost' => 1,
                'sale_price' => 2,
            ])
            ->assertSessionHasErrors('sku');
    }

    public function test_update_edits_the_product_and_ignores_its_own_sku(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Original', 'sku' => 'SKU-X']);

        $this->actingAs($user)
            ->put(route('products.update', $product), [
                'name' => 'Editado',
                'sku' => 'SKU-X',
                'stock' => 25,
                'min_stock' => 5,
                'production_cost' => 3,
                'sale_price' => 7,
                'is_active' => '1',
            ])
            ->assertRedirect(route('products.show', $product));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Editado',
            'stock' => 25,
        ]);
    }

    public function test_update_redirects_to_a_page_rendering_the_success_toast(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Antes', 'sku' => 'SKU-PROBE']);

        $this->actingAs($user)
            ->put(route('products.update', $product), [
                'name' => 'Después',
                'sku' => 'SKU-PROBE',
                'stock' => 10,
                'min_stock' => 2,
                'production_cost' => 4,
                'sale_price' => 8,
                'is_active' => '1',
            ])
            ->assertRedirect(route('products.show', $product));

        $html = $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'data-flash-success="'.__('app.flash.updated', ['entity' => __('app.products.singular')]).'"',
            $html,
        );
    }

    public function test_destroy_deletes_the_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($product);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_destroy_soft_deletes_a_product_with_sale_history(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Producto Con Historial']);

        SaleItem::factory()->create([
            'product_id' => $product->id,
            'sale_id' => Sale::factory(),
        ]);

        $this->actingAs($user)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('sale_items', ['product_id' => $product->id]);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Producto Con Historial');
    }

    public function test_archived_products_still_appear_when_editing_a_sale_that_uses_them(): void
    {
        $user = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['name' => 'Archivado En Venta']);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $product->delete();

        $html = $this->actingAs($user)
            ->get(route('sales.edit', $sale))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Archivado En Venta', $html);
    }

    public function test_show_displays_movements_and_metrics(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Producto Detalle',
            'stock' => 30,
            'production_cost' => 5,
            'sale_price' => 10,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 20,
            'reason' => 'Compra inicial',
        ]);

        SaleItem::factory()->create([
            'product_id' => $product->id,
            'quantity' => 6,
            'unit_price' => 10,
            'total' => 60,
            'sale_id' => Sale::factory(),
        ]);

        $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Producto Detalle')
            ->assertSee('Compra inicial')
            ->assertSee('Q 150.00')
            ->assertSee('6');
    }

    public function test_creating_a_product_via_http_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Auditado',
            'stock' => 5,
            'min_stock' => 1,
            'production_cost' => 1,
            'sale_price' => 2,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'created',
            'auditable_type' => Product::class,
        ]);

        $this->assertSame(1, AuditLog::query()->where('user_id', $user->id)->count());
    }
}
