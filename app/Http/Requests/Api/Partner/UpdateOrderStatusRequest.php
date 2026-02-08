<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:processing,shipped,completed,cancelled'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
