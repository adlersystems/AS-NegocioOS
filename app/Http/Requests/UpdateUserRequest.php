<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getUserId(): int
    {
        $bound = $this->route('user');

        return $bound instanceof User ? (int) $bound->getKey() : (int) $bound;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->getUserId())],
            'role' => ['required', Rule::in(User::ROLES)],
            'language' => ['required', Rule::in(['es', 'en'])],
            'password' => ['nullable', 'string', Password::min(8)],
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return (new StoreUserRequest)->attributes();
    }
}
