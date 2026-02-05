<?php

namespace App\Http\Requests\Api\Tablo\Guest;

use Illuminate\Foundation\Http\FormRequest;

class RegisterWithIdentificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => 'required|string|max:100|min:2',
            'person_id' => 'nullable|integer|exists:tablo_persons,id',
            'email' => 'required|email|max:255',
            'device_identifier' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.required' => 'A becenév megadása kötelező.',
            'nickname.min' => 'A becenév legalább 2 karakter legyen.',
            'person_id.exists' => 'A kiválasztott személy nem található.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
        ];
    }
}
