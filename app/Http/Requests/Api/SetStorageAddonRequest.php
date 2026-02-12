<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SetStorageAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gb' => 'required|integer|min:0|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'gb.required' => 'A GB érték megadása kötelező.',
            'gb.integer' => 'A GB értéknek egész számnak kell lennie.',
            'gb.min' => 'A GB érték nem lehet negatív.',
            'gb.max' => 'Maximum 500 GB extra tárhely vásárolható.',
        ];
    }
}
