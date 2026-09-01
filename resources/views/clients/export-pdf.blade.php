<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $company }} — {{ __('app.clients.export_title') }}</title>
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
            <p class="subtitle">{{ __('app.clients.export_title') }}</p>
        </div>
        <p class="meta">
            {{ __('app.clients.exported_at', ['date' => now()->format('d/m/Y H:i'), 'user' => auth()->user()->name]) }}
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('app.labels.name') }}</th>
                <th>{{ __('app.labels.nit') }}</th>
                <th>{{ __('app.labels.email') }}</th>
                <th>{{ __('app.labels.phone') }}</th>
                <th>{{ __('app.labels.address') }}</th>
                <th class="num">{{ __('app.clients.sales_count') }}</th>
                <th class="num">{{ __('app.clients.total_purchases') }}</th>
                <th class="num">{{ __('app.clients.pending_balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clients as $index => $client)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $client->name }}</td>
                    <td>{{ $client->nit ?? '—' }}</td>
                    <td>{{ $client->email ?? '—' }}</td>
                    <td>{{ $client->phone ?? '—' }}</td>
                    <td>{{ $client->address ?? '—' }}</td>
                    <td class="num">{{ $client->sales_count }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($client->sales_sum_total ?? 0) }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($client->pending_balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; color:#6b7280;">{{ __('app.clients.no_results') }}</td></tr>
            @endforelse
        </tbody>
        @if ($clients->isNotEmpty())
            <tfoot>
                <tr class="total-row">
                    <td colspan="6" style="text-align:right;">{{ __('app.labels.total') }}</td>
                    <td class="num">{{ number_format($clients->sum('sales_count')) }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($clients->sum('sales_sum_total')) }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($clients->sum('pending_balance')) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>