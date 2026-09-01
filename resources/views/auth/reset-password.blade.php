<x-layouts.auth :title="__('auth.reset_password_title')">

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-card sm:p-8">
        <h2 class="text-xl font-bold text-on-surface">{{ __('auth.reset_password_title') }}</h2>
        <p class="mt-1 text-sm text-on-surface-muted">{{ __('auth.reset_password_subtitle') }}</p>

        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <x-input
                name="email"
                :label="__('auth.email')"
                type="email"
                :value="old('email', $email)"
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
                autocomplete="new-password"
            />

            <x-input
                name="password_confirmation"
                :label="__('auth.confirm_password')"
                type="password"
                :placeholder="__('auth.confirm_password_placeholder')"
                required
                autocomplete="new-password"
            />

            <x-button type="submit" class="w-full" full>
                {{ __('auth.reset_password_button') }}
            </x-button>
        </form>
    </div>

</x-layouts.auth>