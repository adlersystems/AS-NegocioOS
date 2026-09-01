<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')],
            'nit' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'nit')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'preferred_language' => ['nullable', Rule::in(['es', 'en'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.labels.name'),
            'email' => __('app.labels.email'),
            'nit' => __('app.labels.nit'),
            'phone' => __('app.labels.phone'),
            'address' => __('app.labels.address'),
            'preferred_language' => __('app.clients.language'),
            'notes' => __('app.labels.notes'),
        ];
    }
}
