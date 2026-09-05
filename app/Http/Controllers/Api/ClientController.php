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

    public function index(Request $request): JsonResponse
    {
        $clients = Client::query()
            ->withCount('sales')
            ->withSum('sales', 'total')
            ->withSum(['sales as unpaid_total' => fn (Builder $query) => $query->unpaid()], 'total')
            ->search($request->string('search')?->toString())
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

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
        $client->loadSum(['sales as unpaid_total' => fn (Builder $query) => $query->unpaid()], 'total');

        $sales = $client->sales()
            ->with(['seller:id,name'])
            ->latest()
            ->limit($request->integer('limit', 20))
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
