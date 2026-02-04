<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Iskola frissítése validáció
 */
class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'city' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Az iskola neve maximum 255 karakter lehet.',
            'city.max' => 'A város neve maximum 255 karakter lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'iskola neve',
            'city' => 'város',
        ];
    }
}
