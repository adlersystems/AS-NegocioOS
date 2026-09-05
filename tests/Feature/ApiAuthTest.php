<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private function login(): array
    {
        $user = User::factory()->admin()->create(['email' => 'api@as-negocios.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'api@as-negocios.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']])
            ->assertJsonPath('user.email', 'api@as-negocios.com');

        return [$user, $response->json('token')];
    }

    public function test_login_returns_a_token_and_the_user(): void
    {
        $this->login();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/clients')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        [, $token] = $this->login();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'api@as-negocios.com');
    }

    public function test_logout_revokes_the_token(): void
    {
        [, $token] = $this->login();

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_settings_endpoint_is_admin_only(): void
    {
        $seller = User::factory()->seller()->create();
        Sanctum::actingAs($seller);
        $this->getJson('/api/settings')->assertForbidden();

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->getJson('/api/settings')->assertOk();
    }
}
