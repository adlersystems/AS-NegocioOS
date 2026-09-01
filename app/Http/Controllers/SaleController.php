<?php

namespace App\Http\Controllers;

use App\Exports\SalesExport;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')?->toString();
        $sellerId = $request->string('seller_id')?->toString();
        $from = $request->string('from')?->toString();
        $to = $request->string('to')?->toString();

        $sales = Sale::query()
            ->with(['client:id,name', 'seller:id,name'])
            ->search($search)
            ->when($sellerId, fn (Builder $query) => $query->where('seller_id', $sellerId))
            ->betweenDates($from, $to)
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('sales.index', [
            'sales' => $sales,
            'search' => $search,
            'sellerId' => $sellerId,
            'from' => $from,
            'to' => $to,
            'sellers' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('sales.form', [
            'sale' => new Sale,
            'products' => $this->sellableProducts(),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'sellers' => User::orderBy('name')->get(['id', 'name']),
            'iva' => (int) Setting::get('iva_percentage', 12),
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $sale = Sale::create([
            'client_id' => $data['client_id'] ?? null,
            'seller_id' => $data['seller_id'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->applyItems($sale, $data['items']);

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', __('app.flash.sale_registered'));
    }

    public function show(Sale $sale): View
    {
        return view('sales.show', [
            'sale' => $sale->load(['client', 'seller:id,name', 'items.product']),
        ]);
    }

    public function edit(Sale $sale): View
    {
        $sale->load('items');

        return view('sales.form', [
            'sale' => $sale,
            'products' => $this->sellableProducts($sale->items->pluck('product_id')->all()),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'sellers' => User::orderBy('name')->get(['id', 'name']),
            'iva' => (int) Setting::get('iva_percentage', 12),
        ]);
    }

    public function update(UpdateSaleRequest $request, Sale $sale): RedirectResponse
    {
        $data = $request->validated();

        $this->reconcileItems($sale, $data['items']);

        $sale->update([
            'client_id' => $data['client_id'] ?? null,
            'seller_id' => $data['seller_id'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', __('app.flash.updated', ['entity' => __('app.sales.singular')]));
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $invoice = $sale->invoiceNumber();

        foreach ($sale->items as $item) {
            $item->product?->increment('stock', $item->quantity);

            InventoryMovement::create([
                'product_id' => $item->product_id,
                'user_id' => auth()->id(),
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => $item->quantity,
                'reason' => __('app.sales.destroy_reason').' '.$invoice,
                'reference' => $invoice,
            ]);
        }

        $sale->delete();

        return redirect()
            ->route('sales.index')
            ->with('success', __('app.flash.deleted', ['entity' => __('app.sales.singular')]));
    }

    public function exportPdf(Sale $sale): Response
    {
        $sale->load(['client', 'seller', 'items.product']);

        $pdf = Pdf::loadView('sales.invoice-pdf', [
            'sale' => $sale,
            'iva' => (int) Setting::get('iva_percentage', 12),
            'company' => [
                'name' => Setting::get('company_name', 'AS-NegocioOS'),
                'nit' => Setting::get('nit'),
                'address' => Setting::get('address'),
                'phone' => Setting::get('phone'),
                'email' => Setting::get('email'),
            ],
        ])->setPaper('a4');

        return $pdf->download('factura-'.$sale->invoiceNumber().'.pdf');
    }

    public function exportListPdf(Request $request): Response
    {
        $sales = Sale::query()
            ->with(['client:id,name,nit', 'seller:id,name'])
            ->search($request->string('search')?->toString())
            ->when($request->string('seller_id')?->toString(), fn (Builder $query, string $sellerId) => $query->where('seller_id', $sellerId))
            ->betweenDates(
                $request->string('from')?->toString(),
                $request->string('to')?->toString(),
            )
            ->latest('id')
            ->get();

        $pdf = Pdf::loadView('sales.export-pdf', [
            'sales' => $sales,
            'company' => Setting::get('company_name', 'AS-NegocioOS'),
            'subtotal' => $sales->sum('subtotal'),
            'tax' => $sales->sum('tax_amount'),
            'total' => $sales->sum('total'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('ventas-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new SalesExport(
                $request->string('search')?->toString(),
                $request->string('seller_id')?->toString(),
                $request->string('from')?->toString(),
                $request->string('to')?->toString(),
            ),
            'ventas-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * Reconcile stock when a sale is edited: products that left the sale are
     * restored, quantity increases decrease stock and decreases restore it.
     * Items are rebuilt and totals recomputed from the authoritative prices.
     *
     * @param  array<int, array{product_id: string, quantity: string}>  $rows
     */
    private function reconcileItems(Sale $sale, array $rows): void
    {
        $iva = (int) Setting::get('iva_percentage', 12);
        $invoice = $sale->invoiceNumber();
        $reason = __('app.sales.edit_reason').' '.$invoice;

        $oldByProduct = $sale->items->keyBy('product_id');

        $newByProduct = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $newByProduct[$productId] = ($newByProduct[$productId] ?? 0) + (int) $row['quantity'];
        }

        $products = Product::withTrashed()
            ->whereIn('id', array_unique([
                ...$oldByProduct->keys()->all(),
                ...array_keys($newByProduct),
            ]))
            ->get()
            ->keyBy('id');

        foreach ($oldByProduct as $oldItem) {
            $productId = $oldItem->product_id;

            if (! isset($newByProduct[$productId])) {
                $products[$productId]->increment('stock', $oldItem->quantity);
                $this->logMovement($productId, InventoryMovement::TYPE_IN, $oldItem->quantity, $reason, $invoice);
            } elseif ($newByProduct[$productId] < $oldItem->quantity) {
                $difference = $oldItem->quantity - $newByProduct[$productId];
                $products[$productId]->increment('stock', $difference);
                $this->logMovement($productId, InventoryMovement::TYPE_IN, $difference, $reason, $invoice);
            } elseif ($newByProduct[$productId] > $oldItem->quantity) {
                $difference = $newByProduct[$productId] - $oldItem->quantity;
                $products[$productId]->decrement('stock', $difference);
                $this->logMovement($productId, InventoryMovement::TYPE_OUT, $difference, $reason, $invoice);
            }
        }

        foreach ($newByProduct as $productId => $quantity) {
            if (! $oldByProduct->has($productId)) {
                $products[$productId]->decrement('stock', $quantity);
                $this->logMovement($productId, InventoryMovement::TYPE_OUT, $quantity, $reason, $invoice);
            }
        }

        $sale->items()->delete();

        $created = [];
        foreach ($rows as $row) {
            $product = $products[(int) $row['product_id']];
            $quantity = (int) $row['quantity'];
            $price = (float) $product->sale_price;

            $created[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total' => round($price * $quantity, 2),
            ];
        }

        $sale->items()->createMany($created);

        $subtotal = round(array_sum(array_column($created, 'total')), 2);
        $tax = round($subtotal * ($iva / 100), 2);

        $sale->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => round($subtotal + $tax, 2),
        ])->save();
    }

    private function logMovement(int $productId, string $type, int $quantity, string $reason, string $reference): void
    {
        InventoryMovement::create([
            'product_id' => $productId,
            'user_id' => auth()->id(),
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'reference' => $reference,
        ]);
    }

    /**
     * Apply the sale items, adjusting stock, creating sale items and
     * logging the outgoing inventory movements for each sold product.
     *
     * @param  array<int, array{product_id: string, quantity: string}>  $rows
     */
    private function applyItems(Sale $sale, array $rows): void
    {
        $iva = (int) Setting::get('iva_percentage', 12);
        $subtotal = 0.0;

        $created = [];

        foreach ($rows as $row) {
            $product = Product::withTrashed()->find((int) $row['product_id']);
            $quantity = (int) $row['quantity'];

            $price = (float) $product->sale_price;
            $subtotal += $price * $quantity;

            $created[$product->id] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $price,
            ];
        }

        $sale->items()->createMany(array_map(
            fn (array $row) => [
                'product_id' => $row['product']->id,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'total' => round($row['unit_price'] * $row['quantity'], 2),
            ],
            $created
        ));

        foreach ($created as $row) {
            $row['product']->decrement('stock', $row['quantity']);

            InventoryMovement::create([
                'product_id' => $row['product']->id,
                'user_id' => auth()->id(),
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => $row['quantity'],
                'reason' => __('app.sales.sale_reason').' '.$sale->invoiceNumber(),
                'reference' => $sale->invoiceNumber(),
            ]);
        }

        $tax = round($subtotal * ($iva / 100), 2);

        $sale->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => round($subtotal + $tax, 2),
        ])->save();
    }

    /**
     * Products available for a new sale are the active, non-deleted ones.
     * When editing, the previously used products are included even if they
     * are now inactive or archived, so old sales can be sent.
     *
     * @param  array<int, int>  $includeIds
     * @return array<int, array{id: int, name: string, sku: ?string, price: float, stock: int}>
     */
    private function sellableProducts(array $includeIds = []): array
    {
        $query = Product::query()
            ->when($includeIds !== [], fn (Builder $query) => $query->withTrashed())
            ->where(function (Builder $query) use ($includeIds) {
                $query->active();

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            });

        return $query
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'sale_price', 'stock'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->sale_price,
                'stock' => $product->stock,
            ])
            ->values()
            ->all();
    }
}
