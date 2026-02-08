<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebshopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'welcome_message' => ['nullable', 'string', 'max:2000'],
            'min_order_amount_huf' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'shipping_cost_huf' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'shipping_free_threshold_huf' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'allow_pickup' => ['sometimes', 'boolean'],
            'allow_shipping' => ['sometimes', 'boolean'],
            'terms_text' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'min_order_amount_huf.min' => 'A minimum rendelési összeg nem lehet negatív.',
            'shipping_cost_huf.min' => 'A szállítási költség nem lehet negatív.',
        ];
    }
}
