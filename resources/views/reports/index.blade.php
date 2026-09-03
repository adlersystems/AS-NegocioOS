<x-layouts.app :title="__('app.menu.reports')">

    @php
        $reportTypes = [
            ['type' => 'sales', 'label' => __('app.reports.type_sales'), 'icon' => 'sales'],
            ['type' => 'inventory', 'label' => __('app.reports.type_inventory'), 'icon' => 'inventory'],
            ['type' => 'clients', 'label' => __('app.reports.type_clients'), 'icon' => 'clients'],
            ['type' => 'products', 'label' => __('app.reports.type_products'), 'icon' => 'products'],
        ];
        $hasFilters = $from || $to || $clientId || $productId || $sellerId;
        $exportQuery = array_filter([
            'type' => $activeType,
            'from' => $from,
            'to' => $to,
            'client_id' => $clientId,
            'product_id' => $productId,
            'seller_id' => $sellerId,
        ]);
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.menu.reports') }}</h2>
            <p class="text-sm text-on-surface-muted">{{ __('app.reports.list_desc') }}</p>
        </div>
    </div>

    <div class="divider">&nbsp;</div>

    {{-- Report type tabs --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($reportTypes as $rt)
            <a
                href="{{ route('reports.index', ['type' => $rt['type']]) }}"
                @class([
                    'flex items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold transition',
                    'border-primary bg-primary-soft text-primary' => $activeType === $rt['type'],
                    'border-border bg-surface text-on-surface-muted hover:border-primary/40 hover:text-on-surface' => $activeType !== $rt['type'],
                ])
            >
                <x-icon :name="$rt['icon']" class="h-4 w-4" />
                {{ $rt['label'] }}
            </a>
        @endforeach
    </div>

    <div class="divider">&nbsp;</div>

    {{-- Metrics --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-card><x-metric :label="__('app.menu.sales')" :value="number_format($metrics['sales'])" icon="sales" color="primary" /></x-card>
        <x-card><x-metric :label="__('app.menu.products')" :value="number_format($metrics['products'])" icon="box" color="info" /></x-card>
        <x-card><x-metric :label="__('app.menu.clients')" :value="number_format($metrics['clients'])" icon="users" color="success" /></x-card>
        <x-card><x-metric :label="__('app.dashboard.inventory_value')" :value="$metrics['inventoryValue']" icon="coins" color="warning" /></x-card>
    </div>

    <div class="divider">&nbsp;</div>

    {{-- Filters --}}
    <x-card class="mt-6">
        <form method="GET" action="{{ route('reports.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="hidden" name="type" value="{{ $activeType }}">

            <div class="grid grid-cols-2 gap-3">
                <x-input type="date" name="from" :label="__('app.sales.from_date')" :value="$from" />
                <x-input type="date" name="to" :label="__('app.sales.to_date')" :value="$to" />
            </div>

            @if (in_array($activeType, ['sales', 'clients']))
                <x-select name="client_id" :selected="$clientId" :label="__('app.reports.filter_client')" :options="$clients->pluck('name', 'id')->prepend(__('app.labels.all'), '')->all()" />
            @endif

            @if (in_array($activeType, ['inventory', 'products']))
                <x-select name="product_id" :selected="$productId" :label="__('app.reports.filter_product')" :options="$products->pluck('name', 'id')->prepend(__('app.labels.all'), '')->all()" />
            @endif

            @if (in_array($activeType, ['sales']))
                <x-select name="seller_id" :selected="$sellerId" :label="__('app.reports.filter_seller')" :options="$sellers->pluck('name', 'id')->prepend(__('app.labels.all'), '')->all()" />
            @endif

            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                <x-button type="submit" size="sm" icon="filter">{{ __('app.actions.apply') }}</x-button>
                @if ($hasFilters)
                    <x-button :href="route('reports.index', ['type' => $activeType])" variant="ghost" size="sm" icon="close">{{ __('app.actions.reset') }}</x-button>
                @endif

                <span class="ml-auto flex items-center gap-2">
                    <x-button :href="route('reports.export.pdf', $exportQuery)" variant="secondary" size="sm" icon="download" onclick="window.showToast('success', '{{ __('app.flash.exported') }}')">{{ __('app.actions.export_pdf') }}</x-button>
                    <x-button :href="route('reports.export.excel', $exportQuery)" variant="secondary" size="sm" icon="download" onclick="window.showToast('success', '{{ __('app.flash.exported') }}')">{{ __('app.actions.export_excel') }}</x-button>
                </span>
            </div>
        </form>
    </x-card>

    <div class="divider">&nbsp;</div>

    <div class="mt-4">
        @include('reports.partials.'.$activeType)
    </div>

</x-layouts.app>