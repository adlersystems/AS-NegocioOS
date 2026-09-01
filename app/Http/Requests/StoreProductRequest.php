<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
            'production_cost' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.labels.name'),
            'description' => __('app.labels.description'),
            'sku' => __('app.labels.sku'),
            'stock' => __('app.labels.stock'),
            'min_stock' => __('app.labels.min_stock'),
            'expiration_date' => __('app.labels.expiration_date'),
            'production_cost' => __('app.labels.production_cost'),
            'sale_price' => __('app.labels.sale_price'),
            'is_active' => __('app.labels.is_active'),
        ];
    }
}
