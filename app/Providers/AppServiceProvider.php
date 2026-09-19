<?php

namespace App\Providers;

use App\Support\LoginThrottle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(LoginThrottle::DECAY_MINUTES, LoginThrottle::MAX_ATTEMPTS)
                ->by(LoginThrottle::key($request));
        });
    }
}
