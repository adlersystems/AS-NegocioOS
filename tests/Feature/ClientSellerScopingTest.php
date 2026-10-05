<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Step 8 — client surfaces must expose a seller's own sales only, while the
 * client registry itself stays shared and admin/encargado keep global views.
 *
 * Fixtures are fully deterministic: explicit ids-free names, explicit totals and
 * explicit paid flags, so no assertion depends on random factory output.
 */
class ClientSellerScopingTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $name): User
    {
        return User::factory()->seller()->create(['name' => $name]);
    }

    private function sale(Client $client, User $seller, float $total, bool $paid): Sale
    {
        return Sale::factory()->create([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'tax_rate' => '0',
            'paid' => $paid,
            'notes' => null,
        ]);
    }

    private function money(float $amount): string
    {
        return Setting::formatMoney($amount);
    }

    public function test_vendedor_sees_only_own_sales_on_a_client_they_served(): void
    {
        $vendedor = $this->seller('Vendedor Propietario');
        $colega = $this->seller('Vendedor Colega');
        $cliente = Client::factory()->create(['name' => 'Cliente Compartido']);

        $mine = $this->sale($cliente, $vendedor, 100, true);
        $theirs = $this->sale($cliente, $colega, 900, false);

        $this->actingAs($vendedor)
            ->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('Cliente Compartido')
            ->assertSee($mine->invoiceNumber())
            ->assertDontSee($theirs->invoiceNumber())
            ->assertDontSee($this->money(900))
            ->assertDontSee('Vendedor Colega');
    }

    public function test_vendedor_sees_a_clean_empty_state_for_another_sellers_client(): void
    {
        $vendedor = $this->seller('Vendedor Primario');
        $colega = $this->seller('Vendedor Secundario');
        $cliente = Client::factory()->create(['name' => 'Cliente Ajeno']);

        $theirs = $this->sale($cliente, $colega, 900, true);

        $response = $this->actingAs($vendedor)
            ->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee('Cliente Ajeno')
            ->assertSee(__('app.clients.no_visible_history'))
            ->assertDontSee(__('app.clients.no_history'))
            ->assertDontSee($theirs->invoiceNumber())
            ->assertDontSee($this->money(900))
            ->assertDontSee('Vendedor Secundario')
            ->assertDontSee(__('app.sales.status_paid'))
            ->assertDontSee(__('app.sales.status_unpaid'));

        $this->assertStringNotContainsString($theirs->invoiceNumber(), $response->getContent());
        $this->assertSame(0, $response->viewData('sales')->count());
        $this->assertSame(1, Sale::query()->where('client_id', $cliente->id)->count());
    }

    public function test_vendedor_client_index_aggregates_only_include_own_sales(): void
    {
        $vendedor = $this->seller('Vendedor Indice');
        $colega = $this->seller('Vendedor Ajeno');
        $cliente = Client::factory()->create(['name' => 'Cliente Indice']);

        $this->sale($cliente, $vendedor, 100, true);
        $this->sale($cliente, $vendedor, 250, false);
        $this->sale($cliente, $colega, 900, false);

        $this->actingAs($vendedor)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Cliente Indice')
            ->assertSee($this->money(350))
            ->assertSee($this->money(250))
            ->assertDontSee($this->money(1150));

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/clients?search=Cliente Indice')
            ->assertOk()
            ->assertJsonPath('data.0.sales_count', 2)
            ->assertJsonPath('data.0.sales_total', 350)
            ->assertJsonPath('data.0.pending_balance', 250);
    }

    public function test_vendedor_api_client_detail_returns_only_own_sales(): void
    {
        $vendedor = $this->seller('Vendedor Api');
        $colega = $this->seller('Colega Api');
        $cliente = Client::factory()->create(['name' => 'Cliente Api']);

        $mine = $this->sale($cliente, $vendedor, 100, true);
        $theirs = $this->sale($cliente, $colega, 900, false);

        Sanctum::actingAs($vendedor);

        $response = $this->getJson("/api/clients/{$cliente->id}")->assertOk();

        $response->assertJsonPath('data.pending_balance', 0)
            ->assertJsonPath('data.sales.0.invoice_number', $mine->invoiceNumber())
            ->assertJsonPath('data.sales.0.total', 100)
            ->assertJsonPath('data.sales.0.paid', true)
            ->assertJsonPath('data.sales.0.seller', 'Vendedor Api')
            ->assertJsonCount(1, 'data.sales');

        $payload = $response->getContent();

        $this->assertStringNotContainsString($theirs->invoiceNumber(), $payload);
        $this->assertStringNotContainsString('Colega Api', $payload);
        $this->assertStringNotContainsString('900', $payload);
    }

    public function test_vendedor_api_client_index_scopes_aggregates_to_own_sales(): void
    {
        $vendedor = $this->seller('Vendedor Api Indice');
        $colega = $this->seller('Colega Api Indice');

        $shared = Client::factory()->create(['name' => 'Compartido Api']);
        $onlyTheirs = Client::factory()->create(['name' => 'Solo Colega Api']);

        $this->sale($shared, $vendedor, 100, false);
        $this->sale($shared, $colega, 900, false);
        $this->sale($onlyTheirs, $colega, 400, false);

        Sanctum::actingAs($vendedor);

        $response = $this->getJson('/api/clients')->assertOk();

        $byName = collect($response->json('data'))->keyBy('name');

        $this->assertSame(1, $byName['Compartido Api']['sales_count']);
        $this->assertEqualsWithDelta(100.0, $byName['Compartido Api']['sales_total'], 0.001);
        $this->assertEqualsWithDelta(100.0, $byName['Compartido Api']['pending_balance'], 0.001);

        $this->assertSame(0, $byName['Solo Colega Api']['sales_count']);
        $this->assertEqualsWithDelta(0.0, $byName['Solo Colega Api']['sales_total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $byName['Solo Colega Api']['pending_balance'], 0.001);
    }

    public function test_admin_retains_global_client_visibility(): void
    {
        $vendedor = $this->seller('Vendedor Global');
        $colega = $this->seller('Colega Global');
        $cliente = Client::factory()->create(['name' => 'Cliente Global']);

        $mine = $this->sale($cliente, $vendedor, 100, true);
        $theirs = $this->sale($cliente, $colega, 900, false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee($mine->invoiceNumber())
            ->assertSee($theirs->invoiceNumber())
            ->assertSee($this->money(1000))
            ->assertSee($this->money(900))
            ->assertSee('Colega Global');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee($this->money(1000))
            ->assertSee($this->money(900));

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson("/api/clients/{$cliente->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.sales')
            ->assertJsonPath('data.pending_balance', 900);

        $this->getJson('/api/clients')
            ->assertOk()
            ->assertJsonPath('data.0.sales_count', 2)
            ->assertJsonPath('data.0.sales_total', 1000)
            ->assertJsonPath('data.0.pending_balance', 900);
    }

    public function test_encargado_retains_global_client_visibility(): void
    {
        $vendedor = $this->seller('Vendedor Delegado');
        $colega = $this->seller('Colega Delegado');
        $cliente = Client::factory()->create(['name' => 'Cliente Delegado']);

        $mine = $this->sale($cliente, $vendedor, 100, true);
        $theirs = $this->sale($cliente, $colega, 900, false);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee($mine->invoiceNumber())
            ->assertSee($theirs->invoiceNumber())
            ->assertSee($this->money(1000));

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee($this->money(1000))
            ->assertSee($this->money(900));

        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson("/api/clients/{$cliente->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.sales')
            ->assertJsonPath('data.pending_balance', 900);

        $this->getJson('/api/clients')
            ->assertOk()
            ->assertJsonPath('data.0.sales_count', 2)
            ->assertJsonPath('data.0.sales_total', 1000);
    }

    public function test_client_without_any_sale_shows_a_clean_empty_state_for_every_role(): void
    {
        $cliente = Client::factory()->create(['name' => 'Cliente Sin Ventas']);

        foreach ([User::ROLE_ADMIN, User::ROLE_SELLER, User::ROLE_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('clients.show', $cliente))
                ->assertOk()
                ->assertSee('Cliente Sin Ventas');
        }

        $vendedor = $this->seller('Vendedor Sin Ventas');
        $this->sale($cliente, $this->seller('Otro Sin Ventas'), 750, false);

        $this->actingAs($vendedor)
            ->get(route('clients.show', $cliente))
            ->assertOk()
            ->assertSee(__('app.clients.no_visible_history'))
            ->assertDontSee($this->money(750));

        Sanctum::actingAs($vendedor);

        $this->getJson("/api/clients/{$cliente->id}")
            ->assertOk()
            ->assertJsonPath('data.pending_balance', 0)
            ->assertJsonCount(0, 'data.sales');
    }

    public function test_api_client_listing_caps_page_size_at_one_hundred_items(): void
    {
        $vendedor = $this->seller('Vendedor Volumen');
        Client::factory()->count(120)->create();

        Sanctum::actingAs($vendedor);

        $response = $this->getJson('/api/clients?per_page=9999')->assertOk();

        $this->assertCount(100, $response->json('data'));
        $this->assertSame(100, $response->json('meta.per_page'));
        $this->assertSame(120, $response->json('meta.total'));
    }

    public function test_api_client_detail_caps_sale_limit_at_one_hundred_items(): void
    {
        $vendedor = $this->seller('Vendedor Historial');
        $cliente = Client::factory()->create(['name' => 'Cliente Historial Api']);

        $this->sale($cliente, $vendedor, 10, true);

        Sanctum::actingAs($vendedor);

        $response = $this->getJson("/api/clients/{$cliente->id}?limit=9999")->assertOk();

        $this->assertCount(1, $response->json('data.sales'));
    }

    public function test_seller_registry_remains_shared_and_open_to_every_role(): void
    {
        $vendedor = $this->seller('Vendedor Registro');

        $this->actingAs($vendedor)
            ->get(route('clients.create'))
            ->assertOk();

        $this->actingAs($vendedor)
            ->post(route('clients.store'), ['name' => 'Cliente Walk In'])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['name' => 'Cliente Walk In']);
    }
}
