<x-layouts.app :title="$client->exists ? __('app.clients.edit_client') : __('app.clients.new_client')">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ $client->exists ? __('app.clients.edit_client') : __('app.clients.new_client') }}</h2>
        <x-button :href="route('clients.index')" variant="ghost" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
    </div>

    <x-card :title="__('app.clients.form_title')" :description="__('app.clients.form_desc')" class="mt-4">
        <form
            method="POST"
            action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}"
            class="grid gap-4 sm:grid-cols-2"
        >
            @csrf
            @if ($client->exists)
                @method('PUT')
            @endif

            <x-input name="name" :label="__('app.labels.name')" :value="$client->name" required />
            <x-input name="nit" :label="__('app.labels.nit')" :value="$client->nit" />

            <x-input name="email" type="email" :label="__('app.labels.email')" :value="$client->email" />
            <x-input name="phone" type="tel" :label="__('app.labels.phone')" :value="$client->phone" />

            <div class="sm:col-span-2">
                <x-textarea name="address" :label="__('app.labels.address')" rows="2">{{ $client->address }}</x-textarea>
            </div>

            <x-select
                name="preferred_language"
                :label="__('app.labels.preferred_language')"
                :selected="$client->preferred_language"
                :options="['es' => __('app.clients.language_es'), 'en' => __('app.clients.language_en')]"
            />

            <div class="sm:col-span-2">
                <x-textarea name="notes" :label="__('app.clients.notes')" rows="3">{{ $client->notes }}</x-textarea>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4 sm:col-span-2">
                <x-button :href="route('clients.index')" variant="secondary">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit" icon="check">{{ $client->exists ? __('app.actions.save_changes') : __('app.actions.save') }}</x-button>
            </div>
        </form>
    </x-card>

</x-layouts.app>