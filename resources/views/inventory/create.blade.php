<x-layouts.app :title="__('app.inventory.new_movement')">

    @php
        $productOptions = $products->mapWithKeys(fn ($product) => [
            $product->id => $product->name.($product->sku ? " ({$product->sku})" : ''),
        ])->all();
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.inventory.new_movement') }}</h2>
        <x-button :href="route('inventory.index')" variant="ghost" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
    </div>

    <x-card :title="__('app.inventory.form_title')" :description="__('app.inventory.form_desc')" class="mt-4">
        <form method="POST" action="{{ route('inventory.store') }}" class="grid gap-4 sm:grid-cols-2">
            @csrf

            <div class="sm:col-span-2">
                <x-select
                    name="product_id"
                    :label="__('app.labels.name')"
                    :options="$productOptions"
                    :selected="old('product_id')"
                    required
                />
            </div>

            <x-select
                name="type"
                :label="__('app.labels.type')"
                :options="[
                    \App\Models\InventoryMovement::TYPE_IN => __('app.inventory.type_in'),
                    \App\Models\InventoryMovement::TYPE_OUT => __('app.inventory.type_out'),
                ]"
                :selected="old('type', \App\Models\InventoryMovement::TYPE_IN)"
                required
            />

            <x-input name="quantity" type="number" min="1" step="1" :label="__('app.labels.quantity')" :value="old('quantity')" required />

            <x-input name="reason" :label="__('app.labels.reason')" :value="old('reason')" required />
            <x-input name="reference" :label="__('app.labels.reference')" :value="old('reference')" />

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4 sm:col-span-2">
                <x-button :href="route('inventory.index')" variant="secondary">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit" icon="check">{{ __('app.actions.save') }}</x-button>
            </div>
        </form>
    </x-card>

</x-layouts.app>