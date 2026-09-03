<?php

namespace App\Http\Controllers;

use App\Exports\ReportsExport;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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

    public function index(Request $request): View
    {
        $type = $this->normalizedType($request);
        $from = $request->string('from')?->toString();
        $to = $request->string('to')?->toString();
        $clientId = $request->string('client_id')?->toString();
        $productId = $request->string('product_id')?->toString();
        $sellerId = $request->string('seller_id')?->toString();

        return view('reports.index', [
            'activeType' => $type,
            'from' => $from,
            'to' => $to,
            'clientId' => $clientId,
            'productId' => $productId,
            'sellerId' => $sellerId,
            'type' => $this->reportData($type, $from, $to, $clientId, $productId, $sellerId),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'sellers' => User::orderBy('name')->get(['id', 'name']),
            'metrics' => $this->indexMetrics(),
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
     * @return array{0: string, 1: mixed, 2: ?string, 3: ?string, 4: ?string, 5: ?string, 6: ?string}
     */
    private function resolved(Request $request): array
    {
        $type = $this->normalizedType($request);
        $from = $request->string('from')?->toString();
        $to = $request->string('to')?->toString();
        $clientId = $request->string('client_id')?->toString();
        $productId = $request->string('product_id')?->toString();
        $sellerId = $request->string('seller_id')?->toString();

        return [$type, $this->reportData($type, $from, $to, $clientId, $productId, $sellerId), $from, $to, $clientId, $productId, $sellerId];
    }

    private function reportData(string $type, ?string $from = null, ?string $to = null, ?string $clientId = null, ?string $productId = null, ?string $sellerId = null): mixed
    {
        return match ($type) {
            'inventory' => $this->inventoryReport($from, $to, $productId),
            'clients' => $this->clientsReport($from, $to, $clientId),
            'products' => $this->productsReport($from, $to, $productId),
            default => $this->salesReport($from, $to, $clientId, $sellerId),
        };
    }

    /**
     * @return array{sales: Collection, totals: array<int, float>, count: int}
     */
    private function salesReport(?string $from, ?string $to, ?string $clientId, ?string $sellerId): array
    {
        $sales = Sale::query()
            ->with(['client:id,name,nit', 'seller:id,name'])
            ->betweenDates($from, $to)
            ->when($clientId, fn (Builder $query) => $query->where('client_id', $clientId))
            ->when($sellerId, fn (Builder $query) => $query->where('seller_id', $sellerId))
            ->latest('id')
            ->get();

        return [
            'sales' => $sales,
            'totals' => [
                'subtotal' => (float) $sales->sum('subtotal'),
                'tax' => (float) $sales->sum('tax_amount'),
                'total' => (float) $sales->sum('total'),
            ],
            'count' => $sales->count(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, sku: ?string, stock_in: int, stock_out: int, net: int, value: float}>
     */
    private function inventoryReport(?string $from, ?string $to, ?string $productId): Collection
    {
        $movements = InventoryMovement::query()
            ->when($from, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('created_at', '<=', $to))
            ->when($productId, fn (Builder $query) => $query->where('product_id', $productId))
            ->with('product:id,name,sku,production_cost,stock')
            ->get();

        return $movements
            ->groupBy('product_id')
            ->map(function (Collection $group) {
                $product = $group->first()->product;

                return [
                    'id' => $product?->id,
                    'name' => $product?->name ?? __('app.labels.none'),
                    'sku' => $product?->sku,
                    'stock_in' => (int) $group->where('type', InventoryMovement::TYPE_IN)->sum('quantity'),
                    'stock_out' => (int) $group->where('type', InventoryMovement::TYPE_OUT)->sum('quantity'),
                    'net' => (int) $group->where('type', InventoryMovement::TYPE_IN)->sum('quantity')
                        - (int) $group->where('type', InventoryMovement::TYPE_OUT)->sum('quantity'),
                    'value' => (float) ($product?->stock * $product?->production_cost ?? 0),
                ];
            })
            ->sortByDesc('net')
            ->values();
    }

    /**
     * @return array{clients: Collection, count: int, total: float}
     */
    private function clientsReport(?string $from, ?string $to, ?string $clientId): array
    {
        $clients = Client::query()
            ->withCount([
                'sales as sales_count' => fn (Builder $query) => $query
                    ->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', $from))
                    ->when($to, fn (Builder $q) => $q->whereDate('created_at', '<=', $to)),
            ])
            ->withSum([
                'sales as sales_total' => fn (Builder $query) => $query
                    ->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', $from))
                    ->when($to, fn (Builder $q) => $q->whereDate('created_at', '<=', $to)),
            ], 'total')
            ->when($clientId, fn (Builder $query) => $query->whereKey($clientId))
            ->orderByDesc('sales_total')
            ->get();

        return [
            'clients' => $clients,
            'count' => $clients->count(),
            'total' => (float) $clients->sum('sales_total'),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, sku: ?string, quantity: int, revenue: float, margin: float}>
     */
    private function productsReport(?string $from, ?string $to, ?string $productId): Collection
    {
        $rows = SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sale_items.product_id, products.name, products.sku, products.production_cost, products.sale_price,
                SUM(sale_items.quantity) as quantity, SUM(sale_items.total) as revenue')
            ->when($from, fn (Builder $query) => $query->whereDate('sales.created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('sales.created_at', '<=', $to))
            ->when($productId, fn (Builder $query) => $query->where('sale_items.product_id', $productId))
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku', 'products.production_cost', 'products.sale_price')
            ->orderByDesc('quantity')
            ->get();

        return $rows->map(function ($row) {
            $cost = (int) $row->quantity * (float) $row->production_cost;

            return [
                'id' => $row->product_id,
                'name' => $row->name,
                'sku' => $row->sku,
                'quantity' => (int) $row->quantity,
                'revenue' => (float) $row->revenue,
                'cost' => $cost,
                'margin' => (float) $row->revenue - $cost,
            ];
        })->values();
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
                [__('app.labels.invoice_number'), __('app.labels.date'), __('app.labels.name'), __('app.labels.nit'), __('app.dashboard.seller'), __('app.labels.subtotal'), __('app.labels.tax'), __('app.labels.total')],
                $data['sales']->map(fn (Sale $sale) => [
                    $sale->invoiceNumber(),
                    $sale->created_at->format('d/m/Y H:i'),
                    $sale->client?->name ?? __('app.sales.no_invoice_client'),
                    $sale->client?->nit,
                    $sale->seller?->name ?? __('app.labels.none'),
                    (float) $sale->subtotal,
                    (float) $sale->tax_amount,
                    (float) $sale->total,
                ])->all(),
            ],
        };
    }

    /**
     * @return array<string, int|string>
     */
    private function indexMetrics(): array
    {
        return [
            'sales' => Sale::count(),
            'products' => Product::count(),
            'clients' => Client::count(),
            'inventoryValue' => Setting::formatMoney((float) Product::query()
                ->selectRaw('SUM(stock * production_cost) as value')
                ->value('value')),
        ];
    }
}
