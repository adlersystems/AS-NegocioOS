<x-layouts.auth :title="__('auth.forgot_password_title')">

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-card sm:p-8">
        <h2 class="text-xl font-bold text-on-surface">{{ __('auth.forgot_password_title') }}</h2>
        <p class="mt-1 text-sm text-on-surface-muted">{{ __('auth.forgot_password_subtitle') }}</p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
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

            <x-button type="submit" class="w-full" full>
                {{ __('auth.send_reset_link') }}
            </x-button>
        </form>

        <p class="mt-6 text-center text-sm text-on-surface-muted">
            <a href="{{ route('login') }}" class="font-medium text-primary hover:text-primary-hover">
                {{ __('auth.back_to_login') }}
            </a>
        </p>
    </div>

</x-layouts.auth>