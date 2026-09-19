<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiAuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('en');
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);
    }

    private function wrongCredentials(User $user): array
    {
        return [
            'email' => $user->email,
            'password' => 'wrong-password',
        ];
    }

    private function correctCredentials(User $user): array
    {
        return [
            'email' => $user->email,
            'password' => 'secret-password',
        ];
    }

    public function test_five_failed_attempts_are_allowed(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $this->wrongCredentials($user))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email')
                ->assertJsonPath('errors.email.0', __('auth.failed'));
        }
    }

    public function test_sixth_failed_attempt_returns_429(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $this->wrongCredentials($user));
        }

        $response = $this->postJson('/api/auth/login', $this->wrongCredentials($user));

        $response->assertStatus(429)
            ->assertJsonValidationErrors('email');
        $this->assertMatchesRegularExpression('/in \d+ seconds\.$/', $response->json('errors.email.0'));
    }

    public function test_successful_login_clears_limiter(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', $this->wrongCredentials($user));
        }

        $this->postJson('/api/auth/login', $this->correctCredentials($user))
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $this->postJson('/api/auth/login', $this->wrongCredentials($user))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed'));
    }

    public function test_same_ip_different_email_remains_independent(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $this->wrongCredentials($userA));
        }

        $this->postJson('/api/auth/login', $this->correctCredentials($userB))
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_same_email_different_ip_remains_independent(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $this->wrongCredentials($user));
        }

        $throttled = $this->postJson('/api/auth/login', $this->wrongCredentials($user));
        $throttled->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->postJson('/api/auth/login', $this->correctCredentials($user))
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_malformed_validation_requests_do_not_consume_attempts(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => $user->email])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }

        $this->postJson('/api/auth/login', $this->correctCredentials($user))
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_named_login_limiter_is_registered(): void
    {
        $this->assertIsCallable(RateLimiter::limiter('login'));
    }
}
