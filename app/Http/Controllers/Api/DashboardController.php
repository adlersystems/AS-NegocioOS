<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $canViewCosts = $user->canViewCosts();

        $months = 6;
        $salesMonthly = [];
        $monthLabels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $monthLabels[] = ucfirst($month->translatedFormat('M'));
            $salesMonthly[] = Sale::query()
                ->visibleTo($user)
                ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->count();
        }

        $trendMonths = 12;
        $revenueTrend = [];
        $trendLabels = [];
        for ($i = $trendMonths - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $trendLabels[] = $month->translatedFormat('M y');
            $revenueTrend[] = (float) Sale::query()
                ->visibleTo($user)
                ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->sum('total');
        }

        $salesBySeller = $canViewCosts
            ? Sale::query()
                ->visibleTo($user)
                ->selectRaw('seller_id, SUM(total) as total')
                ->with('seller:id,name')
                ->groupBy('seller_id')
                ->orderByDesc('total')
                ->get()
            : collect();

        $topProducts = SaleItem::query()
            ->when($user->isSeller(), fn (Builder $q) => $q->whereHas('sale', fn (Builder $sq) => $sq->where('seller_id', $user->id)))
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(total) as revenue')
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('quantity')
            ->limit(6)
            ->get();

        $recentSales = Sale::query()
            ->visibleTo($user)
            ->with(['client:id,name', 'seller:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        $topClient = Sale::query()
            ->visibleTo($user)
            ->selectRaw('client_id, SUM(total) as total')
            ->whereNotNull('client_id')
            ->with('client:id,name')
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->first();

        $inventoryValue = $canViewCosts
            ? (float) Product::query()
                ->selectRaw('SUM(stock * production_cost) as value')
                ->value('value')
            : 0.0;

        return response()->json([
            'counts' => [
                'clients' => Client::count(),
                'products' => Product::count(),
                'sales' => Sale::query()->visibleTo($user)->count(),
                'receivables' => (float) Sale::query()->visibleTo($user)->unpaid()->whereNotNull('client_id')->sum('total'),
            ],
            'kpis' => [
                'today_revenue' => (float) Sale::query()->visibleTo($user)->whereDate('created_at', today())->sum('total'),
                'month_revenue' => (float) Sale::query()->visibleTo($user)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
                'arpu' => (float) (Sale::query()->visibleTo($user)->avg('total') ?? 0),
            ] + ($canViewCosts ? [
                'inventory_value' => $inventoryValue,
            ] : []),
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
                'top_products' => [
                    'labels' => $topProducts->map(fn ($item) => $item->product?->name ?? __('app.labels.none'))->all(),
                    'values' => $topProducts->map(fn ($item) => (int) $item->quantity)->all(),
                ],
            ] + ($canViewCosts ? [
                'by_seller' => [
                    'labels' => $salesBySeller->map(fn ($sale) => $sale->seller?->name ?? __('app.labels.none'))->all(),
                    'values' => $salesBySeller->map(fn ($sale) => (float) $sale->total)->all(),
                ],
            ] : []),
            'currency' => [
                'code' => Setting::get('currency', 'GTQ'),
                'symbol' => Setting::currencySymbol(),
            ],
        ]);
    }
}
