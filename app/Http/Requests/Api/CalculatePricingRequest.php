<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.photoId' => ['required', 'exists:photos,id'],
            'items.*.size' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
