<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $company }} — {{ $reportLabel }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        .header { display: flex; align-items: flex-end; justify-content: space-between; border-bottom: 2px solid #2563eb; padding-bottom: 8px; }
        h1 { margin: 0; font-size: 18px; color: #111827; }
        .subtitle { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .meta { font-size: 10px; color: #6b7280; text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #4b5563; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; }
        td.num, th.num { text-align: right; }
        .total-row td { font-weight: bold; background: #f9fafb; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ $company }}</h1>
            <p class="subtitle">{{ __('app.reports.export_title', ['type' => $reportLabel]) }}
                @if ($from || $to)
                    — {{ $from ?? '…' }} / {{ $to ?? '…' }}
                @endif
            </p>
        </div>
        <p class="meta">
            {{ __('app.reports.exported_at', ['date' => now()->format('d/m/Y H:i'), 'user' => auth()->user()->name]) }}
        </p>
    </div>

    @switch($type)
        @case('inventory')
            <table>
                <thead>
                    <tr>
                        <th>{{ __('app.labels.sku') }}</th>
                        <th>{{ __('app.labels.name') }}</th>
                        <th class="num">{{ __('app.inventory.entries') }}</th>
                        <th class="num">{{ __('app.inventory.exits') }}</th>
                        <th class="num">{{ __('app.inventory.net') }}</th>
                        <th class="num">{{ __('app.labels.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data as $row)
                        <tr>
                            <td>{{ $row['sku'] ?? '—' }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ number_format($row['stock_in']) }}</td>
                            <td class="num">{{ number_format($row['stock_out']) }}</td>
                            <td class="num">{{ $row['net'] }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($row['value']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center; color:#6b7280;">{{ __('app.reports.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        @break

        @case('clients')
            <table>
                <thead>
                    <tr>
                        <th>{{ __('app.labels.name') }}</th>
                        <th>{{ __('app.labels.nit') }}</th>
                        <th class="num">{{ __('app.clients.sales_count') }}</th>
                        <th class="num">{{ __('app.clients.total_purchases') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['clients'] as $client)
                        <tr>
                            <td>{{ $client->name }}</td>
                            <td>{{ $client->nit ?? '—' }}</td>
                            <td class="num">{{ $client->sales_count }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($client->sales_total ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center; color:#6b7280;">{{ __('app.reports.no_results') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($data['clients']->isNotEmpty())
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" style="text-align:right;">{{ __('app.labels.total') }} ({{ $data['count'] }})</td>
                            <td class="num">{{ number_format($data['clients']->sum('sales_count')) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($data['total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        @break

        @case('products')
            <table>
                <thead>
                    <tr>
                        <th>{{ __('app.labels.sku') }}</th>
                        <th>{{ __('app.labels.name') }}</th>
                        <th class="num">{{ __('app.labels.quantity') }}</th>
                        <th class="num">{{ __('app.labels.total') }}</th>
                        <th class="num">{{ __('app.products.margin') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data as $row)
                        <tr>
                            <td>{{ $row['sku'] ?? '—' }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ number_format($row['quantity']) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($row['revenue']) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($row['margin']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center; color:#6b7280;">{{ __('app.reports.no_results') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($data->isNotEmpty())
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" style="text-align:right;">{{ __('app.labels.total') }}</td>
                            <td class="num">{{ number_format($data->sum('quantity')) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($data->sum('revenue')) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($data->sum('margin')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        @break

        @default
            @php($sales = $data['sales'])
            <table>
                <thead>
                    <tr>
                        <th>{{ __('app.labels.invoice_number') }}</th>
                        <th>{{ __('app.labels.date') }}</th>
                        <th>{{ __('app.labels.name') }}</th>
                        <th>{{ __('app.labels.nit') }}</th>
                        <th>{{ __('app.dashboard.seller') }}</th>
                        <th class="num">{{ __('app.labels.subtotal') }}</th>
                        <th class="num">{{ __('app.labels.tax') }}</th>
                        <th class="num">{{ __('app.labels.total') }}</th>
                        <th>{{ __('app.labels.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>{{ $sale->invoiceNumber() }}</td>
                            <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $sale->client?->name ?? __('app.sales.no_invoice_client') }}</td>
                            <td>{{ $sale->client?->nit ?? '—' }}</td>
                            <td>{{ $sale->seller?->name ?? '—' }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($sale->subtotal) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($sale->tax_amount) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($sale->total) }}</td>
                            <td>{{ $sale->isPaid() ? __('app.sales.status_paid') : __('app.sales.status_unpaid') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center; color:#6b7280;">{{ __('app.reports.no_results') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($sales->isNotEmpty())
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="5" style="text-align:right;">{{ __('app.labels.total') }} ({{ $data['count'] }})</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($data['totals']['subtotal']) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($data['totals']['tax']) }}</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($data['totals']['total']) }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="8" style="text-align:right;">{{ __('app.reports.paid_sales') }} ({{ $data['paidCount'] }})</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($data['paidTotal']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="8" style="text-align:right;">{{ __('app.reports.pending_sales') }} ({{ $data['unpaidCount'] }})</td>
                            <td class="num">{{ \App\Models\Setting::formatMoney($data['unpaidTotal']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
    @endswitch
</body>
</html>