<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PaginatesToJson;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    use PaginatesToJson;

    /**
     * Hard ceiling for client list sizes and nested sale listings.
     */
    private const MAX_ITEMS = 100;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $clients = Client::query()
            ->withCount(['sales as sales_count' => fn (Builder $query) => $query->visibleTo($user)])
            ->withSum(['sales as sales_total' => fn (Builder $query) => $query->visibleTo($user)], 'total')
            ->withSum(['sales as unpaid_total' => fn (Builder $query) => $query->unpaid()->visibleTo($user)], 'total')
            ->search($request->string('search')?->toString())
            ->latest('id')
            ->paginate(min($request->integer('per_page', 10), self::MAX_ITEMS));

        return response()->json([
            'data' => collect($clients->items())->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'nit' => $client->nit,
                'address' => $client->address,
                'preferred_language' => $client->preferred_language,
                'pending_balance' => $client->pending_balance,
                'sales_count' => $client->sales_count,
                'sales_total' => (float) $client->sales_total,
                'created_at' => $client->created_at->toIso8601String(),
            ])->all(),
            'meta' => $this->meta($clients),
        ]);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();

        $client->loadSum(['sales as unpaid_total' => fn (Builder $query) => $query->unpaid()->visibleTo($user)], 'total');

        $sales = $client->sales()
            ->visibleTo($user)
            ->with(['seller:id,name'])
            ->latest()
            ->limit(min($request->integer('limit', 20), self::MAX_ITEMS))
            ->get();

        return response()->json([
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'nit' => $client->nit,
                'address' => $client->address,
                'notes' => $client->notes,
                'preferred_language' => $client->preferred_language,
                'pending_balance' => $client->pending_balance,
                'created_at' => $client->created_at->toIso8601String(),
                'sales' => $sales->map(fn ($sale) => [
                    'invoice_number' => $sale->invoiceNumber(),
                    'total' => (float) $sale->total,
                    'paid' => $sale->isPaid(),
                    'seller' => $sale->seller?->name,
                    'created_at' => $sale->created_at->toIso8601String(),
                ])->all(),
            ],
        ]);
    }
}
