<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('en');
        Setting::set('default_language', 'en');
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

    private function emailError(TestResponse $response): string
    {
        $errors = $response->getSession()->get('errors');

        if ($errors instanceof ViewErrorBag || $errors instanceof MessageBag) {
            return (string) $errors->first('email');
        }

        if (is_array($errors)) {
            foreach ($errors as $bag) {
                if (isset($bag['messages']['email'][0])) {
                    return (string) $bag['messages']['email'][0];
                }
            }
        }

        return '';
    }

    private function loginRequest(string $email): Request
    {
        return Request::create('/login', 'POST', ['email' => $email]);
    }

    public function test_five_failed_attempts_are_allowed(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), $this->wrongCredentials($user))
                ->assertSessionHasErrors(['email' => __('auth.failed')]);

            $this->assertGuest();
        }
    }

    public function test_sixth_failed_attempt_is_throttled(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), $this->wrongCredentials($user));
        }

        $response = $this->post(route('login'), $this->wrongCredentials($user));

        $response->assertSessionHasErrors('email');
        $this->assertNotSame(__('auth.failed'), $this->emailError($response));
        $this->assertMatchesRegularExpression('/in \d+ seconds\.$/', $this->emailError($response));
        $this->assertGuest();
    }

    public function test_throttle_message_includes_retry_seconds(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), $this->wrongCredentials($user));
        }

        $response = $this->post(route('login'), $this->wrongCredentials($user));

        $message = $this->emailError($response);
        $this->assertMatchesRegularExpression('/\d+/', $message);
        $this->assertStringContainsString('seconds', $message);
    }

    public function test_successful_login_clears_limiter(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login'), $this->wrongCredentials($user));
        }

        $this->post(route('login'), $this->correctCredentials($user))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $key = LoginThrottle::key($this->loginRequest($user->email));
        $this->assertSame(0, RateLimiter::attempts($key));

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login'), $this->wrongCredentials($user))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_successful_logins_do_not_consume_attempts(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), $this->correctCredentials($user))
                ->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);

            $this->post(route('logout'));
            $this->assertGuest();
        }

        $this->post(route('login'), $this->wrongCredentials($user))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_same_ip_different_email_remains_independent(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), $this->wrongCredentials($userA));
        }

        $this->post(route('login'), $this->correctCredentials($userB))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($userB);
    }

    public function test_same_email_different_ip_remains_independent(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), $this->wrongCredentials($user));
        }

        $response = $this->post(route('login'), $this->wrongCredentials($user));
        $response->assertSessionHasErrors('email');
        $this->assertMatchesRegularExpression('/in \d+ seconds\.$/', $this->emailError($response));

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->post(route('login'), $this->correctCredentials($user))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_malformed_validation_requests_do_not_consume_attempts(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login'), ['email' => $user->email])
                ->assertSessionHasErrors('password');
        }

        $this->post(route('login'), $this->correctCredentials($user))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
