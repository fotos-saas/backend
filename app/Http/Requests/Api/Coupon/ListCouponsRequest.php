<?php

namespace App\Http\Requests\Api\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class ListCouponsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['sometimes', 'integer', 'exists:packages,id'],
            'work_session_id' => ['sometimes', 'integer', 'exists:work_sessions,id'],
        ];
    }
}
