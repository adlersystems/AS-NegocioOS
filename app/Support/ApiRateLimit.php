<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

final class ApiRateLimit
{
    public const GENERAL_PER_MINUTE = 120;

    public const DASHBOARD_PER_MINUTE = 60;

    public const REPORTS_PER_MINUTE = 10;

    public const LOGIN_PER_MINUTE = 30;

    public static function limit(Request $request, string $scope, int $maxAttempts, bool $byUser = true): Limit
    {
        return Limit::perMinute($maxAttempts)
            ->by($byUser ? self::userKey($request, $scope) : self::ipKey($request, $scope))
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => __('api.throttle', [
                    'seconds' => (int) ($headers['Retry-After'] ?? 60),
                ]),
            ], 429, $headers));
    }

    public static function userKey(Request $request, string $scope): string
    {
        $user = $request->user();

        if ($user !== null) {
            return $scope.':user:'.$user->getAuthIdentifier();
        }

        return self::ipKey($request, $scope);
    }

    public static function ipKey(Request $request, string $scope): string
    {
        return $scope.':ip:'.$request->ip();
    }
}
