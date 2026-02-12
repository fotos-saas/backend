<?php

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTabloContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:50', 'regex:/^[\d\s\+\-\(\)]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A név megadása kötelező.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
            'phone.required' => 'A telefonszám megadása kötelező.',
            'phone.regex' => 'Érvénytelen telefonszám formátum.',
        ];
    }
}
