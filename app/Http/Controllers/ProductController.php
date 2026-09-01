<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')?->toString();
        $status = $request->string('status')?->toString();
        $stock = $request->string('stock')?->toString();

        $products = Product::query()
            ->withCount('saleItems')
            ->withSum('saleItems', 'quantity')
            ->search($search)
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($stock === 'low', fn (Builder $query) => $query->lowStock()->where('stock', '>', 0))
            ->when($stock === 'out', fn (Builder $query) => $query->outOfStock())
            ->when($stock === 'expiring', fn (Builder $query) => $query->expiringSoon())
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'status' => $status,
            'stock' => $stock,
        ]);
    }

    public function create(): View
    {
        return view('products.form', ['product' => new Product]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $product = Product::create($data);

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('app.flash.created', ['entity' => __('app.products.singular')]));
    }

    public function show(Product $product): View
    {
        $movements = $product->inventoryMovements()
            ->with('user:id,name')
            ->latest('id')
            ->limit(10)
            ->get();

        $unitsSold = (int) $product->saleItems()->sum('quantity');
        $stockValue = (float) $product->stock * (float) $product->production_cost;
        $margin = $product->margin();
        $marginRate = (float) $product->production_cost > 0
            ? ($margin / (float) $product->production_cost) * 100
            : 0.0;

        return view('products.show', [
            'product' => $product,
            'movements' => $movements,
            'unitsSold' => $unitsSold,
            'stockValue' => $stockValue,
            'marginRate' => $marginRate,
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.form', ['product' => $product]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $product->update($data);

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('app.flash.updated', ['entity' => __('app.products.singular')]));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', __('app.flash.deleted', ['entity' => __('app.products.singular')]));
    }
}
