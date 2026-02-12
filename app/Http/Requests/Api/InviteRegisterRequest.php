<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class InviteRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'A meghívó kód megadása kötelező.',
            'name.required' => 'A név megadása kötelező.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
            'password.required' => 'A jelszó megadása kötelező.',
            'password.confirmed' => 'A jelszavak nem egyeznek.',
            'password.min' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.',
        ];
    }
}
