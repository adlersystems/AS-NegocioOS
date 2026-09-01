<x-layouts.app :title="$sale->exists ? __('app.sales.edit_sale') : __('app.sales.new_sale')">

    @php
        $hasItemsError = $errors->has('items') || $errors->has('items.*') || $errors->any();
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ $sale->exists ? __('app.sales.edit_sale') : __('app.sales.new_sale') }}</h2>
        <x-button :href="$sale->exists ? route('sales.show', $sale) : route('sales.index')" variant="ghost" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
    </div>

    <x-card
        :title="__('app.sales.form_title')"
        :description="__('app.sales.form_desc')"
        class="mt-4"
    >
        <form
            method="POST"
            action="{{ $sale->exists ? route('sales.update', $sale) : route('sales.store') }}"
            x-data="saleForm(@js([
                'products' => $products,
                'iva' => $iva,
                'items' => $sale->exists ? $sale->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'original_quantity' => $item->quantity,
                ])->values()->all() : [],
            ]))"
            class="space-y-5"
        >
            @csrf
            @if ($sale->exists)
                @method('PUT')
            @endif

            {{-- Sale data --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select
                    name="client_id"
                    :label="__('app.labels.name')"
                    :selected="$sale->client_id"
                    :options="$clients->pluck('name', 'id')->all()"
                    placeholder="{{ __('app.sales.no_invoice_client') }}"
                />

                <x-select
                    name="seller_id"
                    :label="__('app.labels.seller')"
                    :selected="$sale->seller_id ?? auth()->id()"
                    :options="$sellers->pluck('name', 'id')->all()"
                    required
                />

                <div class="sm:col-span-2">
                    <x-textarea name="notes" :label="__('app.labels.notes')" rows="2">{{ $sale->notes }}</x-textarea>
                </div>
            </div>

            {{-- Items --}}
            <div class="border-t border-border pt-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-on-surface">{{ __('app.sales.items') }}</h3>
                    <x-button type="button" variant="secondary" size="sm" icon="plus" @click="addItem()">{{ __('app.sales.add_item') }}</x-button>
                </div>

                @if ($errors->any())
                    <div class="mb-3 rounded-lg border border-danger/30 bg-danger-soft/50 px-4 py-3">
                        <ul class="list-inside list-disc space-y-1 text-sm text-danger">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <template x-for="(item, index) in items" :key="index">
                    <div class="grid grid-cols-2 items-end gap-3 rounded-lg border border-border p-3 sm:grid-cols-[1fr_100px_110px_110px_auto] sm:p-4">
                        {{-- Product --}}
                        <div class="col-span-2 sm:col-span-1">
                            <x-label>@lang('app.sales.product')</x-label>
                            <select
                                :name="`items[${index}][product_id]`"
                                x-model.number="item.product_id"
                                @change="productChanged(index)"
                                required
                                class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
                            >
                                <option value="">—</option>
                                <template x-for="p in products" :key="p.id">
                                    <option :value="p.id" x-text="productLabel(p)"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Quantity --}}
                        <div>
                            <x-label>@lang('app.labels.quantity')</x-label>
                            <input
                                type="number"
                                :name="`items[${index}][quantity]`"
                                x-model.number="item.quantity"
                                @change="quantityChanged(index)"
                                min="1"
                                :max="item.max_stock"
                                required
                                class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
                            >
                        </div>

                        {{-- Unit price --}}
                        <div>
                            <x-label>@lang('app.labels.unit_price')</x-label>
                            <div class="mt-1 truncate sm:px-3 sm:py-2.5">
                                <span class="text-sm font-semibold text-on-surface" x-text="formatMoney(item.unit_price)"></span>
                            </div>
                        </div>

                        {{-- Line total --}}
                        <div class="text-right">
                            <x-label>@lang('app.labels.total')</x-label>
                            <div class="mt-1 truncate sm:px-3 sm:py-2.5">
                                <span class="text-sm font-bold text-on-surface" x-text="formatMoney(item.line_total)"></span>
                            </div>
                        </div>

                        {{-- Remove --}}
                        <div class="flex justify-end">
                            <button
                                type="button"
                                @click="removeItem(index)"
                                class="rounded-lg p-2 text-on-surface-muted transition hover:bg-danger-soft hover:text-danger"
                                :title="'{{ __('app.sales.remove_item') }}'"
                            >
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </template>

                <div x-show="items.length === 0" x-cloak class="py-4 text-center text-sm text-on-surface-muted">
                    {{ __('app.sales.empty_items') }}
                </div>

                {{-- Totals --}}
                <div class="mt-4 flex flex-col items-end gap-1 border-t border-border pt-4 text-sm">
                    <div class="flex w-full max-w-xs items-center justify-between gap-4">
                        <span class="text-on-surface-muted">{{ __('app.labels.subtotal') }}</span>
                        <span class="font-semibold text-on-surface" x-text="formatMoney(subtotal)"></span>
                    </div>
                    <div class="flex w-full max-w-xs items-center justify-between gap-4">
                        <span class="text-on-surface-muted">{{ __('app.labels.tax') }} ({{ $iva }}%)</span>
                        <span class="text-on-surface" x-text="formatMoney(tax)"></span>
                    </div>
                    <div class="flex w-full max-w-xs items-center justify-between gap-4 border-t border-border pt-2">
                        <span class="font-semibold text-on-surface">{{ __('app.labels.total') }}</span>
                        <span class="text-lg font-bold text-primary" x-text="formatMoney(total)"></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4">
                <x-button :href="$sale->exists ? route('sales.show', $sale) : route('sales.index')" variant="secondary">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit" icon="check">{{ $sale->exists ? __('app.actions.save_changes') : __('app.actions.save') }}</x-button>
            </div>
        </form>
    </x-card>

</x-layouts.app>