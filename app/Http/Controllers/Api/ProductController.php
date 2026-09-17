<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PaginatesToJson;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use PaginatesToJson;

    public function index(Request $request): JsonResponse
    {
        $canViewCosts = $request->user()->canViewCosts();

        $products = Product::query()
            ->search($request->string('search')?->toString())
            ->when($request->boolean('active'), fn (Builder $query) => $query->active())
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => collect($products->items())->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'min_stock' => $product->min_stock,
                'is_active' => (bool) $product->is_active,
                'expiration_date' => $product->expiration_date?->toDateString(),
                'sale_price' => (float) $product->sale_price,
                'low_stock' => $product->isLowStock(),
                'out_of_stock' => $product->isOutOfStock(),
                'expiring_soon' => $product->isExpiringSoon(),
                'expired' => $product->isExpired(),
            ] + ($canViewCosts ? [
                'production_cost' => (float) $product->production_cost,
                'margin' => $product->margin(),
            ] : []))->all(),
            'meta' => $this->meta($products),
        ]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $canViewCosts = $request->user()->canViewCosts();

        $movements = $product->inventoryMovements()
            ->with('user:id,name')
            ->latest()
            ->limit($request->integer('limit', 20))
            ->get();

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'min_stock' => $product->min_stock,
                'is_active' => (bool) $product->is_active,
                'expiration_date' => $product->expiration_date?->toDateString(),
                'sale_price' => (float) $product->sale_price,
                'low_stock' => $product->isLowStock(),
                'out_of_stock' => $product->isOutOfStock(),
                'recent_movements' => $movements->map(fn (InventoryMovement $movement) => [
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'reason' => $movement->reason,
                    'reference' => $movement->reference,
                    'user' => $movement->user?->name,
                    'created_at' => $movement->created_at->toIso8601String(),
                ])->all(),
            ] + ($canViewCosts ? [
                'production_cost' => (float) $product->production_cost,
                'margin' => $product->margin(),
                'stock_value' => (float) ($product->stock * $product->production_cost),
            ] : []),
        ]);
    }
}
