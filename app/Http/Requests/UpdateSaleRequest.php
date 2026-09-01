<?php

namespace App\Http\Requests;

use App\Models\Product;

class UpdateSaleRequest extends StoreSaleRequest
{
    /**
     * When editing a sale, the product stock already reflects the previously sold
     * quantity, so the available amount must be restored first.
     */
    protected function availableStock(Product $product): int
    {
        $sale = $this->route('sale');
        $previousQuantity = (int) $sale->items()
            ->where('product_id', $product->id)
            ->value('quantity');

        return $product->stock + $previousQuantity;
    }
}
