<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function login(): array
    {
        $user = User::factory()->admin()->create(['email' => 'api@as-negocios.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'api@as-negocios.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        return [$user, $response->json('token')];
    }

    public function test_login_creates_a_token_with_the_explicit_api_ability(): void
    {
        [, $token] = $this->login();

        $tokenModel = PersonalAccessToken::query()->sole();
        [$id, $plain] = explode('|', $token, 2);

        $this->assertSame((int) $id, $tokenModel->id);
        $this->assertTrue(hash_equals($tokenModel->token, hash('sha256', $plain)));
        $this->assertSame(['api'], $tokenModel->abilities);
        $this->assertNull($tokenModel->expires_at);
    }

    public function test_fresh_token_can_access_me_and_dashboard(): void
    {
        [, $token] = $this->login();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/dashboard')
            ->assertOk();
    }

    public function test_unexpired_token_within_the_configured_window_remains_valid(): void
    {
        Carbon::setTestNow(now());

        [, $token] = $this->login();

        Carbon::setTestNow(now()->addMinutes(10));

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk();
    }

    public function test_expired_token_is_rejected(): void
    {
        Carbon::setTestNow(now());

        $this->assertSame(720, config('sanctum.expiration'));

        [, $token] = $this->login();

        $personalToken = PersonalAccessToken::query()->sole();
        $personalToken->forceFill([
            'created_at' => now()->subMinutes(config('sanctum.expiration') + 1),
        ])->save();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->withToken($token)
            ->getJson('/api/dashboard')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
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

    public function test_role_authorization_remains_enforced_for_valid_tokens(): void
    {
        $vendedor = User::factory()->seller()->create();
        $vendedorToken = $vendedor->createToken('api', ['api'])->plainTextToken;

        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('api', ['api'])->plainTextToken;

        $this->withToken($vendedorToken)
            ->getJson('/api/reports')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->withToken($adminToken)
            ->getJson('/api/reports')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($vendedorToken)
            ->getJson('/api/settings')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->withToken($adminToken)
            ->getJson('/api/settings')
            ->assertOk();
    }

    public function test_role_authorization_preserves_commercial_data_secrecy(): void
    {
        $product = Product::factory()->create([
            'name' => 'Costo Oculto Token',
            'sku' => 'COSTO-TOKEN',
            'sale_price' => 10,
            'production_cost' => 6,
            'stock' => 3,
        ]);

        $vendedor = User::factory()->seller()->create();
        $vendedorToken = $vendedor->createToken('api', ['api'])->plainTextToken;

        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('api', ['api'])->plainTextToken;

        $this->withToken($vendedorToken)
            ->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.production_cost')
            ->assertJsonMissingPath('data.stock_value');

        $this->app['auth']->forgetGuards();

        $this->withToken($adminToken)
            ->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.production_cost', 6);
    }

    public function test_seller_scoping_remains_enforced_for_valid_tokens(): void
    {
        $vendedor = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $mine = Sale::factory()->create(['seller_id' => $vendedor->id]);
        $theirs = Sale::factory()->create(['seller_id' => $other->id]);

        $vendedorToken = $vendedor->createToken('api', ['api'])->plainTextToken;

        $this->withToken($vendedorToken)
            ->getJson('/api/sales')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->withToken($vendedorToken)
            ->getJson("/api/sales/{$theirs->id}")
            ->assertNotFound();

        $this->app['auth']->forgetGuards();

        $encargadoTok = User::factory()->manager()->create()->createToken('api', ['api'])->plainTextToken;
        $this->withToken($encargadoTok)
            ->getJson('/api/sales')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_expired_tokens_are_pruned_by_both_mechanisms(): void
    {
        Carbon::setTestNow(now());

        $user = User::factory()->admin()->create();

        $fresh = $user->createToken('fresh');
        $fresh->accessToken->forceFill(['created_at' => now()])->save();

        $byExpiresAt = $user->createToken('by-expires_at');
        $byExpiresAt->accessToken->forceFill(['expires_at' => now()->subHours(30)])->save();

        $byConfigWindow = $user->createToken('by-config-window');
        $byConfigWindow->accessToken->forceFill([
            'created_at' => now()->subMinutes(config('sanctum.expiration') + (24 * 60) + 1),
        ])->save();

        $this->assertSame(3, PersonalAccessToken::count());

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

        $this->assertSame(['fresh'], PersonalAccessToken::pluck('name')->all());
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
