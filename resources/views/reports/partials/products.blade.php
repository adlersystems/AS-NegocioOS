<x-card>
    @if ($type->isEmpty())
        <x-empty-state :title="__('app.reports.no_results')" :description="__('app.reports.no_results_desc')" icon="reports" />
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                        <th class="py-2 pr-4">{{ __('app.labels.sku') }}</th>
                        <th class="py-2 pr-4">{{ __('app.labels.name') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.labels.quantity') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.labels.total') }}</th>
                        <th class="py-2 text-right">{{ __('app.products.margin') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($type as $row)
                        <tr>
                            <td class="py-3 pr-4 font-mono text-xs text-on-surface-muted">{{ $row['sku'] ?? '—' }}</td>
                            <td class="py-3 pr-4 font-semibold text-on-surface">{{ $row['name'] }}</td>
                            <td class="py-3 pr-4 text-right text-on-surface">{{ number_format($row['quantity']) }}</td>
                            <td class="py-3 pr-4 text-right font-semibold text-on-surface">{{ \App\Models\Setting::formatMoney($row['revenue']) }}</td>
                            <td class="py-3 text-right font-bold {{ $row['margin'] >= 0 ? 'text-success' : 'text-danger' }}">{{ \App\Models\Setting::formatMoney($row['margin']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-border text-sm font-semibold text-on-surface">
                        <td class="py-3 pr-4" colspan="2">{{ __('app.labels.total') }}</td>
                        <td class="py-3 pr-4 text-right">{{ number_format($type->sum('quantity')) }}</td>
                        <td class="py-3 pr-4 text-right text-primary">{{ \App\Models\Setting::formatMoney($type->sum('revenue')) }}</td>
                        <td class="py-3 text-right text-primary">{{ \App\Models\Setting::formatMoney($type->sum('margin')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-card>