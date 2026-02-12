<?php

namespace App\Http\Requests\Api\Partner;

use App\Enums\QrCodeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GenerateQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(QrCodeType::class)],
            'expires_at' => ['nullable', 'date'],
            'max_usages' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
