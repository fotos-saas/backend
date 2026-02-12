<?php

namespace App\Http\Requests\Api\Tablo\Guest;

use Illuminate\Foundation\Http\FormRequest;

class SetClassSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_class_size' => 'required|integer|min:1|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'expected_class_size.required' => 'A létszám megadása kötelező.',
            'expected_class_size.min' => 'A létszám legalább 1 legyen.',
            'expected_class_size.max' => 'A létszám maximum 500 lehet.',
        ];
    }
}
