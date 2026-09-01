<x-layouts.app :title="$product->name">

    @php
        $margin = $product->margin();
        $marginHint = ($margin >= 0 ? '+' : '').number_format($marginRate, 1).' %';
        $movementTypes = ['in' => __('app.products.movement_in'), 'out' => __('app.products.movement_out')];
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-button :href="route('products.index')" variant="ghost" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
        <x-button :href="route('products.edit', $product)" variant="secondary" icon="pencil">{{ __('app.actions.edit') }}</x-button>
    </div>

    {{-- Status alerts --}}
    @if ($product->isExpired() || $product->isExpiringSoon() || $product->isLowStock())
        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if ($product->isExpired())
                <span class="inline-flex items-center gap-2 rounded-lg bg-danger-soft px-3 py-2 text-sm font-semibold text-danger">
                    <x-icon name="alert" class="h-4 w-4" /> {{ __('app.products.warn_expired') }}
                </span>
            @elseif ($product->isExpiringSoon())
                <span class="inline-flex items-center gap-2 rounded-lg bg-info-soft px-3 py-2 text-sm font-semibold text-info">
                    <x-icon name="calendar" class="h-4 w-4" /> {{ __('app.products.warn_expiring') }}
                </span>
            @endif
            @if ($product->isOutOfStock())
                <span class="inline-flex items-center gap-2 rounded-lg bg-danger-soft px-3 py-2 text-sm font-semibold text-danger">
                    <x-icon name="alert" class="h-4 w-4" /> {{ __('app.products.warn_out') }}
                </span>
            @elseif ($product->isLowStock())
                <span class="inline-flex items-center gap-2 rounded-lg bg-warning-soft px-3 py-2 text-sm font-semibold text-warning">
                    <x-icon name="alert" class="h-4 w-4" /> {{ __('app.products.warn_low') }}
                </span>
            @endif
        </div>
    @endif

    {{-- Profile --}}
    <x-card class="mt-4">
        <div class="flex flex-wrap items-start gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary">
                <x-icon name="box" class="h-8 w-8" />
            </div>

            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ $product->name }}</h2>
                <p class="mt-0.5 text-sm text-on-surface-muted">{{ $product->sku ?? __('app.labels.none') }}</p>
                <div class="mt-2">
                    <x-stock-badge :product="$product" />
                </div>
            </div>
        </div>

        @if ($product->description)
            <p class="mt-4 border-t border-border pt-4 text-sm text-on-surface-muted">{{ $product->description }}</p>
        @endif
    </x-card>

    {{-- Metrics --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <x-card>
            <x-metric :label="__('app.labels.sale_price')" :value="\App\Models\Setting::formatMoney($product->sale_price)" icon="sales" color="primary" />
        </x-card>
        <x-card>
            <x-metric :label="__('app.labels.production_cost')" :value="\App\Models\Setting::formatMoney($product->production_cost)" icon="coins" color="info" />
        </x-card>
        <x-card>
            <x-metric :label="__('app.products.margin')" :value="\App\Models\Setting::formatMoney($margin)" icon="chart" color="success" :hint="$marginHint" />
        </x-card>
        <x-card>
            <x-metric :label="__('app.labels.stock')" :value="number_format($product->stock)" icon="box" color="warning" :hint="__('app.labels.min_stock').': '.number_format($product->min_stock)" />
        </x-card>
        <x-card>
            <x-metric :label="__('app.products.stock_value')" :value="\App\Models\Setting::formatMoney($stockValue)" icon="coins" color="info" />
        </x-card>
        <x-card>
            <x-metric :label="__('app.products.units_sold')" :value="number_format($unitsSold)" icon="sales" color="primary" />
        </x-card>
    </div>

    {{-- Movements --}}
    <x-card :title="__('app.products.movements')" class="mt-4">
        @if ($movements->isEmpty())
            <x-empty-state :title="__('app.products.no_movements')" icon="inventory" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                            <th class="py-2 pr-4">{{ __('app.labels.type') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.quantity') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.reference') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.reason') }}</th>
                            <th class="py-2 pr-4">{{ __('app.dashboard.seller') }}</th>
                            <th class="py-2 text-right">{{ __('app.labels.date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($movements as $movement)
                            <tr>
                                <td class="py-3 pr-4">
                                    <x-badge :color="$movement->isIn() ? 'green' : 'red'">
                                        {{ $movementTypes[$movement->type] ?? $movement->type }}
                                    </x-badge>
                                </td>
                                <td class="py-3 pr-4 font-semibold text-on-surface">
                                    {{ $movement->isIn() ? '+' : '-' }}{{ number_format($movement->quantity) }}
                                </td>
                                <td class="py-3 pr-4 text-on-surface-muted">{{ $movement->reference ?? '—' }}</td>
                                <td class="py-3 pr-4 text-on-surface">{{ $movement->reason ?? '—' }}</td>
                                <td class="py-3 pr-4 text-on-surface">{{ $movement->user?->name ?? '—' }}</td>
                                <td class="py-3 text-right text-on-surface-muted">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

</x-layouts.app>