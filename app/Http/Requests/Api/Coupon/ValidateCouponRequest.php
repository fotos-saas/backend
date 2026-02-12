<?php

namespace App\Http\Requests\Api\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_total' => ['sometimes', 'numeric', 'min:0'],
            'album_id' => ['sometimes', 'integer', 'exists:albums,id'],
            'package_id' => ['sometimes', 'integer', 'exists:packages,id'],
            'work_session_id' => ['sometimes', 'integer', 'exists:work_sessions,id'],
        ];
    }
}
