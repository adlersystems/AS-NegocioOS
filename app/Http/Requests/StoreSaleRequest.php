<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'seller_id' => $this->user()?->canOverrideSeller()
                ? ['required', 'integer', 'exists:users,id']
                : ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Stock currently available for a product during this sale.
     */
    protected function availableStock(Product $product): int
    {
        return $product->stock;
    }

    /**
     * Quantity of a product already recorded on the sale being validated.
     *
     * New sales have no prior lines, so this defaults to zero. Sale edits
     * override it with the current saved quantity so an archived product line
     * may be retained or reduced but never increased.
     */
    protected function previousQuantity(Product $product): int
    {
        return 0;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = (array) $this->input('items');
            $ids = array_column($items, 'product_id');

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('items', __('app.sales.duplicate_product'));

                return;
            }

            $products = Product::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($items as $index => $row) {
                $product = $products[(int) $row['product_id']] ?? null;
                $quantity = (int) $row['quantity'];

                if (! $product || ! $product->is_active) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        __('app.sales.product_inactive', ['product' => $product?->name ?? $row['product_id']])
                    );

                    continue;
                }

                if ($product->trashed() && $quantity > $this->previousQuantity($product)) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        __('app.sales.product_archived', ['product' => $product->name])
                    );

                    continue;
                }

                if ($this->availableStock($product) < $quantity) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        __('app.sales.insufficient_stock', ['product' => $product->name, 'stock' => $this->availableStock($product)])
                    );
                }
            }
        });
    }
}
