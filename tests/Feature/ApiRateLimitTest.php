<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiRateLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->app->setLocale('en');
    }

    private function assertThrottled(TestResponse $response, int $limit): void
    {
        $response->assertStatus(429);

        $retryAfter = (int) $response->headers->get('Retry-After');

        $this->assertSame((string) $limit, (string) $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', (string) $response->headers->get('X-RateLimit-Remaining'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Reset'));
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
        $this->assertSame(
            __('api.throttle', ['seconds' => $retryAfter]),
            $response->json('message'),
        );
    }

    public function test_general_api_limit_is_120_per_minute_per_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        $first = $this->getJson('/api/me')->assertOk();
        $this->assertSame('120', (string) $first->headers->get('X-RateLimit-Limit'));
        $this->assertSame('119', (string) $first->headers->get('X-RateLimit-Remaining'));

        for ($i = 1; $i < ApiRateLimit::GENERAL_PER_MINUTE; $i++) {
            $this->getJson('/api/me')->assertOk();
        }

        $this->assertThrottled($this->getJson('/api/me'), ApiRateLimit::GENERAL_PER_MINUTE);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/me')->assertOk();
    }

    public function test_dashboard_limit_is_60_per_minute_without_consuming_general_quota(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        for ($i = 0; $i < ApiRateLimit::DASHBOARD_PER_MINUTE; $i++) {
            $this->getJson('/api/dashboard')->assertOk();
        }

        $this->assertThrottled($this->getJson('/api/dashboard'), ApiRateLimit::DASHBOARD_PER_MINUTE);

        $this->getJson('/api/me')->assertOk();
    }

    public function test_reports_limit_is_10_per_minute_without_consuming_other_quotas(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        for ($i = 0; $i < ApiRateLimit::REPORTS_PER_MINUTE; $i++) {
            $this->getJson('/api/reports')->assertOk();
        }

        $this->assertThrottled($this->getJson('/api/reports'), ApiRateLimit::REPORTS_PER_MINUTE);

        $this->getJson('/api/me')->assertOk();
        $this->getJson('/api/dashboard')->assertOk();
    }

    public function test_forbidden_report_requests_do_not_consume_reports_quota(): void
    {
        $user = User::factory()->seller()->create();

        Sanctum::actingAs($user);

        for ($i = 0; $i < ApiRateLimit::REPORTS_PER_MINUTE + 1; $i++) {
            $this->getJson('/api/reports')->assertForbidden();
        }

        $user->update(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($user->refresh());

        for ($i = 0; $i < ApiRateLimit::REPORTS_PER_MINUTE; $i++) {
            $this->getJson('/api/reports')->assertOk();
        }

        $this->assertThrottled($this->getJson('/api/reports'), ApiRateLimit::REPORTS_PER_MINUTE);
    }

    public function test_api_login_limit_is_30_per_minute_per_ip(): void
    {
        for ($i = 0; $i < ApiRateLimit::LOGIN_PER_MINUTE; $i++) {
            $this->postJson('/api/auth/login', [])->assertUnprocessable();
        }

        $response = $this->postJson('/api/auth/login', []);

        $this->assertThrottled($response, ApiRateLimit::LOGIN_PER_MINUTE);
        $this->assertArrayNotHasKey('errors', $response->json());

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.51'])
            ->postJson('/api/auth/login', [])
            ->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/me')->assertOk();
    }

    public function test_api_login_throttle_response_is_localized_in_spanish(): void
    {
        $this->app->setLocale('es');

        for ($i = 0; $i < ApiRateLimit::LOGIN_PER_MINUTE; $i++) {
            $this->postJson('/api/auth/login', [])->assertUnprocessable();
        }

        $response = $this->postJson('/api/auth/login', [])->assertStatus(429);

        $this->assertStringContainsString('Demasiadas solicitudes', $response->json('message'));
        $this->assertStringContainsString('segundos', $response->json('message'));
    }

    public function test_named_api_limiters_are_registered(): void
    {
        foreach (['api', 'api-dashboard', 'api-reports', 'api-login'] as $name) {
            $this->assertIsCallable(RateLimiter::limiter($name));
        }
    }
}
