<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array'],
            'products.*.id' => ['required', 'integer'],
            'products.*.price_huf' => ['required', 'integer', 'min:0'],
            'products.*.is_active' => ['required', 'boolean'],
        ];
    }
}
