<?php

namespace App\Support;

use Illuminate\Http\Request;

final class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_MINUTES = 5;

    public const DECAY_SECONDS = self::DECAY_MINUTES * 60;

    public static function key(Request $request): string
    {
        return strtolower((string) $request->string('email')).'|'.$request->ip();
    }
}
