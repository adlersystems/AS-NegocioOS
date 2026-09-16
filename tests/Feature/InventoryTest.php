<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_inventory_routes(): void
    {
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
        $this->get(route('inventory.create'))->assertRedirect(route('login'));
        $this->post(route('inventory.store'))->assertRedirect(route('login'));
    }

    public function test_admin_and_manager_can_access_inventory_but_not_seller(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('inventory.index'))
                ->assertOk();
        }

        $this->actingAs(User::factory()->seller()->create())
            ->get(route('inventory.index'))
            ->assertForbidden();
    }

    public function test_index_renders_movements_with_product_type_and_reason(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Inventario Visible', 'sku' => 'INV-1']);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 12,
            'reason' => 'Reposición',
        ]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Inventario Visible')
            ->assertSee('Reposición')
            ->assertSee('+12');
    }

    public function test_index_filters_by_type(): void
    {
        $product = Product::factory()->create();
        InventoryMovement::factory()->create(['product_id' => $product->id, 'type' => InventoryMovement::TYPE_IN, 'quantity' => 5]);
        InventoryMovement::factory()->create(['product_id' => $product->id, 'type' => InventoryMovement::TYPE_OUT, 'quantity' => 3]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('inventory.index', ['type' => 'out']))
            ->assertOk()
            ->assertSee('-3')
            ->assertDontSee('+5');
    }

    public function test_index_search_filters_by_product_name(): void
    {
        $in = Product::factory()->create(['name' => 'Café Filtrado']);
        $other = Product::factory()->create(['name' => 'Azúcar']);

        InventoryMovement::factory()->create(['product_id' => $in->id]);
        InventoryMovement::factory()->create(['product_id' => $other->id]);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('inventory.index', ['search' => 'Café']))
            ->assertOk()
            ->assertSee('Café Filtrado')
            ->assertDontSee('Azúcar');
    }

    public function test_create_renders_the_form_with_products(): void
    {
        Product::factory()->create(['name' => 'Producto Formulario', 'sku' => 'FRM-1']);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('inventory.create'))
            ->assertOk()
            ->assertSee('Producto Formulario');
    }

    public function test_store_in_movement_increments_stock_and_logs(): void
    {
        $user = User::factory()->manager()->create();
        $product = Product::factory()->create(['name' => 'Café', 'stock' => 10]);

        $this->actingAs($user)
            ->post(route('inventory.store'), [
                'product_id' => $product->id,
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => 15,
                'reason' => 'Compra a proveedor',
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success');

        $this->assertSame(25, $product->fresh()->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 15,
            'reason' => 'Compra a proveedor',
        ]);
    }

    public function test_store_out_movement_decrements_stock_and_logs(): void
    {
        $user = User::factory()->manager()->create();
        $product = Product::factory()->create(['stock' => 20]);

        $this->actingAs($user)
            ->post(route('inventory.store'), [
                'product_id' => $product->id,
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => 6,
                'reason' => 'Merma',
            ]);

        $this->assertSame(14, $product->fresh()->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 6,
            'reason' => 'Merma',
        ]);
    }

    public function test_store_rejects_out_movement_beyond_available_stock(): void
    {
        $user = User::factory()->manager()->create();
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAs($user)
            ->post(route('inventory.store'), [
                'product_id' => $product->id,
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => 5,
                'reason' => 'Merma',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->manager()->create())
            ->post(route('inventory.store'), [])
            ->assertSessionHasErrors(['product_id', 'type', 'quantity', 'reason']);
    }

    public function test_store_redirects_to_a_page_rendering_the_success_toast(): void
    {
        $user = User::factory()->manager()->create();
        $product = Product::factory()->create(['stock' => 40]);

        $this->actingAs($user)->post(route('inventory.store'), [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 10,
            'reason' => 'Reposición',
        ]);

        $html = $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->getContent();

        $encoded = json_encode(
            __('app.flash.stock_updated'),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT,
        );

        $this->assertStringContainsString($encoded, $html);
        $this->assertStringContainsString('data-toast-initial', $html);
    }

    public function test_store_rolls_back_stock_change_when_inventory_write_fails(): void
    {
        $user = User::factory()->manager()->create();
        $product = Product::factory()->create(['stock' => 10]);

        InventoryMovement::creating(function () {
            throw new \RuntimeException('inventory write failed');
        });

        $this->actingAs($user)
            ->post(route('inventory.store'), [
                'product_id' => $product->id,
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => 15,
                'reason' => 'Compra a proveedor',
            ])
            ->assertServerError();

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_export_excel_downloads_the_movements_registry(): void
    {
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('inventory.export.excel'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_pdf_downloads_the_movements_registry(): void
    {
        $this->actingAs(User::factory()->manager()->create())
            ->get(route('inventory.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
