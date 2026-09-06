<x-layouts.auth :title="__('auth.login_title')">

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-card sm:p-8">
        <h2 class="text-xl font-bold text-on-surface">{{ __('auth.login_title') }}</h2>
        <p class="mt-1 text-sm text-on-surface-muted">{{ __('auth.login_subtitle') }}</p>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf

            <x-input
                name="email"
                :label="__('auth.email')"
                type="email"
                :value="old('email')"
                :placeholder="__('auth.email_placeholder')"
                required
                autofocus
                autocomplete="email"
            />

            <x-input
                name="password"
                :label="__('auth.password')"
                type="password"
                :placeholder="__('auth.password_placeholder')"
                required
                autocomplete="current-password"
            />

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-on-surface-muted">
                    <input
                        type="checkbox"
                        name="remember"
                        @class([
                            'h-4 w-4 rounded border-border bg-surface text-primary accent-primary',
                        ])
                    >
                    {{ __('auth.remember_me') }}
                </label>

                <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:text-primary-hover">
                    {{ __('auth.forgot_password') }}
                </a>
            </div>

            <x-button type="submit" class="w-full" full>
                {{ __('auth.login_button') }}
            </x-button>
        </form>
    </div>

</x-layouts.auth>