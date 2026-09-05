<x-layouts.app :title="$sale->invoiceNumber()">

    @php
        $totalQuantity = $sale->items->sum('quantity');
    @endphp

    <div x-data="{ deleteSale: null }">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-button :href="route('sales.index')" variant="ghost" size="sm" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
                <h2 class="text-xl font-bold text-on-surface sm:text-2xl">{{ $sale->invoiceNumber() }}</h2>
                <x-badge :color="$sale->isPaid() ? 'green' : 'yellow'">
                    {{ $sale->isPaid() ? __('app.sales.status_paid') : __('app.sales.status_unpaid') }}
                </x-badge>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (in_array(auth()->user()->role, ['admin', 'encargado'], true))
                    <form method="POST" action="{{ route('sales.paid.toggle', $sale) }}">
                        @csrf
                        @method('PATCH')
                        <x-button :variant="$sale->isPaid() ? 'secondary' : 'primary'" size="sm" :icon="$sale->isPaid() ? 'undo' : 'check'" type="submit">
                            {{ $sale->isPaid() ? __('app.sales.mark_unpaid') : __('app.sales.mark_paid') }}
                        </x-button>
                    </form>
                @endif
                <x-button :href="route('sales.invoice.pdf', $sale)" variant="secondary" size="sm" icon="download">{{ __('app.actions.export_pdf') }}</x-button>
                @if (in_array(auth()->user()->role, ['admin', 'vendedor'], true))
                    <x-button :href="route('sales.edit', $sale)" variant="secondary" size="sm" icon="pencil">{{ __('app.actions.edit') }}</x-button>
                    <x-button type="button" variant="danger" size="sm" icon="trash" @click="deleteSale = @js(['name' => $sale->invoiceNumber(), 'url' => route('sales.destroy', $sale)])">{{ __('app.actions.delete') }}</x-button>
                @endif
            </div>
        </div>

        {{-- Summary --}}
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-card>
                <x-metric :label="__('app.labels.total')" :value="\App\Models\Setting::formatMoney($sale->total)" icon="receipt" color="primary" />
            </x-card>
            <x-card>
                <x-metric :label="__('app.labels.quantity')" :value="number_format($totalQuantity)" icon="box" color="info" />
            </x-card>
            <x-card>
                <x-metric :label="__('app.sales.sales_date')" :value="$sale->created_at->format('d/m/Y H:i')" icon="calendar" color="info" />
            </x-card>
        </div>

        {{-- Invoice --}}
        <x-card class="mt-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-on-surface">{{ $sale->invoiceNumber() }}</h3>
                    <p class="mt-0.5 text-sm text-on-surface-muted">{{ __('app.sales.sales_date') }}: {{ $sale->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <div class="text-right text-sm">
                    <p class="font-semibold text-on-surface">{{ $sale->client?->name ?? __('app.sales.no_invoice_client') }}</p>
                    @if ($sale->client?->nit)
                        <p class="text-on-surface-muted">{{ __('app.labels.nit') }}: {{ $sale->client->nit }}</p>
                    @endif
                    <p class="text-on-surface-muted">{{ __('app.labels.seller') }}: {{ $sale->seller?->name ?? '—' }}</p>
                </div>
            </div>

            <table class="mt-4 w-full min-w-[560px] text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                        <th class="py-2 pr-4">{{ __('app.sales.product') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.labels.quantity') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.labels.unit_price') }}</th>
                        <th class="py-2 text-right">{{ __('app.labels.total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($sale->items as $item)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-on-surface">{{ $item->product?->name ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right text-on-surface">{{ number_format($item->quantity) }}</td>
                            <td class="py-3 pr-4 text-right text-on-surface">{{ \App\Models\Setting::formatMoney($item->unit_price) }}</td>
                            <td class="py-3 text-right font-semibold text-on-surface">{{ \App\Models\Setting::formatMoney($item->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4 flex justify-end">
                <dl class="w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-on-surface-muted">{{ __('app.labels.subtotal') }}</dt>
                        <dd class="font-semibold text-on-surface">{{ \App\Models\Setting::formatMoney($sale->subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-on-surface-muted">{{ __('app.labels.tax') }}</dt>
                        <dd class="text-on-surface">{{ \App\Models\Setting::formatMoney($sale->tax_amount) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-border pt-1">
                        <dt class="font-semibold text-on-surface">{{ __('app.labels.total') }}</dt>
                        <dd class="text-lg font-bold text-primary">{{ \App\Models\Setting::formatMoney($sale->total) }}</dd>
                    </div>
                </dl>
            </div>

            @if ($sale->notes)
                <div class="mt-4 rounded-lg bg-surface-sunken px-4 py-3 text-sm text-on-surface">
                    <span class="font-medium text-on-surface-muted">{{ __('app.labels.notes') }}:</span>
                    <span class="whitespace-pre-line">{{ $sale->notes }}</span>
                </div>
            @endif
        </x-card>

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