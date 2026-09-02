<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $company }} — {{ __('app.inventory.export_title') }}</title>
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
            <p class="subtitle">{{ __('app.inventory.export_title') }}</p>
        </div>
        <p class="meta">
            {{ __('app.inventory.exported_at', ['date' => now()->format('d/m/Y H:i'), 'user' => auth()->user()->name]) }}
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('app.labels.date') }}</th>
                <th>{{ __('app.labels.name') }}</th>
                <th>{{ __('app.labels.sku') }}</th>
                <th>{{ __('app.labels.type') }}</th>
                <th class="num">{{ __('app.labels.quantity') }}</th>
                <th>{{ __('app.labels.reason') }}</th>
                <th>{{ __('app.labels.reference') }}</th>
                <th>{{ __('app.dashboard.seller') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $index => $movement)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $movement->product?->name ?? '—' }}</td>
                    <td>{{ $movement->product?->sku ?? '—' }}</td>
                    <td>{{ $movement->isIn() ? __('app.inventory.type_in') : __('app.inventory.type_out') }}</td>
                    <td class="num">{{ ($movement->isIn() ? '+' : '-').number_format($movement->quantity) }}</td>
                    <td>{{ $movement->reason ?? '—' }}</td>
                    <td>{{ $movement->reference ?? '—' }}</td>
                    <td>{{ $movement->user?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; color:#6b7280;">{{ __('app.inventory.no_results') }}</td></tr>
            @endforelse
        </tbody>
        @if ($movements->isNotEmpty())
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" style="text-align:right;">{{ __('app.labels.total') }}</td>
                    <td class="num">
                        {{ (int) $entries > 0 ? '+' : '' }}{{ number_format($entries) }}
                        / {{ (int) $exits > 0 ? '-' : '' }}{{ number_format($exits) }}
                    </td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>