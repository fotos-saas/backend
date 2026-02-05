<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Project;

use App\Enums\TabloProjectStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTabloProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'partner_id' => 'sometimes|exists:tablo_partners,id',
            'local_id' => 'nullable|string|max:255|unique:tablo_projects,local_id,' . $id,
            'external_id' => 'nullable|string|max:255|unique:tablo_projects,external_id,' . $id,
            'status' => 'nullable|in:' . implode(',', TabloProjectStatus::values()),
            'is_aware' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'partner_id.exists' => 'A megadott partner nem található.',
            'local_id.unique' => 'Ez a helyi azonosító már foglalt.',
            'external_id.unique' => 'Ez a külső azonosító már foglalt.',
        ];
    }
}
