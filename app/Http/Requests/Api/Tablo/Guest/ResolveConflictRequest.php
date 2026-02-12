<?php

namespace App\Http\Requests\Api\Tablo\Guest;

use Illuminate\Foundation\Http\FormRequest;

class ResolveConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approve' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'approve.required' => 'A döntés megadása kötelező.',
        ];
    }
}
