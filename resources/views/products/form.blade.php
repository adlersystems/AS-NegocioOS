<x-layouts.app :title="$product->exists ? __('app.products.edit_product') : __('app.products.new_product')">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ $product->exists ? __('app.products.edit_product') : __('app.products.new_product') }}</h2>
        <x-button :href="route('products.index')" variant="ghost" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
    </div>

    <x-card :title="__('app.products.form_title')" :description="__('app.products.form_desc')" class="mt-4">
        <form
            method="POST"
            action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}"
            class="grid gap-4 sm:grid-cols-2"
        >
            @csrf
            @if ($product->exists)
                @method('PUT')
            @endif

            <x-input name="name" :label="__('app.labels.name')" :value="$product->name" required />
            <x-input name="sku" :label="__('app.labels.sku')" :value="$product->sku" />

            <x-input name="production_cost" type="number" step="0.01" min="0" :label="__('app.labels.production_cost')" :value="$product->production_cost" prepend="Q" required />
            <x-input name="sale_price" type="number" step="0.01" min="0" :label="__('app.labels.sale_price')" :value="$product->sale_price" prepend="Q" required />

            <x-input name="stock" type="number" step="1" min="0" :label="__('app.labels.stock')" :value="$product->stock" required />
            <x-input name="min_stock" type="number" step="1" min="0" :label="__('app.labels.min_stock')" :value="$product->min_stock" required />

            <x-input name="expiration_date" type="date" :label="__('app.labels.expiration_date')" :value="$product->expiration_date?->format('Y-m-d')" />

            <label class="flex items-center gap-3 self-end rounded-lg border border-border bg-surface px-4 py-3">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    class="h-4 w-4 rounded border-border text-primary focus:ring-primary/40"
                    @checked(old('is_active', $product->is_active ?? true))
                >
                <span class="text-sm font-medium text-on-surface">{{ __('app.labels.is_active') }}</span>
            </label>

            <div class="sm:col-span-2">
                <x-textarea name="description" :label="__('app.labels.description')" rows="3">{{ $product->description }}</x-textarea>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4 sm:col-span-2">
                <x-button :href="route('products.index')" variant="secondary">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit" icon="check">{{ $product->exists ? __('app.actions.save_changes') : __('app.actions.save') }}</x-button>
            </div>
        </form>
    </x-card>

</x-layouts.app>