<x-layouts.app :title="__('app.menu.products')">

    @php
        $search = request('search');
        $status = request('status');
        $stock = request('stock');
    @endphp

    <div x-data="{ deleteProduct: null }">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.menu.products') }}</h2>
                <p class="text-sm text-on-surface-muted">{{ __('app.products.list_desc') }}</p>
            </div>
            <x-button :href="route('products.create')" icon="plus">{{ __('app.products.new_product') }}</x-button>
        </div>

        <div class="divider">&nbsp;</div>

        {{-- Toolbar --}}
        <x-card class="mt-6">
            <form method="GET" action="{{ route('products.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative lg:col-span-2">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-muted">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="{{ __('app.products.search_hint') }}"
                        class="w-full rounded-lg border border-border bg-surface py-2.5 pl-10 pr-4 text-sm text-on-surface outline-none transition placeholder:text-on-surface-muted focus:border-primary focus:ring-2 focus:ring-primary/30"
                    >
                </div>

                <x-select
                    name="status"
                    :selected="$status"
                    :options="[
                        '' => __('app.products.all_statuses'),
                        'active' => __('app.products.active'),
                        'inactive' => __('app.products.inactive'),
                    ]"
                />

                <x-select
                    name="stock"
                    :selected="$stock"
                    :options="[
                        '' => __('app.products.all_stock'),
                        'low' => __('app.products.status_low'),
                        'out' => __('app.products.status_out'),
                        'expiring' => __('app.products.status_expiring'),
                    ]"
                />

                <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                    <x-button type="submit" size="sm" icon="filter">{{ __('app.actions.apply') }}</x-button>
                    @if ($search || $status || $stock)
                        <x-button :href="route('products.index')" variant="ghost" size="sm" icon="close">{{ __('app.actions.reset') }}</x-button>
                    @endif
                </div>
            </form>
        </x-card>

        <div class="divider">&nbsp;</div>

        {{-- Product list --}}
        <x-card class="mt-4">
            @if ($products->isEmpty())
                @if ($search || $status || $stock)
                    <x-empty-state :title="__('app.products.no_results')" :description="__('app.products.no_results_desc')" icon="products" />
                @else
                    <x-empty-state :title="__('app.products.no_products')" :description="__('app.products.no_products_desc')" icon="products" />
                @endif
            @else
                <div class="divide-y divide-border">
                    @foreach ($products as $product)
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 py-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-soft font-bold text-primary">
                                <x-icon name="box" class="h-5 w-5" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('products.show', $product) }}" class="block truncate text-sm font-semibold text-on-surface transition hover:text-primary">
                                    {{ $product->name }}
                                </a>
                                <p class="truncate text-xs text-on-surface-muted">{{ $product->sku ?? __('app.labels.none') }}</p>
                            </div>

                            <x-stock-badge :product="$product" />

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-on-surface-muted">
                                <span class="inline-flex items-center gap-1">
                                    <x-icon name="box" class="h-4 w-4" />
                                    {{ number_format($product->stock) }} {{ __('app.labels.stock') }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <x-icon name="sales" class="h-4 w-4" />
                                    {{ number_format($product->sale_items_sum_quantity ?? 0) }} {{ __('app.products.units_sold') }}
                                </span>
                            </div>

                            <p class="text-sm font-bold text-on-surface">{{ \App\Models\Setting::formatMoney($product->sale_price) }}</p>

                            <div class="flex items-center gap-1">
                                <a
                                    href="{{ route('products.show', $product) }}"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                    title="{{ __('app.actions.view') }}"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('products.edit', $product) }}"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                    title="{{ __('app.actions.edit') }}"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <button
                                    type="button"
                                    @click="deleteProduct = @js(['name' => $product->name, 'url' => route('products.destroy', $product)])"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-danger-soft hover:text-danger"
                                    title="{{ __('app.actions.delete') }}"
                                >
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Pagination --}}
        @if ($products->hasPages())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-on-surface-muted">
                    {{ __('app.pagination.showing', ['from' => $products->firstItem(), 'to' => $products->lastItem(), 'total' => $products->total()]) }}
                </p>
                {{ $products->links() }}
            </div>
        @endif

        {{-- Delete confirmation modal --}}
        <div
            x-show="deleteProduct"
            x-transition.opacity.duration.200ms
            x-cloak
            class="fixed inset-0 z-40 bg-black/60"
            @click="deleteProduct = null"
        ></div>

        <div
            x-show="deleteProduct"
            x-transition.origin.top.scale.90.duration.200ms
            x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
        >
            <div class="w-full max-w-lg rounded-t-2xl border border-border bg-surface shadow-card-hover sm:rounded-2xl">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h3 class="text-base font-semibold text-on-surface">{{ __('app.products.confirm_delete_title') }}</h3>
                    <button
                        type="button"
                        @click="deleteProduct = null"
                        class="rounded-lg p-1.5 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                    >
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <div class="px-5 py-4">
                    <p class="text-sm text-on-surface-muted">
                        {{ __('app.products.confirm_delete_desc') }}
                        <span class="font-semibold text-on-surface" x-text="deleteProduct && deleteProduct.name"></span>.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4">
                    <x-button variant="secondary" @click="deleteProduct = null">{{ __('app.actions.cancel') }}</x-button>

                    <form method="POST" :action="deleteProduct ? deleteProduct.url : ''">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="trash">{{ __('app.actions.yes_delete') }}</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-layouts.app>