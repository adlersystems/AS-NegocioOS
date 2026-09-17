<x-layouts.app :title="__('app.menu.dashboard')">

    @php
        $user = auth()->user();
        $canSell = in_array($user->role, ['admin', 'vendedor'], true);
        $canManage = in_array($user->role, ['admin', 'encargado'], true);
        $totalAlerts = $alerts['low_stock'] + $alerts['out_of_stock'] + $alerts['expiring_soon'];
        $chartData = [
            'charts' => $charts,
            'currency' => \App\Models\Setting::currencySymbol(),
            'labels' => [
                'sales' => __('app.dashboard.sales_count'),
                'revenue' => __('app.labels.total'),
                'quantity' => __('app.dashboard.top_product_qty'),
            ],
        ];
    @endphp

    {{-- Welcome + quick actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ __('app.dashboard.welcome', ['name' => $user->name]) }}</h2>
            <p class="text-sm text-on-surface-muted">{{ $user->email }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canSell)
                <x-button :href="route('sales.index')" icon="plus">{{ __('app.dashboard.new_sale') }}</x-button>
            @endif
            @if ($canManage)
                <x-button :href="route('products.index')" icon="plus" variant="secondary">{{ __('app.dashboard.new_product') }}</x-button>
            @endif
            <x-button :href="route('clients.index')" icon="plus" variant="secondary">{{ __('app.dashboard.new_client') }}</x-button>
        </div>
    </div>

    {{-- Core counts --}}
    <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-card><x-metric :label="__('app.dashboard.clients')" :value="number_format($counts['clients'])" icon="clients" color="primary" /></x-card>
        <x-card><x-metric :label="__('app.dashboard.products')" :value="number_format($counts['products'])" icon="products" color="success" /></x-card>
        <x-card><x-metric :label="__('app.dashboard.sales_count')" :value="number_format($counts['sales'])" icon="sales" color="info" /></x-card>
        <x-card><x-metric :label="__('app.dashboard.receivables')" :value="$counts['receivables']" icon="coins" color="warning" /></x-card>
    </div>

    {{-- KPIs --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-card><x-metric :label="__('app.dashboard.today_revenue')" :value="$kpis['today_revenue']" icon="sales" color="info" /></x-card>
        <x-card><x-metric :label="__('app.dashboard.month_revenue')" :value="$kpis['month_revenue']" icon="calendar" color="primary" /></x-card>
        <x-card><x-metric :label="__('app.dashboard.arpu')" :value="$kpis['arpu']" icon="receipt" color="success" /></x-card>
        @if ($canViewCosts)
            <x-card><x-metric :label="__('app.dashboard.inventory_value')" :value="$kpis['inventory_value']" icon="box" color="warning" /></x-card>
        @endif
    </div>

    {{-- Inventory alerts --}}
    <div class="mt-6">
        <x-card :title="__('app.dashboard.alerts_title')">
            @if ($totalAlerts > 0)
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-warning-soft px-3 py-2 text-sm font-semibold text-warning transition hover:opacity-90">
                        <x-icon name="alert" class="h-4 w-4" /> {{ $alerts['out_of_stock'] }} {{ __('app.dashboard.out_of_stock') }}
                    </a>
                    <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-danger-soft px-3 py-2 text-sm font-semibold text-danger transition hover:opacity-90">
                        <x-icon name="alert" class="h-4 w-4" /> {{ $alerts['low_stock'] }} {{ __('app.dashboard.low_stock') }}
                    </a>
                    <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-info-soft px-3 py-2 text-sm font-semibold text-info transition hover:opacity-90">
                        <x-icon name="calendar" class="h-4 w-4" /> {{ $alerts['expiring_soon'] }} {{ __('app.dashboard.expiring_soon') }}
                    </a>
                </div>
            @else
                <p class="flex items-center gap-2 text-sm font-medium text-success">
                    <x-icon name="check" class="h-4 w-4" /> {{ __('app.dashboard.alerts_ok') }}
                </p>
            @endif
        </x-card>
    </div>

    {{-- Charts --}}
    @if ($counts['sales'] > 0)
        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2" x-data="dashboardCharts(@js($chartData))">
            <x-card :title="__('app.dashboard.sales_monthly_title')">
                <div class="h-64">
                    <canvas id="chart-sales-monthly"></canvas>
                </div>
            </x-card>

            @if ($canViewCosts)
                <x-card :title="__('app.dashboard.sales_by_seller')">
                    <div class="h-64">
                        <canvas id="chart-by-seller"></canvas>
                    </div>
                </x-card>
            @endif

            <x-card :title="__('app.dashboard.top_products')">
                <div class="h-64">
                    <canvas id="chart-top-products"></canvas>
                </div>
            </x-card>

            <x-card :title="__('app.dashboard.revenue_title')">
                <div class="h-64">
                    <canvas id="chart-revenue-trend"></canvas>
                </div>
            </x-card>
        </div>
    @endif

    {{-- Top client + recent sales --}}
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card :title="__('app.dashboard.top_client_title')">
            @if ($topClient)
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-on-surface">{{ $topClient['name'] }}</p>
                        <p class="text-xs text-on-surface-muted">{{ __('app.dashboard.sales_count') }}</p>
                    </div>
                    <p class="shrink-0 text-lg font-bold text-primary">{{ $topClient['total'] }}</p>
                </div>
            @else
                <x-empty-state :title="__('app.dashboard.no_top_client')" icon="clients" />
            @endif
        </x-card>

        <div class="lg:col-span-2">
            <x-card :title="__('app.dashboard.recent_sales')">
                <x-slot:header>
                    <x-button :href="route('sales.index')" variant="ghost" size="xs" icon="arrow-left" class="!pr-1">
                        {{ __('app.dashboard.view_all') }}
                    </x-button>
                </x-slot:header>

                @if ($recentSales->isEmpty())
                    <x-empty-state :title="__('app.dashboard.empty_recent')" icon="sales" />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($recentSales as $sale)
                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-on-surface">{{ $sale->invoiceNumber() }}</p>
                                    <p class="truncate text-xs text-on-surface-muted">
                                        {{ $sale->buyerName() ?? __('app.labels.none') }}
                                        · {{ $sale->seller?->name }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-on-surface">{{ \App\Models\Setting::formatMoney($sale->total) }}</p>
                                    <p class="text-xs text-on-surface-muted">{{ $sale->created_at->diffForHumans() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>

</x-layouts.app>