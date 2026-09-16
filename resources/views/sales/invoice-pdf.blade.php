<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoiceNumber() }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        .header { display: flex; align-items: flex-end; justify-content: space-between; border-bottom: 2px solid #2563eb; padding-bottom: 8px; }
        .company { font-size: 18px; color: #111827; font-weight: bold; }
        .company-meta { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .invoice-box { font-size: 10px; color: #6b7280; text-align: right; }
        .invoice-box strong { display: block; font-size: 16px; color: #111827; }
        .parties { display: flex; justify-content: space-between; margin-top: 14px; }
        .party { width: 48%; }
        .party .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; margin-bottom: 3px; }
        .party p { margin: 0; font-size: 12px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #4b5563; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; }
        td.num, th.num { text-align: right; }
        .totals { margin-top: 10px; width: 260px; margin-left: auto; }
        .totals tr td { border: none; padding: 3px 8px; }
        .totals tr td:last-child { text-align: right; }
        .grand td { font-weight: bold; font-size: 13px; border-top: 1px solid #2563eb; }
        .notes { margin-top: 12px; font-size: 10px; color: #6b7280; }
        .footer { margin-top: 24px; text-align: center; font-size: 10px; color: #6b7280; }
        .footer strong { color: #2563eb; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display:flex; align-items:center; gap:10px;">
            @if ($logoUrl = \App\Models\Setting::logoDataUri())
                <img src="{{ $logoUrl }}" style="max-height:48px; max-width:120px;" alt="">
            @endif
            <div>
                <div class="company">{{ $company['name'] }}</div>
                <div class="company-meta">
                    @if ($company['nit'])
                        {{ __('app.labels.nit') }}: {{ $company['nit'] }}<br>
                    @endif
                    @if ($company['address'])
                        {{ $company['address'] }}
                    @endif
                    @if ($company['phone'])
                        <br>{{ $company['phone'] }}
                    @endif
                    @if ($company['email'])
                        <br>{{ $company['email'] }}
                    @endif
                </div>
            </div>
        </div>
        <div class="invoice-box">
            <strong>{{ $sale->invoiceNumber() }}</strong>
            {{ __('app.sales.sales_date') }}: {{ $sale->created_at->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <div class="label">{{ __('app.labels.name') }}</div>
            <p>{{ $sale->buyerName() ?? __('app.sales.no_invoice_client') }}</p>
            @if ($sale->buyerNit())
                <p>{{ __('app.labels.nit') }}: {{ $sale->buyerNit() }}</p>
            @endif
        </div>
        <div class="party" style="text-align:right;">
            <div class="label">{{ __('app.labels.seller') }}</div>
            <p>{{ $sale->seller?->name ?? '—' }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('app.sales.product') }}</th>
                <th class="num">{{ __('app.labels.quantity') }}</th>
                <th class="num">{{ __('app.labels.unit_price') }}</th>
                <th class="num">{{ __('app.labels.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product?->name ?? '—' }}</td>
                    <td class="num">{{ number_format($item->quantity) }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($item->unit_price) }}</td>
                    <td class="num">{{ \App\Models\Setting::formatMoney($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('app.labels.subtotal') }}</td>
            <td>{{ \App\Models\Setting::formatMoney($sale->subtotal) }}</td>
        </tr>
        <tr>
            <td>{{ __('app.labels.tax') }} ({{ $iva }}%)</td>
            <td>{{ \App\Models\Setting::formatMoney($sale->tax_amount) }}</td>
        </tr>
        <tr class="grand">
            <td>{{ __('app.labels.total') }}</td>
            <td>{{ \App\Models\Setting::formatMoney($sale->total) }}</td>
        </tr>
    </table>

    @if ($sale->notes)
        <div class="notes">
            <strong>{{ __('app.labels.notes') }}:</strong> {{ $sale->notes }}
        </div>
    @endif

    <div class="footer">
        {{ __('app.sales.thanks') }}<br>
        {{ __('app.sales.generated_by', ['user' => auth()->user()->name]) }}
    </div>
</body>
</html>