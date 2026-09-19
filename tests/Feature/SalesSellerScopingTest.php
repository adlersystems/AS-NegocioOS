<?php

namespace Tests\Feature;

use App\Exports\SalesExport;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesSellerScopingTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->create(['sale_price' => 10, 'stock' => 50]);
    }

    private function postSale(User $operator, int $forgedSellerId): Sale
    {
        $product = $this->product();

        $this->actingAs($operator)->post(route('sales.store'), [
            'seller_id' => $forgedSellerId,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        return Sale::query()->latest('id')->firstOrFail();
    }

    public function test_vendedor_create_forces_authenticated_seller_id_when_seller_id_is_forged(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();

        $sale = $this->postSale($vendedor, $other->id);

        $this->assertSame($vendedor->id, $sale->seller_id);
        $this->assertNotSame($other->id, $sale->seller_id);
    }

    public function test_vendedor_create_uses_authenticated_seller_id_when_seller_id_is_omitted(): void
    {
        $vendedor = User::factory()->seller()->create();
        $product = $this->product();

        $this->actingAs($vendedor)->post(route('sales.store'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $sale = Sale::query()->latest('id')->firstOrFail();

        $this->assertSame($vendedor->id, $sale->seller_id);
    }

    public function test_encargado_create_forces_authenticated_seller_id(): void
    {
        $encargado = User::factory()->manager()->create();
        $other = User::factory()->seller()->create();

        $sale = $this->postSale($encargado, $other->id);

        $this->assertSame($encargado->id, $sale->seller_id);
        $this->assertNotSame($other->id, $sale->seller_id);
    }

    public function test_admin_create_preserves_explicitly_selected_seller(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->seller()->create();

        $sale = $this->postSale($admin, $target->id);

        $this->assertSame($target->id, $sale->seller_id);
    }

    public function test_vendedor_create_form_fixes_the_seller_and_hides_selectors(): void
    {
        $vendedor = User::factory()->seller()->create(['name' => 'Vendedor Autenticado']);
        $other = User::factory()->seller()->create(['name' => 'Otro Vendedor']);

        $this->actingAs($vendedor)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Vendedor Autenticado')
            ->assertDontSee('Otro Vendedor')
            ->assertSee('name="seller_id"', false);
    }

    public function test_vendedor_index_lists_only_own_sales(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();

        $mine = Sale::factory()->count(2)->create(['seller_id' => $vendedor->id]);
        $theirs = Sale::factory()->count(2)->create(['seller_id' => $other->id]);

        $this->actingAs($vendedor)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee($mine[0]->invoiceNumber())
            ->assertSee($mine[1]->invoiceNumber())
            ->assertDontSee($theirs[0]->invoiceNumber())
            ->assertDontSee($theirs[1]->invoiceNumber());
    }

    public function test_vendedor_cannot_open_another_sellers_sale(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $mine = Sale::factory()->create(['seller_id' => $vendedor->id]);
        $theirs = Sale::factory()->create(['seller_id' => $other->id]);

        $this->actingAs($vendedor)
            ->get(route('sales.show', $mine))
            ->assertOk();

        $this->actingAs($vendedor)
            ->get(route('sales.show', $theirs))
            ->assertNotFound();
    }

    public function test_vendedor_cannot_download_another_sellers_invoice_pdf(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $mine = Sale::factory()->create(['seller_id' => $vendedor->id]);
        $theirs = Sale::factory()->create(['seller_id' => $other->id]);

        $this->actingAs($vendedor)
            ->get(route('sales.invoice.pdf', $mine))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($vendedor)
            ->get(route('sales.invoice.pdf', $theirs))
            ->assertNotFound();
    }

    public function test_vendedor_sales_exports_contain_only_own_sales(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $mine = Sale::factory()->count(2)->create(['seller_id' => $vendedor->id]);
        $theirs = Sale::factory()->count(2)->create(['seller_id' => $other->id]);

        $rows = (new SalesExport(null, null, null, null, $vendedor))->collection();

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->pluck('id')->diff($mine->pluck('id'))->isEmpty());
        $this->assertFalse($rows->contains('id', $theirs[0]->id));
        $this->assertFalse($rows->contains('id', $theirs[1]->id));
    }

    public function test_vendedor_list_pdf_export_returns_ok(): void
    {
        $vendedor = User::factory()->seller()->create();
        Sale::factory()->create(['seller_id' => $vendedor->id]);
        Sale::factory()->count(10)->create();

        $this->actingAs($vendedor)
            ->get(route('sales.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_and_encargado_sales_index_retain_global_visibility(): void
    {
        $first = User::factory()->seller()->create();
        $second = User::factory()->seller()->create();
        $sales = collect()
            ->merge(Sale::factory()->count(2)->create(['seller_id' => $first->id]))
            ->merge(Sale::factory()->count(2)->create(['seller_id' => $second->id]));

        foreach ([User::ROLE_ADMIN, User::ROLE_MANAGER] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor)
                ->get(route('sales.index'))
                ->assertOk()
                ->assertSee($sales[0]->invoiceNumber())
                ->assertSee($sales[2]->invoiceNumber());
        }

        $admin = User::factory()->admin()->create();
        $this->assertSame(4, (new SalesExport(null, null, null, null, $admin))->collection()->count());
    }

    public function test_vendedor_api_sales_index_returns_only_own_sales(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $mine = Sale::factory()->count(2)->create(['seller_id' => $vendedor->id]);
        Sale::factory()->count(2)->create(['seller_id' => $other->id]);

        Sanctum::actingAs($vendedor);

        $response = $this->getJson('/api/sales')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->diff($mine->pluck('id'))->isEmpty());
    }

    public function test_vendedor_cannot_retrieve_another_sellers_sale_by_api(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $mine = Sale::factory()->create(['seller_id' => $vendedor->id]);
        $theirs = Sale::factory()->create(['seller_id' => $other->id]);

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/sales/{$mine->id}")->assertOk();
        $this->getJson("/api/sales/{$theirs->id}")->assertNotFound();
    }

    public function test_admin_and_encargado_api_sales_remain_globally_visible(): void
    {
        $first = User::factory()->seller()->create();
        $second = User::factory()->seller()->create();
        Sale::factory()->count(2)->create(['seller_id' => $first->id]);
        Sale::factory()->count(2)->create(['seller_id' => $second->id]);

        foreach ([User::ROLE_ADMIN, User::ROLE_MANAGER] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->getJson('/api/sales')->assertOk()->assertJsonCount(4, 'data');
        }
    }

    public function test_vendedor_dashboard_recent_sales_are_limited_to_own_sales(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $mine = Sale::factory()->count(2)->create(['seller_id' => $vendedor->id]);
        $theirs = Sale::factory()->count(2)->create(['seller_id' => $other->id]);

        $this->actingAs($vendedor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($mine[0]->invoiceNumber())
            ->assertDontSee($theirs[0]->invoiceNumber());

        Sanctum::actingAs($vendedor);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonCount(2, 'recent_sales');
        $this->assertFalse(collect($response->json('recent_sales'))->contains('invoice_number', $theirs[0]->invoiceNumber()));
    }

    public function test_vendedor_dashboard_kpis_and_top_client_exclude_other_sellers(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $clientMine = Client::factory()->create(['name' => 'Cliente Propio']);
        $clientOther = Client::factory()->create(['name' => 'Cliente Ajeno']);

        Sale::factory()->create(['seller_id' => $vendedor->id, 'client_id' => $clientMine->id, 'total' => 200]);
        Sale::factory()->create(['seller_id' => $other->id, 'client_id' => $clientOther->id, 'total' => 900]);

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('kpis.month_revenue', 200)
            ->assertJsonPath('kpis.today_revenue', 200)
            ->assertJsonPath('top_client.name', 'Cliente Propio')
            ->assertJsonPath('top_client.total', 200);

        $this->actingAs($vendedor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Q 200.00', false)
            ->assertDontSee('Q 900.00', false);
    }
}
