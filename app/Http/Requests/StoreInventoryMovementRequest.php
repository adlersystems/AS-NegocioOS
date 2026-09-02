<?php

namespace App\Http\Requests;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryMovementRequest extends FormRequest
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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::in([InventoryMovement::TYPE_IN, InventoryMovement::TYPE_OUT])],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('type') !== InventoryMovement::TYPE_OUT) {
                return;
            }

            $product = Product::find((int) $this->input('product_id'));

            if ($product && $product->stock < (int) $this->input('quantity')) {
                $validator->errors()->add(
                    'quantity',
                    __('app.inventory.insufficient_stock', ['product' => $product->name, 'stock' => $product->stock])
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => __('app.labels.name'),
            'type' => __('app.labels.type'),
            'quantity' => __('app.labels.quantity'),
            'reason' => __('app.labels.reason'),
            'reference' => __('app.labels.reference'),
        ];
    }
}
