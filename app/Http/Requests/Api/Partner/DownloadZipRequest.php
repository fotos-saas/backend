<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class DownloadZipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_ids' => 'required|array|min:1',
            'photo_ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'photo_ids.required' => 'Legalább egy kép kiválasztása kötelező.',
            'photo_ids.min' => 'Legalább egy képet ki kell választani.',
            'photo_ids.*.integer' => 'Érvénytelen kép azonosító.',
        ];
    }
}
