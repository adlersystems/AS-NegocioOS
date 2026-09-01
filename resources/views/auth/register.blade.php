<x-layouts.auth :title="__('auth.register_title')">

    <div class="rounded-2xl border border-border bg-surface p-6 shadow-card sm:p-8">
        <h2 class="text-xl font-bold text-on-surface">{{ __('auth.register_title') }}</h2>
        <p class="mt-1 text-sm text-on-surface-muted">{{ __('auth.register_subtitle') }}</p>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf

            <x-input
                name="name"
                :label="__('auth.name')"
                :value="old('name')"
                :placeholder="__('auth.name_placeholder')"
                required
                autofocus
                autocomplete="name"
            />

            <x-input
                name="email"
                :label="__('auth.email')"
                type="email"
                :value="old('email')"
                :placeholder="__('auth.email_placeholder')"
                required
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
                {{ __('auth.register_button') }}
            </x-button>
        </form>

        <p class="mt-6 text-center text-sm text-on-surface-muted">
            {{ __('auth.have_account') }}
            <a href="{{ route('login') }}" class="font-medium text-primary hover:text-primary-hover">
                {{ __('auth.login_link') }}
            </a>
        </p>
    </div>

</x-layouts.auth>