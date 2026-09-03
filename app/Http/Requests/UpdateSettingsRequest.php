<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:130'],
            'nit' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'currency' => ['required', 'string', 'in:GTQ,USD'],
            'iva_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_language' => ['required', 'in:es,en'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => __('app.settings.company_name'),
            'nit' => __('app.labels.nit'),
            'address' => __('app.labels.address'),
            'phone' => __('app.labels.phone'),
            'email' => __('app.labels.email'),
            'logo' => __('app.settings.logo'),
            'currency' => __('app.settings.currency'),
            'iva_percentage' => __('app.settings.iva_percentage'),
            'default_language' => __('app.settings.default_language'),
        ];
    }
}
