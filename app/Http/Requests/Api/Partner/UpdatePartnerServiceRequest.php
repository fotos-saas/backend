<?php

namespace App\Http\Requests\Api\Partner;

use App\Models\PartnerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'service_type' => ['sometimes', Rule::in(PartnerService::SERVICE_TYPES)],
            'default_price' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'vat_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
