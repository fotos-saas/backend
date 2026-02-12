<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => 'required|string|in:alap,iskola,studio',
            'billing_cycle' => 'sometimes|string|in:monthly,yearly',
        ];
    }
}
