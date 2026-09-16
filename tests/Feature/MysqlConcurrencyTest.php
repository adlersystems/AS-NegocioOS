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
