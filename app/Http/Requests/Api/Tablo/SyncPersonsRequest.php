<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class SyncPersonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|integer',
            'persons' => 'required|array',
            'persons.*.name' => 'required|string|max:255',
            'persons.*.local_id' => 'required|string|max:255',
            'persons.*.type' => 'nullable|string|in:student,teacher',
            'persons.*.position' => 'nullable|integer',
            'persons.*.note' => 'nullable|string',
        ];
    }
}
