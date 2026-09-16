<?php

namespace App\Services;

use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function data(string $type, ?string $from = null, ?string $to = null, ?string $clientId = null, ?string $productId = null, ?string $sellerId = null, ?string $paid = null): mixed
    {
        return match ($type) {
            'inventory' => $this->inventoryReport($from, $to, $productId),
            'clients' => $this->clientsReport($from, $to, $clientId),
            'products' => $this->productsReport($from, $to, $productId),
            default => $this->salesReport($from, $to, $clientId, $sellerId, $paid),
        };
    }

    /**
     * @return array<string, int|string>
     */
    public function indexMetrics(): array
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

    /**
     * @return array{sales: Collection, totals: array<int, float>, count: int, paidCount: int, unpaidCount: int, paidTotal: float, unpaidTotal: float}
     */
    private function salesReport(?string $from, ?string $to, ?string $clientId, ?string $sellerId, ?string $paid): array
    {
        $sales = Sale::query()
            ->with(['client:id,name,nit', 'seller:id,name'])
            ->betweenDates($from, $to)
            ->when($clientId, fn (Builder $query) => $query->where('client_id', $clientId))
            ->when($sellerId, fn (Builder $query) => $query->where('seller_id', $sellerId))
            ->when($paid === 'paid', fn (Builder $query) => $query->paid())
            ->when($paid === 'pending', fn (Builder $query) => $query->unpaid())
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
            'paidTotal' => (float) $sales->where('paid', true)->sum('total'),
            'unpaidTotal' => (float) $sales->where('paid', false)->sum('total'),
            'paidCount' => $sales->where('paid', true)->count(),
            'unpaidCount' => $sales->where('paid', false)->count(),
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
     * @return Collection<int, array{id: int, name: string, sku: ?string, quantity: int, revenue: float, cost: float, margin: float}>
     */
    private function productsReport(?string $from, ?string $to, ?string $productId): Collection
    {
        $rows = SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sale_items.product_id, products.name, products.sku,
                SUM(sale_items.quantity) as quantity, SUM(sale_items.total) as revenue,
                SUM(COALESCE(sale_items.cost, 0) * sale_items.quantity) as cost')
            ->when($from, fn (Builder $query) => $query->whereDate('sales.created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('sales.created_at', '<=', $to))
            ->when($productId, fn (Builder $query) => $query->where('sale_items.product_id', $productId))
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku')
            ->orderByDesc('quantity')
            ->get();

        return $rows->map(function ($row) {
            $cost = (float) $row->cost;

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
}
