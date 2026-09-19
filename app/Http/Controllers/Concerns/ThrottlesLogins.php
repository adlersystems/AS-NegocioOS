<?php

namespace App\Http\Controllers\Concerns;

use App\Support\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

trait ThrottlesLogins
{
    protected function throttleLogin(Request $request): void
    {
        $key = LoginThrottle::key($request);

        if (RateLimiter::tooManyAttempts($key, LoginThrottle::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => [__('auth.throttle', ['seconds' => $seconds])],
            ])->status(429);
        }
    }

    protected function hitLoginThrottle(Request $request): void
    {
        RateLimiter::hit(LoginThrottle::key($request), LoginThrottle::DECAY_SECONDS);
    }

    protected function clearLoginThrottle(Request $request): void
    {
        RateLimiter::clear(LoginThrottle::key($request));
    }
}
