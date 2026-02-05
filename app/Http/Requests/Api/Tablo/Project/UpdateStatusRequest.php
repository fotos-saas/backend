<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Project;

use App\Enums\TabloProjectStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:' . implode(',', TabloProjectStatus::values()),
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'A státusz megadása kötelező.',
            'status.in' => 'Érvénytelen státusz érték.',
        ];
    }
}
