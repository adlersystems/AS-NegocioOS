<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PaginatesToJson;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    use PaginatesToJson;

    public function index(Request $request): JsonResponse
    {
        $sales = Sale::query()
            ->with(['client:id,name,nit', 'seller:id,name'])
            ->search($request->string('search')?->toString())
            ->betweenDates($request->string('from')?->toString(), $request->string('to')?->toString())
            ->when($request->string('client_id')?->toString(), fn (Builder $query, string $id) => $query->where('client_id', $id))
            ->when($request->string('seller_id')?->toString(), fn (Builder $query, string $id) => $query->where('seller_id', $id))
            ->when($request->string('paid')?->toString() === 'paid', fn (Builder $query) => $query->paid())
            ->when($request->string('paid')?->toString() === 'pending', fn (Builder $query) => $query->unpaid())
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => collect($sales->items())->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoiceNumber(),
                'client' => $sale->client?->name,
                'client_nit' => $sale->client?->nit,
                'seller' => $sale->seller?->name,
                'subtotal' => (float) $sale->subtotal,
                'tax_amount' => (float) $sale->tax_amount,
                'total' => (float) $sale->total,
                'paid' => $sale->isPaid(),
                'created_at' => $sale->created_at->toIso8601String(),
            ])->all(),
            'meta' => $this->meta($sales),
        ]);
    }

    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['client', 'seller:id,name', 'items.product:id,name,sku']);

        return response()->json([
            'data' => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoiceNumber(),
                'client' => [
                    'id' => $sale->client?->id,
                    'name' => $sale->client?->name,
                    'nit' => $sale->client?->nit,
                    'email' => $sale->client?->email,
                ],
                'seller' => $sale->seller?->name,
                'subtotal' => (float) $sale->subtotal,
                'tax_amount' => (float) $sale->tax_amount,
                'total' => (float) $sale->total,
                'paid' => $sale->isPaid(),
                'notes' => $sale->notes,
                'created_at' => $sale->created_at->toIso8601String(),
                'items' => $sale->items->map(fn (SaleItem $item) => [
                    'product_id' => $item->product_id,
                    'product' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ])->all(),
            ],
        ]);
    }
}
