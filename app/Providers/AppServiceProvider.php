<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ApiRateLimit;
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

        RateLimiter::for('api', fn (Request $request) => ApiRateLimit::limit(
            $request,
            'api',
            ApiRateLimit::GENERAL_PER_MINUTE,
        ));

        RateLimiter::for('api-dashboard', fn (Request $request) => ApiRateLimit::limit(
            $request,
            'api-dashboard',
            ApiRateLimit::DASHBOARD_PER_MINUTE,
        ));

        RateLimiter::for('api-reports', function (Request $request) {
            $user = $request->user();

            if (! $user || ! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_MANAGER], true)) {
                return Limit::none();
            }

            return ApiRateLimit::limit($request, 'api-reports', ApiRateLimit::REPORTS_PER_MINUTE);
        });

        RateLimiter::for('api-login', fn (Request $request) => ApiRateLimit::limit(
            $request,
            'api-login',
            ApiRateLimit::LOGIN_PER_MINUTE,
            byUser: false,
        ));
    }
}
