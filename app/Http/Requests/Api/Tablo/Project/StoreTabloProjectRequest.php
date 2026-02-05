<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Project;

use App\Enums\TabloProjectStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreTabloProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'partner_id' => 'required|exists:tablo_partners,id',
            'local_id' => 'nullable|string|max:255|unique:tablo_projects,local_id',
            'external_id' => 'nullable|string|max:255|unique:tablo_projects,external_id',
            'status' => 'nullable|in:' . implode(',', TabloProjectStatus::values()),
            'is_aware' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A projekt neve kötelező.',
            'partner_id.required' => 'A partner megadása kötelező.',
            'partner_id.exists' => 'A megadott partner nem található.',
            'local_id.unique' => 'Ez a helyi azonosító már foglalt.',
            'external_id.unique' => 'Ez a külső azonosító már foglalt.',
        ];
    }
}
