<x-card>
    @if ($type['clients']->isEmpty())
        <x-empty-state :title="__('app.reports.no_results')" :description="__('app.reports.no_results_desc')" icon="reports" />
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                        <th class="py-2 pr-4">{{ __('app.labels.name') }}</th>
                        <th class="py-2 pr-4">{{ __('app.labels.nit') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.clients.sales_count') }}</th>
                        <th class="py-2 text-right">{{ __('app.clients.total_purchases') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($type['clients'] as $client)
                        <tr>
                            <td class="py-3 pr-4">
                                <a href="{{ route('clients.show', $client) }}" class="font-semibold text-on-surface transition hover:text-primary">{{ $client->name }}</a>
                            </td>
                            <td class="py-3 pr-4 text-on-surface-muted">{{ $client->nit ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right text-on-surface">{{ number_format($client->sales_count) }}</td>
                            <td class="py-3 text-right font-bold text-on-surface">{{ \App\Models\Setting::formatMoney($client->sales_total ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-border text-sm font-semibold text-on-surface">
                        <td class="py-3 pr-4" colspan="2">{{ __('app.labels.total') }} ({{ $type['count'] }})</td>
                        <td class="py-3 pr-4 text-right">{{ number_format($type['clients']->sum('sales_count')) }}</td>
                        <td class="py-3 text-right text-primary">{{ \App\Models\Setting::formatMoney($type['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-card>