<?php

namespace App\Http\Controllers;

use App\Exports\InventoryMovementsExport;
use App\Http\Requests\StoreInventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')?->toString();
        $type = $request->string('type')?->toString();
        $from = $request->string('from')?->toString();
        $to = $request->string('to')?->toString();

        $movements = $this->filteredMovements($request)
            ->with(['product:id,name,sku', 'user:id,name'])
            ->paginate(15)
            ->withQueryString();

        return view('inventory.index', [
            'movements' => $movements,
            'search' => $search,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'entries' => (int) InventoryMovement::query()->where('type', InventoryMovement::TYPE_IN)->sum('quantity'),
            'exits' => (int) InventoryMovement::query()->where('type', InventoryMovement::TYPE_OUT)->sum('quantity'),
            'lowStock' => Product::lowStock()->where('stock', '>', 0)->count(),
            'outOfStock' => Product::outOfStock()->count(),
        ]);
    }

    public function create(): View
    {
        return view('inventory.create', [
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    public function store(StoreInventoryMovementRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $product = Product::whereKey((int) $data['product_id'])->lockForUpdate()->first();
            $quantity = (int) $data['quantity'];
            $type = $data['type'];

            if ($type === InventoryMovement::TYPE_OUT && $product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('app.inventory.insufficient_stock', [
                        'product' => $product->name,
                        'stock' => $product->stock,
                    ]),
                ]);
            }

            if ($type === InventoryMovement::TYPE_IN) {
                $product->increment('stock', $quantity);
            } else {
                $product->decrement('stock', $quantity);
            }

            InventoryMovement::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity' => $quantity,
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
            ]);
        });

        return redirect()
            ->route('inventory.index')
            ->with('success', __('app.flash.stock_updated'));
    }

    public function exportPdf(Request $request): Response
    {
        $movements = $this->filteredMovements($request)->with('user:id,name')->get();

        $pdf = Pdf::loadView('inventory.export-pdf', [
            'movements' => $movements,
            'entries' => $movements->where('type', InventoryMovement::TYPE_IN)->sum('quantity'),
            'exits' => $movements->where('type', InventoryMovement::TYPE_OUT)->sum('quantity'),
            'company' => Setting::get('company_name', 'AS-NegocioOS'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('inventario-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new InventoryMovementsExport(
                $request->string('search')?->toString(),
                $request->string('type')?->toString(),
                $request->string('from')?->toString(),
                $request->string('to')?->toString(),
            ),
            'inventario-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    private function filteredMovements(Request $request): Builder
    {
        $search = $request->string('search')?->toString();
        $type = $request->string('type')?->toString();
        $from = $request->string('from')?->toString();
        $to = $request->string('to')?->toString();

        return InventoryMovement::query()
            ->when($search, fn (Builder $query) => $query->whereHas(
                'product',
                fn (Builder $product) => $product->search($search)
            ))
            ->when($type === InventoryMovement::TYPE_IN, fn (Builder $query) => $query->where('type', InventoryMovement::TYPE_IN))
            ->when($type === InventoryMovement::TYPE_OUT, fn (Builder $query) => $query->where('type', InventoryMovement::TYPE_OUT))
            ->when($from, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('created_at', '<=', $to))
            ->latest('id');
    }
}
