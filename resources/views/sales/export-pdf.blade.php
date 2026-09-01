<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $company }} — {{ __('app.menu.sales') }}</title>
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
            <p class="subtitle">{{ __('app.menu.sales') }}</p>
        </div>
        <p class="meta">
            {{ __('app.clients.exported_at', ['date' => now()->format('d/m/Y H:i'), 'user' => auth()->user()->name]) }}
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('app.labels.invoice_number') }}</th>
                <th>{{ __('app.labels.date') }}</th>
                <th>{{ __('app.labels.name') }}</th>
                <th>{{ __('app.labels.nit') }}</th>
                <th>{{ __('app.labels.seller') }}</th>
                <th class="num">{{ __('app.labels.subtotal') }}</th>
                <th class="num">{{ __('app.labels.tax') }}</th>
                <th class="num">{{ __('app.labels.total') }}</th>
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
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; color:#6b7280;">{{ __('app.sales.no_results') }}</td></tr>
            @endforelse
        </tbody>
        @if ($sales->isNotEmpty())
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" style="text-align:right;">{{ __('app.labels.total') }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($subtotal) }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($tax) }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($total) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>