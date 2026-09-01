<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
    /**
     * Switch the application locale and persist it for the session/cookie.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['es', 'en'], true)) {
            abort(404);
        }

        session(['locale' => $locale]);

        if ($request->user()) {
            $request->user()->update(['language' => $locale]);
        }

        App::setLocale($locale);

        return back()
            ->with('success', __('app.flash.language_switched'))
            ->cookie('locale', $locale, 60 * 24 * 365);
    }
}
