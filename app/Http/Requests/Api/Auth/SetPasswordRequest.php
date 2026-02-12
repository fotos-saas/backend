<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'min:12',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'A jelszónak legalább 12 karakter hosszúnak kell lennie.',
            'password.regex' => 'A jelszónak tartalmaznia kell nagybetűt, kisbetűt, számot és speciális karaktert (@$!%*#?&).',
        ];
    }
}
