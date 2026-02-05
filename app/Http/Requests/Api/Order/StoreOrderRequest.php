<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'work_session_id' => 'nullable|exists:work_sessions,id',
            'package_id' => 'nullable|exists:packages,id',
            'coupon_id' => 'nullable|exists:coupons,id',
            'coupon_discount' => 'nullable|integer|min:0',
            'subtotal_huf' => 'required|integer|min:0',
            'discount_huf' => 'required|integer|min:0',
            'total_gross_huf' => 'required|integer|min:175',
            'items' => 'required|array|min:1',
            'items.*.photo_id' => 'nullable|exists:photos,id',
            'items.*.size' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price_huf' => 'required|integer|min:0',
            'items.*.total_price_huf' => 'required|integer|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'package_point_id' => 'nullable|exists:package_points,id',
            'shipping_address' => 'nullable|string',
            'shipping_cost_huf' => 'required|integer|min:0',
            'cod_fee_huf' => 'nullable|integer|min:0',
            'is_company_purchase' => 'nullable|boolean',
            'company_name' => 'nullable|required_if:is_company_purchase,true|string|max:255',
            'tax_number' => 'nullable|required_if:is_company_purchase,true|string|max:50',
            'billing_address' => 'nullable|string',
        ];

        // Guest data validation if user is not authenticated
        if (!$this->user()) {
            $rules = array_merge($rules, [
                'guest_name' => 'required|string|max:255',
                'guest_email' => 'required|email|max:255',
                'guest_phone' => 'required|string|max:20',
                'guest_address' => 'required|string',
            ]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'total_gross_huf.min' => 'A végösszeg legalább 175 Ft kell legyen. Kérlek adj hozzá több tételt vagy módosítsd a kupont.',
        ];
    }
}
