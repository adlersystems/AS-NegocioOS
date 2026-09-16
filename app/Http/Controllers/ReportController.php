<?php

namespace App\Http\Controllers;

use App\Exports\ReportsExport;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    private const TYPES = [
        'sales' => ['label' => 'app.reports.type_sales', 'icon' => 'sales'],
        'inventory' => ['label' => 'app.reports.type_inventory', 'icon' => 'inventory'],
        'clients' => ['label' => 'app.reports.type_clients', 'icon' => 'clients'],
        'products' => ['label' => 'app.reports.type_products', 'icon' => 'products'],
    ];

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $type = $this->normalizedType($request);
        $from = $request->string('from')?->toString();
        $to = $request->string('to')?->toString();
        $clientId = $request->string('client_id')?->toString();
        $productId = $request->string('product_id')?->toString();
        $sellerId = $request->string('seller_id')?->toString();
        $paid = $request->string('paid')?->toString();

        return view('reports.index', [
            'activeType' => $type,
            'from' => $from,
            'to' => $to,
            'clientId' => $clientId,
            'productId' => $productId,
            'sellerId' => $sellerId,
            'paid' => $paid,
            'type' => $this->reports->data($type, $from, $to, $clientId, $productId, $sellerId, $paid),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'sellers' => User::orderBy('name')->get(['id', 'name']),
            'metrics' => $this->reports->indexMetrics(),
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        [$type, $data, $from, $to, $clientId, $productId, $sellerId] = $this->resolved($request);

        $pdf = Pdf::loadView('reports.export-pdf', [
            'type' => $type,
            'reportLabel' => __(self::TYPES[$type]['label']),
            'data' => $data,
            'from' => $from,
            'to' => $to,
            'company' => Setting::get('company_name', 'AS-NegocioOS'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reporte-'.$type.'-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$type, $data, $from, $to, $clientId, $productId, $sellerId] = $this->resolved($request);

        [$headings, $rows] = $this->tabular($type, $data);

        return Excel::download(
            new ReportsExport($headings, $rows),
            'reporte-'.$type.'-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    private function normalizedType(Request $request): string
    {
        $type = $request->string('type')?->toString();

        return isset(self::TYPES[$type]) ? $type : 'sales';
    }

    /**
     * @return array{0: string, 1: mixed, 2: ?string, 3: ?string, 4: ?string, 5: ?string, 6: ?string, 7: ?string}
     */
    private function resolved(Request $request): array
    {
        $type = $this->normalizedType($request);
        $from = $request->string('from')?->toString();
        $to = $request->string('to')?->toString();
        $clientId = $request->string('client_id')?->toString();
        $productId = $request->string('product_id')?->toString();
        $sellerId = $request->string('seller_id')?->toString();
        $paid = $request->string('paid')?->toString();

        return [$type, $this->reports->data($type, $from, $to, $clientId, $productId, $sellerId, $paid), $from, $to, $clientId, $productId, $sellerId, $paid];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function tabular(string $type, mixed $data): array
    {
        return match ($type) {
            'inventory' => [
                [__('app.labels.sku'), __('app.labels.name'), __('app.inventory.entries'), __('app.inventory.exits'), __('app.labels.total'), __('app.inventory.net')],
                $data->map(fn ($row) => [$row['sku'], $row['name'], $row['stock_in'], $row['stock_out'], (float) $row['value'], $row['net']])->all(),
            ],
            'clients' => [
                [__('app.labels.name'), __('app.labels.nit'), __('app.clients.sales_count'), __('app.clients.total_purchases')],
                $data['clients']->map(fn ($client) => [$client->name, $client->nit, $client->sales_count, (float) $client->sales_total])->all(),
            ],
            'products' => [
                [__('app.labels.sku'), __('app.labels.name'), __('app.labels.quantity'), __('app.labels.total'), __('app.products.margin')],
                $data->map(fn ($row) => [$row['sku'], $row['name'], $row['quantity'], (float) $row['revenue'], (float) $row['margin']])->all(),
            ],
            default => [
                [__('app.labels.invoice_number'), __('app.labels.date'), __('app.labels.name'), __('app.labels.nit'), __('app.dashboard.seller'), __('app.labels.subtotal'), __('app.labels.tax'), __('app.labels.total'), __('app.labels.status')],
                $data['sales']->map(fn (Sale $sale) => [
                    $sale->invoiceNumber(),
                    $sale->created_at->format('d/m/Y H:i'),
                    $sale->buyerName() ?? __('app.sales.no_invoice_client'),
                    $sale->buyerNit(),
                    $sale->seller?->name ?? __('app.labels.none'),
                    (float) $sale->subtotal,
                    (float) $sale->tax_amount,
                    (float) $sale->total,
                    $sale->isPaid() ? __('app.sales.status_paid') : __('app.sales.status_unpaid'),
                ])->all(),
            ],
        };
    }
}
