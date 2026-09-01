<x-layouts.app :title="__('app.menu.sales')">

    @php
        $search = request('search');
        $sellerId = request('seller_id');
        $from = request('from');
        $to = request('to');
        $hasFilters = $search || $sellerId || $from || $to;
        $exportQuery = array_filter([
            'search' => $search,
            'seller_id' => $sellerId,
            'from' => $from,
            'to' => $to,
        ]);
    @endphp

    <div x-data="{ deleteSale: null }">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.menu.sales') }}</h2>
                <p class="text-sm text-on-surface-muted">{{ __('app.sales.list_desc') }}</p>
            </div>
            <x-button :href="route('sales.create')" icon="plus">{{ __('app.sales.new_sale') }}</x-button>
        </div>

        {{-- Toolbar --}}
        <x-card class="mt-6">
            <form method="GET" action="{{ route('sales.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative lg:col-span-2">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-muted">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="{{ __('app.sales.search_hint') }}"
                        class="w-full rounded-lg border border-border bg-surface py-2.5 pl-10 pr-4 text-sm text-on-surface outline-none transition placeholder:text-on-surface-muted focus:border-primary focus:ring-2 focus:ring-primary/30"
                    >
                </div>

                <x-select
                    name="seller_id"
                    :selected="$sellerId"
                    :options="$sellers->pluck('name', 'id')->prepend(__('app.sales.all_sellers'), '')->all()"
                />

                <div class="grid grid-cols-2 gap-3">
                    <x-input type="date" name="from" :label="__('app.sales.from_date')" :value="$from" />
                    <x-input type="date" name="to" :label="__('app.sales.to_date')" :value="$to" />
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                    <x-button type="submit" size="sm" icon="filter">{{ __('app.actions.apply') }}</x-button>
                    @if ($hasFilters)
                        <x-button :href="route('sales.index')" variant="ghost" size="sm" icon="close">{{ __('app.actions.reset') }}</x-button>
                    @endif

                    <span class="ml-auto flex items-center gap-2">
                        <x-button :href="route('sales.export.pdf', $exportQuery)" variant="secondary" size="sm" icon="download" onclick="window.showToast('success', '{{ __('app.flash.exported') }}')">{{ __('app.actions.export_pdf') }}</x-button>
                        <x-button :href="route('sales.export.excel', $exportQuery)" variant="secondary" size="sm" icon="download" onclick="window.showToast('success', '{{ __('app.flash.exported') }}')">{{ __('app.actions.export_excel') }}</x-button>
                    </span>
                </div>
            </form>
        </x-card>

        {{-- Sale list --}}
        <x-card class="mt-4">
            @if ($sales->isEmpty())
                @if ($hasFilters)
                    <x-empty-state :title="__('app.sales.no_results')" :description="__('app.sales.no_results_desc')" icon="sales" />
                @else
                    <x-empty-state :title="__('app.sales.no_sales')" :description="__('app.sales.no_sales_desc')" icon="sales" />
                @endif
            @else
                <div class="divide-y divide-border">
                    @foreach ($sales as $sale)
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 py-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-soft font-bold text-primary">
                                <x-icon name="receipt" class="h-5 w-5" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('sales.show', $sale) }}" class="block truncate text-sm font-semibold text-on-surface transition hover:text-primary">
                                    {{ $sale->invoiceNumber() }}
                                </a>
                                <p class="truncate text-xs text-on-surface-muted">
                                    {{ $sale->client?->name ?? __('app.sales.no_invoice_client') }}
                                    @if ($sale->client?->nit)
                                        <span class="mx-1">·</span>{{ $sale->client->nit }}
                                    @endif
                                    <span class="mx-1">·</span>{{ $sale->seller?->name ?? '—' }}
                                </p>
                            </div>

                            <span class="text-xs text-on-surface-muted">{{ $sale->created_at->format('d/m/Y H:i') }}</span>

                            <p class="text-sm font-bold text-on-surface">{{ \App\Models\Setting::formatMoney($sale->total) }}</p>

                            <div class="flex items-center gap-1">
                                <a
                                    href="{{ route('sales.show', $sale) }}"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                    title="{{ __('app.actions.view') }}"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('sales.edit', $sale) }}"
                                    class="rounded-lg p-2 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                                    title="{{ __('app.actions.edit') }}"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <button
                                    type="button"
                                    @click="deleteSale = @js(['name' => $sale->invoiceNumber(), 'url' => route('sales.destroy', $sale)])"
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
        @if ($sales->hasPages())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-on-surface-muted">
                    {{ __('app.pagination.showing', ['from' => $sales->firstItem(), 'to' => $sales->lastItem(), 'total' => $sales->total()]) }}
                </p>
                {{ $sales->links() }}
            </div>
        @endif

        {{-- Delete confirmation modal --}}
        <div
            x-show="deleteSale"
            x-transition.opacity.duration.200ms
            x-cloak
            class="fixed inset-0 z-40 bg-black/60"
            @click="deleteSale = null"
        ></div>

        <div
            x-show="deleteSale"
            x-transition.origin.top.scale.90.duration.200ms
            x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
        >
            <div class="w-full max-w-lg rounded-t-2xl border border-border bg-surface shadow-card-hover sm:rounded-2xl">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h3 class="text-base font-semibold text-on-surface">{{ __('app.sales.confirm_delete_title') }}</h3>
                    <button
                        type="button"
                        @click="deleteSale = null"
                        class="rounded-lg p-1.5 text-on-surface-muted transition hover:bg-surface-sunken hover:text-on-surface"
                    >
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <div class="px-5 py-4">
                    <p class="text-sm text-on-surface-muted">
                        {{ __('app.sales.confirm_delete_desc') }}
                        <span class="font-semibold text-on-surface" x-text="deleteSale && deleteSale.name"></span>.
                        {{ __('app.actions.confirm_delete_desc') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4">
                    <x-button variant="secondary" @click="deleteSale = null">{{ __('app.actions.cancel') }}</x-button>

                    <form method="POST" :action="deleteSale ? deleteSale.url : ''">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="trash">{{ __('app.actions.yes_delete') }}</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-layouts.app>