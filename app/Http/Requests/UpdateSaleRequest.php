<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Sale;

class UpdateSaleRequest extends StoreSaleRequest
{
    /**
     * When editing a sale, the product stock already reflects the previously sold
     * quantity, so the available amount must be restored first.
     */
    protected function availableStock(Product $product): int
    {
        return $product->stock + $this->previousQuantity($product);
    }

    /**
     * Quantity of the product already recorded on the sale being edited.
     */
    protected function previousQuantity(Product $product): int
    {
        $sale = $this->route('sale');

        if (! $sale instanceof Sale) {
            return 0;
        }

        return (int) $sale->items()
            ->where('product_id', $product->id)
            ->value('quantity');
    }
}
