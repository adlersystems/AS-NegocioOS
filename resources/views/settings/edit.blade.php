<x-layouts.app :title="__('app.settings.title')">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.settings.title') }}</h2>
            <p class="mt-1 text-sm text-on-surface-muted">{{ __('app.settings.page_desc') }}</p>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('settings.update') }}"
        enctype="multipart/form-data"
        class="mt-6 space-y-6"
    >
        @csrf

        {{-- Company details --}}
        <x-card :title="__('app.settings.company_section')" :description="__('app.settings.company_section_desc')">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input name="company_name" :label="__('app.settings.company_name')" :value="old('company_name', $settings['company_name'] ?? '')" required />
                </div>

                <div class="sm:col-span-2">
                    <x-input name="tagline" :label="__('app.settings.tagline')" :value="old('tagline', $settings['tagline'] ?? '')" />
                    <p class="mt-1 text-xs text-on-surface-muted">{{ __('app.settings.tagline_desc') }}</p>
                </div>

                <div class="sm:col-span-2">
                    <x-label for="logo">{{ __('app.settings.logo') }}</x-label>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        @if ($logoUrl = \App\Models\Setting::logoUrl())
                            <img
                                src="{{ $logoUrl }}"
                                alt="{{ __('app.settings.logo_current') }}"
                                class="h-16 w-16 rounded-xl border border-border bg-surface-sunken object-contain p-1"
                            >
                        @else
                            <div class="flex h-16 w-16 items-center justify-center rounded-xl border border-dashed border-border bg-surface-sunken">
                                <x-icon name="settings" class="h-6 w-6 text-on-surface-muted" />
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <input
                                type="file"
                                id="logo"
                                name="logo"
                                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                @class([
                                    'block w-full text-sm text-on-surface file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary',
                                    'border' => false,
                                ])
                            >
                            <p class="mt-1 text-xs text-on-surface-muted">{{ __('app.settings.logo_desc') }}</p>
                            <x-error name="logo" />
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Contact --}}
        <x-card :title="__('app.settings.contact_section')" :description="__('app.settings.contact_section_desc')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="nit" :label="__('app.labels.nit')" :value="old('nit', $settings['nit'] ?? '')" />
                <x-input name="email" type="email" :label="__('app.labels.email')" :value="old('email', $settings['email'] ?? '')" />
                <x-input name="phone" type="tel" :label="__('app.labels.phone')" :value="old('phone', $settings['phone'] ?? '')" />
                <div class="sm:col-span-2">
                    <x-textarea name="address" :label="__('app.labels.address')" rows="2">{{ old('address', $settings['address'] ?? '') }}</x-textarea>
                </div>
            </div>
        </x-card>

        {{-- Preferences --}}
        <x-card :title="__('app.settings.preferences_section')" :description="__('app.settings.preferences_section_desc')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-select
                    name="currency"
                    :label="__('app.settings.currency')"
                    :selected="old('currency', $settings['currency'] ?? 'GTQ')"
                    :options="[
                        'GTQ' => __('app.currency.gtq'),
                        'USD' => __('app.currency.usd'),
                    ]"
                    required
                />
                <div>
                    <x-input name="iva_percentage" type="number" step="0.01" min="0" max="100" :label="__('app.settings.iva_percentage')" :value="old('iva_percentage', $settings['iva_percentage'] ?? '12')" required />
                    <p class="mt-1 text-xs text-on-surface-muted">{{ __('app.settings.iva_desc') }}</p>
                </div>
                <x-select
                    name="default_language"
                    :label="__('app.settings.default_language')"
                    :selected="old('default_language', $settings['default_language'] ?? 'es')"
                    :options="[
                        'es' => __('app.settings.language_es'),
                        'en' => __('app.settings.language_en'),
                    ]"
                    required
                />
            </div>
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-button type="submit" icon="check">{{ __('app.actions.save_changes') }}</x-button>
        </div>
    </form>

</x-layouts.app>