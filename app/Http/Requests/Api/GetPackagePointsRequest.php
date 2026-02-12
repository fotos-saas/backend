<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Csomagpontok lekérdezése validáció
 */
class GetPackagePointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'nullable|in:foxpost,packeta',
            'search' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'A szolgáltató csak foxpost vagy packeta lehet.',
        ];
    }
}
