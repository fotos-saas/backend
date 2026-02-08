<?php

namespace App\Http\Requests\Api\Partner;

use App\Models\PartnerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'service_type' => ['required', Rule::in(PartnerService::SERVICE_TYPES)],
            'default_price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'vat_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
