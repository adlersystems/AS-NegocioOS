<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getClientId(): int
    {
        $bound = $this->route('client');

        return $bound instanceof Client ? (int) $bound->getKey() : (int) $bound;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($this->getClientId())],
            'nit' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'nit')->ignore($this->getClientId())],
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
        return (new StoreClientRequest)->attributes();
    }
}
