<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Project;

use Illuminate\Foundation\Http\FormRequest;

class SyncStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fotocms_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'status_id' => 'required|integer|min:1|max:13',
        ];
    }

    public function messages(): array
    {
        return [
            'status_id.required' => 'A státusz ID megadása kötelező.',
            'status_id.min' => 'A státusz ID minimum 1 lehet.',
            'status_id.max' => 'A státusz ID maximum 13 lehet.',
        ];
    }
}
