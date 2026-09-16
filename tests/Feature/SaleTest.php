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

class SaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_sale_routes(): void
    {
        $sale = Sale::factory()->create();

        $this->get(route('sales.index'))->assertRedirect(route('login'));
        $this->get(route('sales.create'))->assertRedirect(route('login'));
        $this->get(route('sales.show', $sale))->assertRedirect(route('login'));
        $this->get(route('sales.edit', $sale))->assertRedirect(route('login'));
    }

    public function test_sales_index_is_accessible_to_managers(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_SELLER, User::ROLE_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('sales.index'))
                ->assertOk();
        }
    }

    public function test_managers_cannot_create_edit_or_delete_sales(): void
    {
        $sale = Sale::factory()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('sales.create'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('sales.edit', $sale))
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('sales.destroy', $sale))
            ->assertForbidden();
    }

    public function test_sellers_can_create_but_not_edit_or_delete_sales(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 5]);
        $sale = Sale::factory()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller)->get(route('sales.create'))->assertOk();

        $this->actingAs($seller)
            ->post(route('sales.store'), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])->assertRedirect();

        $this->actingAs($seller)->get(route('sales.edit', $sale))->assertForbidden();
        $this->actingAs($seller)->put(route('sales.update', $sale), [
            'seller_id' => $seller->id,
            'items' => [],
        ])->assertForbidden();
        $this->actingAs($seller)->delete(route('sales.destroy', $sale))->assertForbidden();
    }

    public function test_index_renders_sales_with_invoice_client_seller_and_total(): void
    {
        $seller = User::factory()->seller()->create(['name' => 'Vendedor Principal']);
        $client = Client::factory()->create(['name' => 'Cliente Factura']);
        $sale = Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'subtotal' => 100,
            'tax_amount' => 12,
            'total' => 112,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee('V-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('Cliente Factura')
            ->assertSee('Vendedor Principal')
            ->assertSee('Q 112.00');
    }

    public function test_index_search_filters_by_client_name_or_nit(): void
    {
        $target = Client::factory()->create(['name' => 'Cliente Objetivo', 'nit' => '8888-8']);
        $other = Client::factory()->create(['name' => 'Otro Cliente']);

        Sale::factory()->create(['client_id' => $target->id]);
        Sale::factory()->create(['client_id' => $other->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('sales.index', ['search' => 'Objetivo']))
            ->assertOk()
            ->assertSee('Cliente Objetivo')
            ->assertDontSee('Otro Cliente');

        $this->actingAs(User::factory()->create())
            ->get(route('sales.index', ['search' => '8888-8']))
            ->assertOk()
            ->assertSee('Cliente Objetivo')
            ->assertDontSee('Otro Cliente');
    }

    public function test_index_search_by_invoice_code_resolves_the_sale(): void
    {
        $client = Client::factory()->create(['name' => 'Cliente Por Código']);
        $target = Sale::factory()->create(['client_id' => $client->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('sales.index', ['search' => $target->invoiceNumber()]))
            ->assertOk()
            ->assertSee('Cliente Por Código');
    }

    public function test_index_filters_by_seller_with_number_filters(): void
    {
        $sellerA = User::factory()->seller()->create();
        $sellerB = User::factory()->seller()->create();

        $saleA = Sale::factory()->create(['seller_id' => $sellerA->id]);
        $saleB = Sale::factory()->create(['seller_id' => $sellerB->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('sales.index', ['seller_id' => $sellerA->id]))
            ->assertOk()
            ->assertSee($saleA->invoiceNumber())
            ->assertDontSee($saleB->invoiceNumber());
    }

    public function test_index_filters_by_date_range(): void
    {
        $client = Client::factory()->create(['name' => 'Filtro Fechas']);

        $saleIn = Sale::factory()->create(['client_id' => $client->id, 'created_at' => now()]);
        $saleOut = Sale::factory()->create(['client_id' => $client->id, 'created_at' => now()->subMonths(3)]);

        $from = now()->startOfWeek()->toDateString();
        $to = now()->toDateString();

        $this->actingAs(User::factory()->create())
            ->get(route('sales.index', ['from' => $from, 'to' => $to]))
            ->assertOk()
            ->assertSee($saleIn->invoiceNumber())
            ->assertDontSee($saleOut->invoiceNumber());
    }

    public function test_store_creates_a_sale_decrements_stock_and_logs_movements(): void
    {
        $seller = User::factory()->seller()->create();
        $client = Client::factory()->create(['name' => 'Comprador Mayorista']);
        $product = Product::factory()->create(['name' => 'Café Gourmet', 'sale_price' => 50, 'stock' => 10]);

        $response = $this->actingAs($seller)->post(route('sales.store'), [
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'notes' => 'Venta de prueba',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();

        $response->assertRedirect(route('sales.show', $sale));
        $response->assertSessionHas('success');

        $this->assertSame(150.00, (float) $sale->subtotal);
        $this->assertSame(18.00, (float) $sale->tax_amount);
        $this->assertSame(168.00, (float) $sale->total);
        $this->assertSame(7, $product->fresh()->stock);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 50,
            'total' => 150,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $seller->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 3,
            'reference' => $sale->invoiceNumber(),
        ]);
    }

    public function test_store_defaults_to_walk_in_client_when_omitted(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 5]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $this->assertNull(Sale::query()->firstOrFail()->client_id);
    }

    public function test_store_redirects_to_a_page_rendering_the_success_toast(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 20]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $html = $this->actingAs($seller)
            ->get(route('sales.show', Sale::query()->firstOrFail()))
            ->assertOk()
            ->getContent();

        $encoded = json_encode(
            __('app.flash.sale_registered'),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT,
        );

        $this->assertStringContainsString($encoded, $html);
        $this->assertStringContainsString('data-toast-initial', $html);
    }

    public function test_store_requires_at_least_one_item(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)
            ->post(route('sales.store'), ['seller_id' => $seller->id, 'items' => []])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_store_rejects_duplicate_products(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 20]);

        $this->actingAs($seller)
            ->post(route('sales.store'), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(20, $product->fresh()->stock);
    }

    public function test_store_rejects_inactive_products(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 20, 'is_active' => false]);

        $this->actingAs($seller)
            ->post(route('sales.store'), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertSessionHasErrors('items.0.product_id');
    }

    public function test_store_rejects_quantities_beyond_stock(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 3]);

        $this->actingAs($seller)
            ->post(route('sales.store'), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_store_rejects_zero_quantity(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 3]);

        $this->actingAs($seller)
            ->post(route('sales.store'), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 0],
                ],
            ])
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_update_recalculates_totals_and_reconciles_stock(): void
    {
        $seller = User::factory()->seller()->create();
        $p1 = Product::factory()->create(['sale_price' => 20, 'stock' => 10]);
        $p2 = Product::factory()->create(['sale_price' => 50, 'stock' => 10]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('sales.update', $sale), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 4],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
        ])->assertRedirect(route('sales.show', $sale));

        $this->assertSame(6, $p1->fresh()->stock);
        $this->assertSame(9, $p2->fresh()->stock);

        $sale->refresh();
        $this->assertSame(130.00, (float) $sale->subtotal);
        $this->assertSame(15.60, (float) $sale->tax_amount);
        $this->assertSame(145.60, (float) $sale->total);
        $this->assertSame(2, $sale->items()->count());

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $p1->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 2,
            'reference' => $sale->invoiceNumber(),
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $p2->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 1,
            'reference' => $sale->invoiceNumber(),
        ]);
    }

    public function test_update_restores_stock_for_removed_or_reduced_products(): void
    {
        $seller = User::factory()->seller()->create();
        $p1 = Product::factory()->create(['sale_price' => 20, 'stock' => 10]);
        $p2 = Product::factory()->create(['sale_price' => 50, 'stock' => 10]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 3],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('sales.update', $sale), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 1],
            ],
        ]);

        $this->assertSame(9, $p1->fresh()->stock);
        $this->assertSame(10, $p2->fresh()->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $p1->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 1,
            'reference' => $sale->invoiceNumber(),
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $p2->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 3,
            'reference' => $sale->invoiceNumber(),
        ]);
    }

    public function test_destroy_restores_stock_and_annuls_movements(): void
    {
        $seller = User::factory()->seller()->create();
        $p1 = Product::factory()->create(['sale_price' => 20, 'stock' => 10]);
        $p2 = Product::factory()->create(['sale_price' => 50, 'stock' => 10]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 3],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('sales.destroy', $sale))
            ->assertRedirect(route('sales.index'));

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertSame(10, $p1->fresh()->stock);
        $this->assertSame(10, $p2->fresh()->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $p1->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 2,
            'reference' => $sale->invoiceNumber(),
        ]);
    }

    public function test_show_displays_invoice_items_and_totals(): void
    {
        $seller = User::factory()->seller()->create();
        $client = Client::factory()->create(['name' => 'Cliente Detalle']);
        $product = Product::factory()->create(['name' => 'Arroz Selecto', 'sale_price' => 25]);

        $sale = Sale::factory()->create(['client_id' => $client->id, 'seller_id' => $seller->id]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 25,
            'total' => 100,
        ]);
        $sale->forceFill(['subtotal' => 100, 'tax_amount' => 12, 'total' => 112])->save();

        $this->actingAs(User::factory()->create())
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee($sale->invoiceNumber())
            ->assertSee('Cliente Detalle')
            ->assertSee('Arroz Selecto')
            ->assertSee('Q 112.00');
    }

    public function test_export_pdf_downloads_the_invoice(): void
    {
        $sale = Sale::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('sales.invoice.pdf', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_list_pdf_downloads_the_filtered_sales(): void
    {
        $client = Client::factory()->create(['name' => 'Cliente Reporte']);
        Sale::factory()->create(['client_id' => $client->id]);

        Sale::factory()->count(10)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('sales.export.pdf', ['search' => 'Cliente Reporte']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_excel_downloads_the_sales_registry(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('sales.export.excel'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_creating_a_sale_via_http_is_audited(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 5]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $seller->id,
            'action' => 'created',
            'auditable_type' => Sale::class,
            'auditable_id' => $sale->id,
        ]);
    }

    public function test_new_sales_are_unpaid_by_default(): void
    {
        $seller = User::factory()->seller()->create();
        $client = Client::factory()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 5]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $this->assertFalse(Sale::query()->firstOrFail()->isPaid());
    }

    public function test_admin_and_manager_can_toggle_the_paid_status(): void
    {
        $sale = Sale::factory()->create(['paid' => false]);

        foreach ([User::ROLE_ADMIN, User::ROLE_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->patch(route('sales.paid.toggle', $sale))
                ->assertRedirect(route('sales.show', $sale));

            $this->assertTrue($sale->fresh()->isPaid());

            $this->actingAs(User::factory()->create(['role' => $role]))
                ->patch(route('sales.paid.toggle', $sale))
                ->assertRedirect(route('sales.show', $sale));

            $this->assertFalse($sale->fresh()->isPaid());
        }
    }

    public function test_sellers_cannot_toggle_the_paid_status(): void
    {
        $sale = Sale::factory()->create(['paid' => false]);

        $this->actingAs(User::factory()->seller()->create())
            ->patch(route('sales.paid.toggle', $sale))
            ->assertForbidden();

        $this->assertFalse($sale->fresh()->isPaid());
    }

    public function test_toggling_the_paid_status_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $sale = Sale::factory()->create(['paid' => false]);

        $this->actingAs($admin)->patch(route('sales.paid.toggle', $sale));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'updated',
            'auditable_type' => Sale::class,
            'auditable_id' => $sale->id,
        ]);
    }

    public function test_client_pending_balance_sums_only_unpaid_sales(): void
    {
        $client = Client::factory()->create();
        $seller = User::factory()->seller()->create();

        Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'subtotal' => 100,
            'tax_amount' => 12,
            'total' => 112,
            'paid' => false,
        ]);

        Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'subtotal' => 50,
            'tax_amount' => 0,
            'total' => 50,
            'paid' => true,
        ]);

        $this->assertSame(112.0, $client->pending_balance);
    }

    public function test_update_validates_stock_restoring_previous_quantities(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 5]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('sales.update', $sale), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 6],
                ],
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->assertSame(40.00, (float) $sale->fresh()->subtotal);
        $this->assertSame(4.80, (float) $sale->fresh()->tax_amount);
        $this->assertSame(44.80, (float) $sale->fresh()->total);
    }

    public function test_store_rolls_back_all_changes_when_inventory_write_fails(): void
    {
        $seller = User::factory()->seller()->create();
        $client = Client::factory()->create(['name' => 'Cliente Rollback']);
        $product = Product::factory()->create(['name' => 'Café', 'sale_price' => 50, 'stock' => 10]);

        InventoryMovement::creating(function () {
            throw new \RuntimeException('inventory write failed');
        });

        $this->actingAs($seller)
            ->post(route('sales.store'), [
                'client_id' => $client->id,
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ])
            ->assertServerError();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_update_rolls_back_partial_stock_reconciliation_when_inventory_write_fails(): void
    {
        $seller = User::factory()->seller()->create();
        $p1 = Product::factory()->create(['name' => 'P1', 'sale_price' => 20, 'stock' => 10]);
        $p2 = Product::factory()->create(['name' => 'P2', 'sale_price' => 50, 'stock' => 10]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 5],
                ['product_id' => $p2->id, 'quantity' => 3],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->assertSame(5, $p1->fresh()->stock);
        $this->assertSame(7, $p2->fresh()->stock);

        $createdCount = 0;
        InventoryMovement::creating(function () use (&$createdCount) {
            $createdCount++;

            if ($createdCount === 2) {
                throw new \RuntimeException('inventory write failed');
            }
        });

        $this->actingAs($admin)
            ->put(route('sales.update', $sale), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $p1->id, 'quantity' => 1],
                    ['product_id' => $p2->id, 'quantity' => 8],
                ],
            ])
            ->assertServerError();

        $this->assertSame(5, $p1->fresh()->stock);
        $this->assertSame(7, $p2->fresh()->stock);

        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $p1->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $p2->id,
            'quantity' => 3,
        ]);

        $this->assertSame(250.00, (float) $sale->fresh()->subtotal);
        $this->assertSame(30.00, (float) $sale->fresh()->tax_amount);
        $this->assertSame(280.00, (float) $sale->fresh()->total);

        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseMissing('inventory_movements', [
            'reference' => $sale->invoiceNumber(),
            'type' => InventoryMovement::TYPE_IN,
        ]);
    }

    public function test_destroy_rolls_back_stock_restore_and_deletion_when_inventory_write_fails(): void
    {
        $seller = User::factory()->seller()->create();
        $p1 = Product::factory()->create(['name' => 'P1', 'sale_price' => 20, 'stock' => 10]);
        $p2 = Product::factory()->create(['name' => 'P2', 'sale_price' => 50, 'stock' => 10]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 3],
            ],
        ]);

        $sale = Sale::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->assertSame(8, $p1->fresh()->stock);
        $this->assertSame(7, $p2->fresh()->stock);

        $createdCount = 0;
        InventoryMovement::creating(function () use (&$createdCount) {
            $createdCount++;

            if ($createdCount === 2) {
                throw new \RuntimeException('inventory write failed');
            }
        });

        $this->actingAs($admin)
            ->delete(route('sales.destroy', $sale))
            ->assertServerError();

        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertSame(8, $p1->fresh()->stock);
        $this->assertSame(7, $p2->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseMissing('inventory_movements', [
            'reference' => $sale->invoiceNumber(),
            'type' => InventoryMovement::TYPE_IN,
        ]);
    }
}
