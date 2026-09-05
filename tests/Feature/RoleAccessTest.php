<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_settings(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertOk();
    }

    public function test_seller_cannot_access_admin_settings(): void
    {
        $user = User::factory()->seller()->create();

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertForbidden();
    }

    public function test_manager_cannot_access_admin_settings(): void
    {
        $user = User::factory()->manager()->create();

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertForbidden();
    }

    public function test_manager_can_access_inventory_and_read_sales(): void
    {
        $user = User::factory()->manager()->create();

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('sales.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertForbidden();
    }

    public function test_seller_cannot_access_inventory_or_reports(): void
    {
        $user = User::factory()->seller()->create();

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_seller_can_access_clients_products_and_sales(): void
    {
        $user = User::factory()->seller()->create();

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('sales.index'))
            ->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->get(route('clients.index'))
            ->assertRedirect(route('login'));

        $this->get(route('products.index'))
            ->assertRedirect(route('login'));
    }
}
