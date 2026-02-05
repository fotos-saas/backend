<?php

namespace App\Http\Requests\Api\Tablo\Guest;

use Illuminate\Foundation\Http\FormRequest;

class RegisterGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_name' => 'required|string|max:100|min:2',
            'guest_email' => 'nullable|email|max:255',
            'device_identifier' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'guest_name.required' => 'A név megadása kötelező.',
            'guest_name.min' => 'A név legalább 2 karakter legyen.',
            'guest_email.email' => 'Érvénytelen email cím.',
        ];
    }
}
