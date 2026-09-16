<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const TYPES = [
        'sales',
        'inventory',
        'clients',
        'products',
    ];

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->string('type')?->toString();
        $type = in_array($type, self::TYPES, true) ? $type : 'sales';

        $from = $request->string('from')?->toString();
        $to = $request->string('to')?->toString();
        $clientId = $request->string('client_id')?->toString();
        $productId = $request->string('product_id')?->toString();
        $sellerId = $request->string('seller_id')?->toString();
        $paid = $request->string('paid')?->toString();

        $data = $this->reports->data($type, $from, $to, $clientId, $productId, $sellerId, $paid);

        return response()->json(match ($type) {
            'inventory' => [
                'type' => $type,
                'data' => $data->values(),
            ],
            'clients' => [
                'type' => $type,
                'count' => $data['count'],
                'total' => $data['total'],
                'data' => $data['clients']->map(fn ($client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'nit' => $client->nit,
                    'sales_count' => $client->sales_count,
                    'sales_total' => (float) $client->sales_total,
                ])->values(),
            ],
            'products' => [
                'type' => $type,
                'data' => $data->values(),
            ],
            default => [
                'type' => $type,
                'count' => $data['count'],
                'totals' => $data['totals'],
                'paid_count' => $data['paidCount'],
                'unpaid_count' => $data['unpaidCount'],
                'paid_total' => $data['paidTotal'],
                'unpaid_total' => $data['unpaidTotal'],
                'data' => $data['sales']->map(fn (Sale $sale) => [
                    'invoice_number' => $sale->invoiceNumber(),
                    'client' => $sale->buyerName(),
                    'client_nit' => $sale->buyerNit(),
                    'seller' => $sale->seller?->name,
                    'subtotal' => (float) $sale->subtotal,
                    'tax_amount' => (float) $sale->tax_amount,
                    'total' => (float) $sale->total,
                    'paid' => $sale->isPaid(),
                    'created_at' => $sale->created_at->toIso8601String(),
                ])->values(),
            ],
        });
    }
}
