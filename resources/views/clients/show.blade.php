<x-layouts.app :title="$client->name">

    @php
        $hasDebt = (float) $client->pending_balance > 0;
        $totalPurchases = (float) $sales->sum('total');
        $initials = collect(explode(' ', trim($client->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-button :href="route('clients.index')" variant="ghost" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
        <x-button :href="route('clients.edit', $client)" variant="secondary" icon="pencil">{{ __('app.actions.edit') }}</x-button>
    </div>

    {{-- Profile --}}
    <x-card class="mt-4">
        <div class="flex flex-wrap items-start gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xl font-bold text-primary">
                {{ $initials }}
            </div>

            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ $client->name }}</h2>
                <p class="mt-0.5 text-sm text-on-surface-muted">{{ $client->email ?? __('app.labels.none') }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-badge color="primary">{{ __('app.clients.language_'.($client->preferred_language ?? 'es')) }}</x-badge>
                    <span class="text-xs text-on-surface-muted">{{ __('app.clients.since', ['date' => $client->created_at->format('d/m/Y')]) }}</span>
                </div>
            </div>
        </div>

        <dl class="mt-6 grid gap-4 border-t border-border pt-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-on-surface-muted">{{ __('app.labels.nit') }}</dt>
                <dd class="mt-0.5 text-on-surface">{{ $client->nit ?? '—' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-on-surface-muted">{{ __('app.labels.phone') }}</dt>
                <dd class="mt-0.5 text-on-surface">{{ $client->phone ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="font-medium text-on-surface-muted">{{ __('app.labels.address') }}</dt>
                <dd class="mt-0.5 text-on-surface">{{ $client->address ?? '—' }}</dd>
            </div>
            @if ($client->notes)
                <div class="sm:col-span-2">
                    <dt class="font-medium text-on-surface-muted">{{ __('app.labels.notes') }}</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-on-surface">{{ $client->notes }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    {{-- Summary --}}
    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-card>
            <x-metric :label="__('app.clients.sales_count')" :value="number_format($sales->count())" icon="sales" color="info" />
        </x-card>
        <x-card>
            <x-metric :label="__('app.clients.total_purchases')" :value="\App\Models\Setting::formatMoney($totalPurchases)" icon="receipt" color="primary" />
        </x-card>
        <x-card>
            <x-metric :label="__('app.clients.pending_balance')" :value="\App\Models\Setting::formatMoney($client->pending_balance)" icon="coins" :color="$hasDebt ? 'warning' : 'success'" />
        </x-card>
    </div>

    {{-- Purchase history --}}
    <x-card :title="__('app.clients.history')" class="mt-4">
        @if ($sales->isEmpty())
            <x-empty-state :title="__('app.clients.no_history')" icon="receipt" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                            <th class="py-2 pr-4">{{ __('app.clients.invoice') }}</th>
                            <th class="py-2 pr-4">{{ __('app.labels.date') }}</th>
                            <th class="py-2 pr-4">{{ __('app.clients.seller') }}</th>
                            <th class="py-2 pr-4 text-right">{{ __('app.clients.subtotal') }}</th>
                            <th class="py-2 pr-4 text-right">{{ __('app.clients.tax') }}</th>
                            <th class="py-2 text-right">{{ __('app.clients.total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($sales as $sale)
                            <tr>
                                <td class="py-3 pr-4 font-semibold text-on-surface">{{ $sale->invoiceNumber() }}</td>
                                <td class="py-3 pr-4 text-on-surface-muted">{{ $sale->created_at->format('d/m/Y') }}</td>
                                <td class="py-3 pr-4 text-on-surface">{{ $sale->seller?->name ?? '—' }}</td>
                                <td class="py-3 pr-4 text-right text-on-surface">{{ \App\Models\Setting::formatMoney($sale->subtotal) }}</td>
                                <td class="py-3 pr-4 text-right text-on-surface-muted">{{ \App\Models\Setting::formatMoney($sale->tax_amount) }}</td>
                                <td class="py-3 text-right font-semibold text-on-surface">{{ \App\Models\Setting::formatMoney($sale->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

</x-layouts.app>