<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_user_routes(): void
    {
        $user = User::factory()->create();

        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->get(route('users.create'))->assertRedirect(route('login'));
        $this->get(route('users.edit', $user))->assertRedirect(route('login'));
    }

    public function test_only_admin_can_access_the_users_index(): void
    {
        foreach ([User::ROLE_SELLER, User::ROLE_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('users.index'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Nuevo Vendedor',
                'email' => 'vendedor@example.com',
                'role' => User::ROLE_SELLER,
                'language' => 'es',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'vendedor@example.com',
            'name' => 'Nuevo Vendedor',
            'role' => User::ROLE_SELLER,
        ]);

        $this->assertNotSame('secret123', User::where('email', 'vendedor@example.com')->first()->password);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'dup@example.com']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Duplicado',
                'email' => 'dup@example.com',
                'role' => User::ROLE_SELLER,
                'language' => 'es',
                'password' => 'secret123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_a_user_without_changing_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->seller()->create(['name' => 'Antes', 'email' => 'a@example.com']);

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'Después',
                'email' => 'a@example.com',
                'role' => User::ROLE_MANAGER,
                'language' => 'en',
                'password' => '',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Después', $user->name);
        $this->assertSame(User::ROLE_MANAGER, $user->role);
        $this->assertSame('en', $user->language);
    }

    public function test_admin_can_update_a_user_with_a_new_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->seller()->create();

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'language' => $user->language,
            'password' => 'nueva12345',
        ])->assertRedirect(route('users.index'));

        $this->assertNotSame($user->fresh()->password, $user->password);
    }

    public function test_admin_cannot_demote_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => User::ROLE_SELLER,
                'language' => 'es',
                'password' => '',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_admin_when_more_than_one_remains(): void
    {
        $adminA = User::factory()->admin()->create();
        $adminB = User::factory()->admin()->create();

        $this->actingAs($adminA)
            ->delete(route('users.destroy', $adminB))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $adminB->id]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->seller()->create();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_can_view_costs_is_true_for_admin_and_manager_only(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $seller = User::factory()->seller()->create();

        $this->assertTrue($admin->canViewCosts());
        $this->assertTrue($manager->canViewCosts());
        $this->assertFalse($seller->canViewCosts());
    }
}
