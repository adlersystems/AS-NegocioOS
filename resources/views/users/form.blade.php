<x-layouts.app :title="$user->exists ? __('app.users.edit_user') : __('app.users.new_user')">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ $user->exists ? __('app.users.edit_user') : __('app.users.new_user') }}</h2>
        <x-button :href="route('users.index')" variant="ghost" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
    </div>

    <x-card :title="__('app.users.form_title')" :description="__('app.users.form_desc')" class="mt-4">
        <form
            method="POST"
            action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
            class="grid gap-4 sm:grid-cols-2"
        >
            @csrf
            @if ($user->exists)
                @method('PUT')
            @endif

            <x-input name="name" :label="__('app.labels.name')" :value="$user->name" required />
            <x-input name="email" type="email" :label="__('app.labels.email')" :value="$user->email" required />

            <x-select
                name="role"
                :label="__('app.users.role')"
                :selected="$user->role"
                :options="[
                    'admin' => __('app.roles.admin'),
                    'vendedor' => __('app.roles.vendedor'),
                    'encargado' => __('app.roles.encargado'),
                ]"
                required
            />

            <x-select
                name="language"
                :label="__('app.users.language')"
                :selected="$user->language"
                :options="['es' => __('app.users.language_es'), 'en' => __('app.users.language_en')]"
                required
            />

            <div class="sm:col-span-2">
                <x-input
                    name="password"
                    type="password"
                    :label="__('app.users.password')"
                    :required="! $user->exists"
                />
                @if ($user->exists)
                    <p class="mt-1 text-xs text-on-surface-muted">{{ __('app.users.password_hint') }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4 sm:col-span-2">
                <x-button :href="route('users.index')" variant="secondary">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit" icon="check">{{ $user->exists ? __('app.actions.save_changes') : __('app.actions.save') }}</x-button>
            </div>
        </form>
    </x-card>

</x-layouts.app>
