<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PaginatesToJson;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use PaginatesToJson;

    public function index(Request $request): JsonResponse
    {
        $movements = InventoryMovement::query()
            ->with(['product:id,name,sku', 'user:id,name'])
            ->when(
                $search = $request->string('search')?->toString(),
                fn (Builder $query) => $query->whereHas('product', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"))
            )
            ->when($request->string('type')?->toString(), fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($request->string('from')?->toString(), fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->string('to')?->toString(), fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => collect($movements->items())->map(fn (InventoryMovement $movement) => [
                'id' => $movement->id,
                'product_id' => $movement->product_id,
                'product' => $movement->product?->name,
                'sku' => $movement->product?->sku,
                'user' => $movement->user?->name,
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'reason' => $movement->reason,
                'reference' => $movement->reference,
                'created_at' => $movement->created_at->toIso8601String(),
            ])->all(),
            'meta' => $this->meta($movements),
        ]);
    }
}
