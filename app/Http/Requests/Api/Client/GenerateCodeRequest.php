<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expires_at' => 'nullable|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'expires_at.date' => 'Érvénytelen dátum.',
            'expires_at.after' => 'A lejárati dátumnak a jövőben kell lennie.',
        ];
    }
}
