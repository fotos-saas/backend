<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'allow_registration' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'A név maximum 255 karakter lehet.',
            'email.email' => 'Érvénytelen email cím.',
            'email.max' => 'Az email maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
        ];
    }
}
