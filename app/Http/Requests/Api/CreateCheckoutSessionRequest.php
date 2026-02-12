<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'billing.company_name' => ['required', 'string', 'max:255'],
            'billing.tax_number' => ['nullable', 'string', 'max:50'],
            'billing.country' => ['required', 'string', 'max:100'],
            'billing.postal_code' => ['required', 'string', 'max:10'],
            'billing.city' => ['required', 'string', 'max:100'],
            'billing.address' => ['required', 'string', 'max:255'],
            'billing.phone' => ['required', 'string', 'max:50'],
            'plan' => ['required', 'string', 'in:alap,iskola,studio'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
            'is_desktop' => ['nullable', 'boolean'],
        ];
    }
}
