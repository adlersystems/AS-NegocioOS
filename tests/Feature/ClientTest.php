<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_client_routes(): void
    {
        $this->get(route('clients.index'))->assertRedirect(route('login'));
        $this->get(route('clients.create'))->assertRedirect(route('login'));
        $this->get(route('clients.show', Client::factory()->create()))->assertRedirect(route('login'));
    }

    public function test_all_roles_can_access_the_clients_index(): void
    {
        foreach ([
            User::ROLE_ADMIN,
            User::ROLE_SELLER,
            User::ROLE_MANAGER,
        ] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('clients.index'))
                ->assertOk();
        }
    }

    public function test_index_renders_clients(): void
    {
        $user = User::factory()->create();
        Client::factory()->count(3)->create(['name' => 'Cliente Visible']);

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Cliente Visible');
    }

    public function test_index_filters_clients_by_search(): void
    {
        $user = User::factory()->create();
        Client::factory()->create(['name' => 'María López', 'email' => 'maria@example.com']);
        $other = Client::factory()->create(['name' => 'Juan Pérez']);

        $this->actingAs($user)
            ->get(route('clients.index', ['search' => 'María']))
            ->assertOk()
            ->assertSee('María López');

        $response = $this->actingAs($user)
            ->get(route('clients.index', ['search' => 'María']));

        $response->assertDontSee($other->name);
    }

    public function test_index_paginates_clients(): void
    {
        $user = User::factory()->create();
        Client::factory()->count(10)->create();
        $newest = Client::factory()->create(['name' => 'Cliente Más Reciente']);

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Cliente Más Reciente')
            ->assertSee('Mostrando 1–10 de 11');

        $this->actingAs($user)
            ->get(route('clients.index', ['page' => 2]))
            ->assertOk()
            ->assertDontSee('Cliente Más Reciente')
            ->assertSee('Mostrando 11–11 de 11');
    }

    public function test_store_creates_a_client(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'nit' => '1234567-8',
            'phone' => '5555-1234',
            'address' => 'Av. Principal 123',
            'preferred_language' => 'es',
            'notes' => 'Cliente frecuente',
        ]);

        $client = Client::query()->where('email', 'ana@example.com')->firstOrFail();

        $response->assertRedirect(route('clients.show', $client));
        $this->assertSame('Ana García', $client->name);
        $this->assertSame('5555-1234', $client->phone);

        $response->assertSessionHas('success');
    }

    public function test_store_validates_required_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('clients.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();
        Client::factory()->create(['email' => 'usado@example.com']);

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'name' => 'Nuevo Cliente',
                'email' => 'usado@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_show_displays_purchase_history_and_pending_balance(): void
    {
        $user = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $client = Client::factory()->create(['name' => 'Cliente Historial', 'pending_balance' => 150.5]);

        $this->actingAs($user);

        foreach ([100.0, 50.0] as $total) {
            Sale::factory()->create([
                'client_id' => $client->id,
                'seller_id' => $seller->id,
                'subtotal' => $total,
                'tax_amount' => 0,
                'total' => $total,
            ]);
        }

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Cliente Historial')
            ->assertSee('Q 150.50')
            ->assertSee('Q 150.00')
            ->assertSee('Historial de compras');
    }

    public function test_update_edits_the_client_and_ignores_its_own_email(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['name' => 'Nombre Original', 'email' => 'mismo@example.com']);

        $this->actingAs($user)
            ->put(route('clients.update', $client), [
                'name' => 'Nombre Nuevo',
                'email' => 'mismo@example.com',
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Nombre Nuevo',
        ]);
    }

    public function test_update_rejects_email_used_by_another_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $other = Client::factory()->create(['email' => 'ocupado@example.com']);

        $this->actingAs($user)
            ->put(route('clients.update', $client), [
                'name' => 'Cliente',
                'email' => 'ocupado@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_update_redirects_to_a_page_rendering_the_success_toast(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['name' => 'Antes']);

        $this->actingAs($user)
            ->put(route('clients.update', $client), ['name' => 'Después'])
            ->assertRedirect(route('clients.show', $client));

        $html = $this->actingAs($user)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->getContent();

        $encoded = json_encode(
            __('app.flash.updated', ['entity' => __('app.clients.singular')]),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT,
        );

        $this->assertStringContainsString($encoded, $html);
        $this->assertStringContainsString('data-toast-initial', $html);
    }

    public function test_destroy_deletes_the_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_export_pdf_returns_a_pdf(): void
    {
        $user = User::factory()->create();
        Client::factory()->count(2)->create(['name' => 'Para Exportar']);

        $this->actingAs($user)
            ->get(route('clients.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_excel_returns_a_spreadsheet(): void
    {
        $user = User::factory()->create();
        Client::factory()->count(2)->create(['name' => 'Para Excel']);

        $response = $this->actingAs($user)
            ->get(route('clients.export.excel'))
            ->assertOk();

        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type', ''));
    }

    public function test_creating_a_client_via_http_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Cliente Auditado HTTP',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'created',
            'auditable_type' => Client::class,
        ]);

        $this->assertSame(1, AuditLog::query()->where('user_id', $user->id)->count());
    }
}
