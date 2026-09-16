<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $months = 6;
        $salesMonthly = [];
        $monthLabels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $monthLabels[] = ucfirst($month->translatedFormat('M'));
            $salesMonthly[] = Sale::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->count();
        }

        $trendMonths = 12;
        $revenueTrend = [];
        $trendLabels = [];
        for ($i = $trendMonths - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $trendLabels[] = $month->translatedFormat('M y');
            $revenueTrend[] = (float) Sale::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->sum('total');
        }

        $salesBySeller = Sale::query()
            ->selectRaw('seller_id, SUM(total) as total')
            ->with('seller:id,name')
            ->groupBy('seller_id')
            ->orderByDesc('total')
            ->get();

        $topProducts = SaleItem::query()
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(total) as revenue')
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('quantity')
            ->limit(6)
            ->get();

        $recentSales = Sale::query()
            ->with(['client:id,name', 'seller:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        $topClient = Sale::query()
            ->selectRaw('client_id, SUM(total) as total')
            ->whereNotNull('client_id')
            ->with('client:id,name')
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->first();

        $inventoryValue = (float) Product::query()
            ->selectRaw('SUM(stock * production_cost) as value')
            ->value('value');

        return response()->json([
            'counts' => [
                'clients' => Client::count(),
                'products' => Product::count(),
                'sales' => Sale::count(),
                'receivables' => (float) Sale::unpaid()->whereNotNull('client_id')->sum('total'),
            ],
            'kpis' => [
                'today_revenue' => (float) Sale::whereDate('created_at', today())->sum('total'),
                'month_revenue' => (float) Sale::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
                'arpu' => (float) (Sale::avg('total') ?? 0),
                'inventory_value' => $inventoryValue,
            ],
            'alerts' => [
                'low_stock' => Product::lowStock()->where('stock', '>', 0)->count(),
                'out_of_stock' => Product::where('stock', '<=', 0)->count(),
                'expiring_soon' => Product::expiringSoon()->count(),
            ],
            'top_client' => $topClient?->client ? [
                'name' => $topClient->client->name,
                'total' => (float) $topClient->total,
            ] : null,
            'recent_sales' => $recentSales->map(fn (Sale $sale) => [
                'invoice_number' => $sale->invoiceNumber(),
                'client' => $sale->buyerName(),
                'seller' => $sale->seller?->name,
                'total' => (float) $sale->total,
                'paid' => $sale->isPaid(),
                'created_at' => $sale->created_at->toIso8601String(),
            ])->values(),
            'charts' => [
                'sales_monthly' => ['labels' => $monthLabels, 'values' => $salesMonthly],
                'revenue_trend' => ['labels' => $trendLabels, 'values' => $revenueTrend],
                'by_seller' => [
                    'labels' => $salesBySeller->map(fn ($sale) => $sale->seller?->name ?? __('app.labels.none'))->all(),
                    'values' => $salesBySeller->map(fn ($sale) => (float) $sale->total)->all(),
                ],
                'top_products' => [
                    'labels' => $topProducts->map(fn ($item) => $item->product?->name ?? __('app.labels.none'))->all(),
                    'values' => $topProducts->map(fn ($item) => (int) $item->quantity)->all(),
                ],
            ],
            'currency' => [
                'code' => Setting::get('currency', 'GTQ'),
                'symbol' => Setting::currencySymbol(),
            ],
        ]);
    }
}
