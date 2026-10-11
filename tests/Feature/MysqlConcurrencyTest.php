<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * MySQL integration coverage for the sales + inventory concurrency guard.
 *
 * These tests run exclusively against the dedicated MySQL database
 * `as_negocios_test` (created locally for this purpose) and exercise the
 * real InnoDB row-locking behaviour that the SQLite suite cannot validate.
 * Run them with: vendor\bin\phpunit -c phpunit.mysql.xml
 */
#[Group('mysql')]
class MysqlConcurrencyTest extends TestCase
{
    private const TEST_DB = 'as_negocios_test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', self::TEST_DB);
        config()->set('database.connections.mysql.username', 'root');
        config()->set('database.connections.mysql.password', '');

        DB::purge('mysql');

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_sale_lifecycle_works_on_mysql(): void
    {
        $seller = User::factory()->seller()->create();
        $client = Client::factory()->create();
        $product = Product::factory()->create(['name' => 'Café', 'sale_price' => 50, 'stock' => 10]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])->assertRedirect();

        $sale = Sale::query()->firstOrFail();
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(150.00, (float) $sale->subtotal);
        $this->assertSame(18.00, (float) $sale->tax_amount);
        $this->assertSame(168.00, (float) $sale->total);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 3,
            'reference' => $sale->invoiceNumber(),
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('sales.update', $sale), [
            'seller_id' => $seller->id,
            'client_id' => $client->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ])->assertRedirect(route('sales.show', $sale));

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(5, $sale->fresh()->items()->count() > 0 ? $sale->fresh()->items()->first()->quantity : 0);

        $this->actingAs($admin)->delete(route('sales.destroy', $sale))->assertRedirect(route('sales.index'));
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_oversell_is_rejected_on_mysql(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 50, 'stock' => 2]);

        $this->actingAs($seller)
            ->post(route('sales.store'), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ])
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_lock_for_update_blocks_a_competing_transaction(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $primary = DB::connection('mysql');
        $primary->beginTransaction();

        try {
            Product::whereKey($product->id)->lockForUpdate()->first();

            $competing = $this->secondaryPdo();

            try {
                $stmt = $competing->prepare('SELECT id, stock FROM products WHERE id = ? FOR UPDATE NOWAIT');
                $stmt->execute([$product->id]);
                $this->fail('A competing transaction must not be able to lock the same product row.');
            } catch (PDOException $e) {
                $this->assertStringContainsStringIgnoringCase('lock', $e->getMessage());
            }
        } finally {
            $primary->rollBack();
        }

        $competing = $this->secondaryPdo();
        $stmt = $competing->prepare('SELECT id, stock FROM products WHERE id = ? FOR UPDATE');
        $stmt->execute([$product->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(5, (int) $row['stock']);
    }

    public function test_two_admins_updating_the_same_sale_serialize_on_the_sale_row_lock(): void
    {
        $seller = User::factory()->seller()->create();
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 100]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertRedirect();

        $sale = Sale::query()->firstOrFail();
        $this->assertSame(98, $product->fresh()->stock);

        // A competing session holds an exclusive lock on the sale row.
        $competing = $this->secondaryPdo();
        $competing->beginTransaction();
        $competing->prepare('SELECT id FROM sales WHERE id = ? FOR UPDATE')->execute([$sale->id]);

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        // The blocked edit must fail cleanly without corrupting stock or lines.
        $this->actingAs($admin)
            ->put(route('sales.update', $sale), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ])
            ->assertServerError();

        $this->assertSame(98, $product->fresh()->stock);
        $this->assertSame(2, (int) $sale->fresh()->items()->first()->quantity);
        $this->assertDatabaseMissing('inventory_movements', [
            'reference' => $sale->invoiceNumber(),
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 3,
        ]);

        // Once the lock is released the same edit uses the committed state.
        $competing->rollBack();

        $this->actingAs($admin)
            ->put(route('sales.update', $sale), [
                'seller_id' => $seller->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ])
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame(95, $product->fresh()->stock);
        $this->assertSame(5, (int) $sale->fresh()->items()->first()->quantity);

        $outTotal = (int) InventoryMovement::query()
            ->where('product_id', $product->id)
            ->where('type', InventoryMovement::TYPE_OUT)
            ->where('reference', $sale->invoiceNumber())
            ->sum('quantity');

        $this->assertSame(5, $outTotal);
    }

    public function test_two_cancellations_of_the_same_sale_restore_stock_exactly_once(): void
    {
        $seller = User::factory()->seller()->create();
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 100]);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ])->assertRedirect();

        $sale = Sale::query()->firstOrFail();
        $invoice = $sale->invoiceNumber();
        $this->assertSame(96, $product->fresh()->stock);

        $competing = $this->secondaryPdo();
        $competing->beginTransaction();
        $competing->prepare('SELECT id FROM sales WHERE id = ? FOR UPDATE')->execute([$sale->id]);

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->actingAs($admin)
            ->delete(route('sales.destroy', $sale))
            ->assertServerError();

        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertSame(96, $product->fresh()->stock);
        $this->assertSame(0, InventoryMovement::query()
            ->where('reference', $invoice)
            ->where('type', InventoryMovement::TYPE_IN)
            ->count());

        $competing->rollBack();

        $this->actingAs($admin)
            ->delete(route('sales.destroy', $sale))
            ->assertRedirect(route('sales.index'));

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertSame(100, $product->fresh()->stock);
        $this->assertSame(1, InventoryMovement::query()
            ->where('product_id', $product->id)
            ->where('type', InventoryMovement::TYPE_IN)
            ->where('quantity', 4)
            ->where('reference', $invoice)
            ->count());
    }

    public function test_competing_sales_for_insufficient_stock_serialize_on_the_product_lock(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 1]);

        $competing = $this->secondaryPdo();
        $competing->beginTransaction();
        $competing->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE')->execute([$product->id]);

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertServerError();

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(1, $product->fresh()->stock);

        $competing->rollBack();

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(0, $product->fresh()->stock);

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_archival_racing_with_sale_creation_serializes_and_then_blocks_the_sale(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sale_price' => 10, 'stock' => 10]);

        // A competing session holds the product row with an uncommitted archival.
        $competing = $this->secondaryPdo();
        $competing->beginTransaction();
        $competing->prepare('UPDATE products SET deleted_at = NOW() WHERE id = ?')->execute([$product->id]);

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertServerError();

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, Product::withTrashed()->find($product->id)->stock);

        $competing->commit();

        // The product is now archived: the sale must be rejected by the guard.
        $this->actingAs($seller)->post(route('sales.store'), [
            'seller_id' => $seller->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, Product::withTrashed()->find($product->id)->stock);
    }

    private function secondaryPdo(): PDO
    {
        return new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname='.self::TEST_DB.';charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
