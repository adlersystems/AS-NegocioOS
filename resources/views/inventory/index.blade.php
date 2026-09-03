<x-layouts.app :title="__('app.menu.inventory')">

    @php
        $search = request('search');
        $type = request('type');
        $from = request('from');
        $to = request('to');
        $hasFilters = $search || $type || $from || $to;
        $exportQuery = array_filter([
            'search' => $search,
            'type' => $type,
            'from' => $from,
            'to' => $to,
        ]);
        $movementTypes = [\App\Models\InventoryMovement::TYPE_IN => __('app.inventory.type_in'), \App\Models\InventoryMovement::TYPE_OUT => __('app.inventory.type_out')];
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.menu.inventory') }}</h2>
            <p class="text-sm text-on-surface-muted">{{ __('app.inventory.list_desc') }}</p>
        </div>
        <x-button :href="route('inventory.create')" icon="plus">{{ __('app.inventory.new_movement') }}</x-button>
    </div>

    {{-- Metrics --}}
    <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-card><x-metric :label="__('app.inventory.entries')" :value="number_format($entries)" icon="arrow-down" color="success" /></x-card>
        <x-card><x-metric :label="__('app.inventory.exits')" :value="number_format($exits)" icon="arrow-up" color="danger" /></x-card>
        @if ($lowStock > 0)
            <a href="{{ route('products.index', ['stock' => 'low']) }}" class="transition hover:-translate-y-0.5">
                <x-card class="h-full"><x-metric :label="__('app.inventory.low_stock')" :value="number_format($lowStock)" icon="alert" color="warning" :hint="__('app.inventory.view_products')" /></x-card>
            </a>
        @else
            <x-card><x-metric :label="__('app.inventory.low_stock')" :value="number_format($lowStock)" icon="alert" color="warning" /></x-card>
        @endif
        @if ($outOfStock > 0)
            <a href="{{ route('products.index', ['stock' => 'out']) }}" class="transition hover:-translate-y-0.5">
                <x-card class="h-full"><x-metric :label="__('app.inventory.out_of_stock')" :value="number_format($outOfStock)" icon="alert" color="danger" :hint="__('app.inventory.view_products')" /></x-card>
            </a>
        @else
            <x-card><x-metric :label="__('app.inventory.out_of_stock')" :value="number_format($outOfStock)" icon="alert" color="danger" /></x-card>
        @endif
    </div>

    <div class="divider">&nbsp;</div>

    {{-- Toolbar --}}
    <x-card class="mt-4">
        <form method="GET" action="{{ route('inventory.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative lg:col-span-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-on-surface-muted">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('app.inventory.search_hint') }}"
                    class="w-full rounded-lg border border-border bg-surface py-2.5 pl-10 pr-4 text-sm text-on-surface outline-none transition placeholder:text-on-surface-muted focus:border-primary focus:ring-2 focus:ring-primary/30"
                >
            </div>

            <x-select
                name="type"
                :selected="$type"
                :options="[
                    '' => __('app.inventory.all_types'),
                    \App\Models\InventoryMovement::TYPE_IN => __('app.inventory.type_in'),
                    \App\Models\InventoryMovement::TYPE_OUT => __('app.inventory.type_out'),
                ]"
            />

            <div class="grid grid-cols-2 gap-3">
                <x-input type="date" name="from" :label="__('app.sales.from_date')" :value="$from" />
                <x-input type="date" name="to" :label="__('app.sales.to_date')" :value="$to" />
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                <x-button type="submit" size="sm" icon="filter">{{ __('app.actions.apply') }}</x-button>
                @if ($hasFilters)
                    <x-button :href="route('inventory.index')" variant="ghost" size="sm" icon="close">{{ __('app.actions.reset') }}</x-button>
                @endif

                <span class="ml-auto flex items-center gap-2">
                    <x-button :href="route('inventory.export.pdf', $exportQuery)" variant="secondary" size="sm" icon="download" onclick="window.showToast('success', '{{ __('app.flash.exported') }}')">{{ __('app.actions.export_pdf') }}</x-button>
                    <x-button :href="route('inventory.export.excel', $exportQuery)" variant="secondary" size="sm" icon="download" onclick="window.showToast('success', '{{ __('app.flash.exported') }}')">{{ __('app.actions.export_excel') }}</x-button>
                </span>
            </div>
        </form>
    </x-card>

    <div class="divider">&nbsp;</div>

    {{-- Movements ledger --}}
    <x-card class="mt-4">
        @if ($movements->isEmpty())
            @if ($hasFilters)
                <x-empty-state :title="__('app.inventory.no_results')" :description="__('app.inventory.no_results_desc')" icon="inventory" />
            @else
                <x-empty-state :title="__('app.inventory.no_movements')" :description="__('app.inventory.no_movements_desc')" icon="inventory" />
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                            <th class="py-2 pr-4">{{ __('app.labels.date') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.name') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.type') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.quantity') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.reason') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.reference') }}</th>
                            <th class="py-2 pr-4">{{ __('app.dashboard.seller') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($movements as $movement)
                            <tr>
                                <td class="whitespace-nowrap py-3 pr-4 text-on-surface-muted">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-3 pr-4">
                                    <a href="{{ route('products.show', $movement->product_id) }}" class="font-semibold text-on-surface transition hover:text-primary">
                                        {{ $movement->product?->name ?? __('app.labels.none') }}
                                    </a>
                                    @if ($movement->product?->sku)
                                        <span class="block text-xs text-on-surface-muted">{{ $movement->product->sku }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    <x-badge :color="$movement->isIn() ? 'green' : 'red'">
                                        {{ $movementTypes[$movement->type] ?? $movement->type }}
                                    </x-badge>
                                </td>
                                <td class="py-3 pr-4 font-semibold text-on-surface">
                                    {{ $movement->isIn() ? '+' : '-' }}{{ number_format($movement->quantity) }}
                                </td>
                                <td class="py-3 pr-4 text-on-surface">{{ $movement->reason ?? '—' }}</td>
                                <td class="py-3 pr-4 text-on-surface-muted">{{ $movement->reference ?? '—' }}</td>
                                <td class="py-3 text-on-surface">{{ $movement->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    {{-- Pagination --}}
    @if ($movements->hasPages())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-on-surface-muted">
                {{ __('app.pagination.showing', ['from' => $movements->firstItem(), 'to' => $movements->lastItem(), 'total' => $movements->total()]) }}
            </p>
            {{ $movements->links() }}
        </div>
    @endif

</x-layouts.app>