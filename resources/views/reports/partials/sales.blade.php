<x-card>
    @if ($type['sales']->isEmpty())
        <x-empty-state :title="__('app.reports.no_results')" :description="__('app.reports.no_results_desc')" icon="reports" />
    @else
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-card>
                <x-metric :label="__('app.reports.paid_sales')" :value="number_format($type['paidCount'])" icon="check" color="success" :hint="\App\Models\Setting::formatMoney($type['paidTotal'])" />
            </x-card>
            <x-card>
                <x-metric :label="__('app.reports.pending_sales')" :value="number_format($type['unpaidCount'])" icon="undo" color="warning" :hint="\App\Models\Setting::formatMoney($type['unpaidTotal'])" />
            </x-card>
            <x-card>
                <x-metric :label="__('app.sales.singular')" :value="number_format($type['count'])" icon="sales" color="primary" :hint="\App\Models\Setting::formatMoney($type['totals']['total'])" />
            </x-card>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                        <th class="py-2 pr-4">{{ __('app.labels.invoice_number') }}</th>
                        <th class="py-2 pr-4">{{ __('app.labels.date') }}</th>
                        <th class="py-2 pr-4">{{ __('app.labels.name') }}</th>
                        <th class="py-2 pr-4">{{ __('app.dashboard.seller') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.labels.subtotal') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.labels.tax') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.labels.total') }}</th>
                        <th class="py-2 text-right">{{ __('app.labels.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($type['sales'] as $sale)
                        <tr>
                            <td class="whitespace-nowrap py-3 pr-4 font-semibold text-on-surface">
                                <a href="{{ route('sales.show', $sale) }}" class="font-semibold text-on-surface transition hover:text-primary">
                                    {{ $sale->invoiceNumber() }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap py-3 pr-4 text-on-surface-muted">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-3 pr-4">
                                @if ($sale->client)
                                    <a href="{{ route('clients.show', $sale->client) }}" class="font-semibold text-on-surface transition hover:text-primary">
                                        {{ $sale->client->name }}
                                    </a>
                                @else
                                    <span class="text-on-surface-muted">{{ __('app.sales.no_invoice_client') }}</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-on-surface">{{ $sale->seller?->name ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right text-on-surface">{{ \App\Models\Setting::formatMoney($sale->subtotal) }}</td>
                            <td class="py-3 pr-4 text-right text-on-surface">{{ \App\Models\Setting::formatMoney($sale->tax_amount) }}</td>
                            <td class="py-3 pr-4 text-right font-bold text-on-surface">{{ \App\Models\Setting::formatMoney($sale->total) }}</td>
                            <td class="py-3 text-right">
                                <x-badge :color="$sale->isPaid() ? 'green' : 'yellow'">
                                    {{ $sale->isPaid() ? __('app.sales.status_paid') : __('app.sales.status_unpaid') }}
                                </x-badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-border text-sm font-semibold text-on-surface">
                        <td class="py-3 pr-4" colspan="4">{{ __('app.labels.total') }} ({{ $type['count'] }})</td>
                        <td class="py-3 pr-4 text-right">{{ \App\Models\Setting::formatMoney($type['totals']['subtotal']) }}</td>
                        <td class="py-3 pr-4 text-right">{{ \App\Models\Setting::formatMoney($type['totals']['tax']) }}</td>
                        <td class="py-3 pr-4 text-right text-primary">{{ \App\Models\Setting::formatMoney($type['totals']['total']) }}</td>
                        <td class="py-3 text-right text-primary"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-card>