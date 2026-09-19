<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $canViewCosts = $user->canViewCosts();

        $months = 6;

        $salesMonthly = [];
        $monthLabels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $monthLabels[] = ucfirst($month->translatedFormat('M'));
            $salesMonthly[] = Sale::query()
                ->visibleTo($user)
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
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
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
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
            ? Product::query()
                ->selectRaw('SUM(stock * production_cost) as value')
                ->value('value')
            : 0;

        return view('dashboard.index', [
            'counts' => [
                'clients' => Client::count(),
                'products' => Product::count(),
                'sales' => Sale::query()->visibleTo($user)->count(),
                'receivables' => Setting::formatMoney((float) Sale::query()->visibleTo($user)->unpaid()->whereNotNull('client_id')->sum('total')),
            ],
            'kpis' => [
                'today_revenue' => Setting::formatMoney(Sale::query()->visibleTo($user)->whereDate('created_at', today())->sum('total')),
                'month_revenue' => Setting::formatMoney(Sale::query()->visibleTo($user)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total')),
                'arpu' => Setting::formatMoney((float) (Sale::query()->visibleTo($user)->avg('total') ?? 0)),
            ] + ($canViewCosts ? [
                'inventory_value' => Setting::formatMoney((float) $inventoryValue),
            ] : []),
            'alerts' => [
                'low_stock' => Product::lowStock()->where('stock', '>', 0)->count(),
                'out_of_stock' => Product::where('stock', '<=', 0)->count(),
                'expiring_soon' => Product::expiringSoon()->count(),
            ],
            'topClient' => $topClient ? [
                'name' => $topClient->buyerName() ?? __('app.labels.none'),
                'total' => Setting::formatMoney($topClient->total),
            ] : null,
            'recentSales' => $recentSales,
            'topProducts' => $topProducts,
            'canViewCosts' => $canViewCosts,
            'charts' => [
                'salesMonthly' => [
                    'labels' => $monthLabels,
                    'values' => $salesMonthly,
                ],
                'revenueTrend' => [
                    'labels' => $trendLabels,
                    'values' => $revenueTrend,
                ],
                'topProducts' => [
                    'labels' => $topProducts->map(fn ($p) => $p->product?->name ?? __('app.labels.none'))->all(),
                    'values' => $topProducts->map(fn ($p) => (int) $p->quantity)->all(),
                ],
            ] + ($canViewCosts ? [
                'bySeller' => [
                    'labels' => $salesBySeller->map(fn ($s) => $s->seller?->name ?? __('app.labels.none'))->all(),
                    'values' => $salesBySeller->map(fn ($s) => (float) $s->total)->all(),
                ],
            ] : []),
        ]);
    }
}
