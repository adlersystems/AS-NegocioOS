<x-card>
    @if ($type->isEmpty())
        <x-empty-state :title="__('app.reports.no_results')" :description="__('app.reports.no_results_desc')" icon="reports" />
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-on-surface-muted">
                        <th class="py-2 pr-4">{{ __('app.labels.sku') }}</th>
                        <th class="py-2 pr-4">{{ __('app.labels.name') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.inventory.entries') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.inventory.exits') }}</th>
                        <th class="py-2 pr-4 text-right">{{ __('app.inventory.net') }}</th>
                        <th class="py-2 text-right">{{ __('app.labels.total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($type as $row)
                        <tr>
                            <td class="py-3 pr-4 font-mono text-xs text-on-surface-muted">{{ $row['sku'] ?? '—' }}</td>
                            <td class="py-3 pr-4 font-semibold text-on-surface">{{ $row['name'] }}</td>
                            <td class="py-3 pr-4 text-right text-success">{{ number_format($row['stock_in']) }}</td>
                            <td class="py-3 pr-4 text-right text-danger">{{ number_format($row['stock_out']) }}</td>
                            <td class="py-3 pr-4 text-right font-semibold {{ $row['net'] >= 0 ? 'text-on-surface' : 'text-danger' }}">{{ $row['net'] > 0 ? '+'.$row['net'] : $row['net'] }}</td>
                            <td class="py-3 text-right font-semibold text-on-surface">{{ \App\Models\Setting::formatMoney($row['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>