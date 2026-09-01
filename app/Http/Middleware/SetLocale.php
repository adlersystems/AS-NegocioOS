<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the request locale from session, cookie, user preference or config.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userLocale = $request->user()?->language;

        $locale = session('locale')
            ?? $request->cookie('locale')
            ?? $userLocale
            ?? config('app.locale');

        if (in_array($locale, ['es', 'en'], true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
