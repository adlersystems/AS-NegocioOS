<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guests_are_redirected_from_audit(): void
    {
        $this->get(route('audit.index'))->assertRedirect(route('login'));
        $this->get('/audit/1')->assertRedirect(route('login'));
    }

    public function test_only_admin_can_access_audit(): void
    {
        foreach ([User::ROLE_SELLER, User::ROLE_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('audit.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_sees_logged_actions_and_record_links(): void
    {
        $this->actingAs($this->admin());

        $client = Client::factory()->create(['name' => 'Cliente Auditado Vista']);

        $this->get(route('audit.index'))
            ->assertOk()
            ->assertSee(__('app.menu.audit'))
            ->assertSee('Cliente Auditado Vista')
            ->assertSee(route('clients.show', $client));

        $log = AuditLog::query()
            ->where('auditable_id', $client->id)
            ->first();

        $this->get(route('audit.show', $log))
            ->assertOk()
            ->assertSee(__('app.audit.detail_title'))
            ->assertSee('Cliente Auditado Vista')
            ->assertSee(__('app.audit.action_created'));
    }

    public function test_show_renders_old_and_new_values(): void
    {
        $this->actingAs($this->admin());

        $client = Client::factory()->create(['name' => 'Antes']);
        $client->update(['name' => 'Después']);

        $log = AuditLog::query()
            ->where('auditable_id', $client->id)
            ->where('action', 'updated')
            ->first();

        $response = $this->get(route('audit.show', $log));

        $response->assertOk()
            ->assertSee('Antes')
            ->assertSee('Después')
            ->assertSee(__('app.audit.changes'));
    }

    public function test_action_filter_narrows_the_list(): void
    {
        $this->actingAs($this->admin());

        Client::factory()->create(['name' => 'Solo Creado']);
        $product = Product::factory()->create(['name' => 'Producto Update']);
        $product->update(['name' => 'Producto Update 2']);

        $this->get(route('audit.index', ['action' => 'created']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2)
            ->assertSee('Solo Creado');

        $this->get(route('audit.index', ['action' => 'updated']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }

    public function test_model_and_user_filters_apply(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Client::factory()->create(['name' => 'Cliente Filtrado']);

        $this->get(route('audit.index', ['model' => 'product']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);

        $this->get(route('audit.index', ['model' => 'client']))
            ->assertOk()
            ->assertSee('Cliente Filtrado');

        $other = User::factory()->seller()->create();
        $this->get(route('audit.index', ['user_id' => $other->id]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
    }

    public function test_date_range_filter_applies(): void
    {
        $this->actingAs($this->admin());

        $client = Client::factory()->create(['name' => 'Cliente Con Fecha']);
        $log = AuditLog::query()
            ->where('auditable_id', $client->id)
            ->first();
        $log->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->get(route('audit.index', ['from' => now()->subDays(3)->toDateString()]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);

        $this->get(route('audit.index', ['from' => now()->subDays(7)->toDateString()]))
            ->assertOk()
            ->assertSee('Cliente Con Fecha');
    }

    public function test_deleted_records_show_old_values_and_badge(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['name' => 'Producto Borrado']);
        $product->delete();

        $log = AuditLog::query()
            ->where('auditable_id', $product->id)
            ->where('action', 'deleted')
            ->first();

        $this->get(route('audit.index'))
            ->assertOk()
            ->assertSee(__('app.audit.action_deleted'));

        $this->get(route('audit.show', $log))
            ->assertOk()
            ->assertSee('Producto Borrado');
    }
}
