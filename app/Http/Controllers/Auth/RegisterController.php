<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Registration is only available before the first account exists.
     * The first registered user is promoted to administrator.
     */
    public function showRegistrationForm(): View|RedirectResponse
    {
        if (User::exists()) {
            return redirect()->route('login')
                ->with('error', __('auth.registration_closed'));
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        if (User::exists()) {
            return redirect()->route('login')
                ->with('error', __('auth.registration_closed'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_ADMIN,
            'language' => app()->getLocale(),
        ]);

        Auth::login($user, true);

        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', __('auth.registration_success'));
    }
}
