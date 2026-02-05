<?php

namespace App\Http\Requests\Api\Tablo\Finalization;

use Illuminate\Foundation\Http\FormRequest;

class SaveDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'contactEmail' => 'nullable|email|max:255',
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'schoolName' => 'nullable|string|max:255',
            'schoolCity' => 'nullable|string|max:255',
            'className' => 'nullable|string|max:255',
            'classYear' => 'nullable|string|max:50',
            'quote' => 'nullable|string|max:1000',
            'fontFamily' => 'nullable|string|max:255',
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'description' => 'nullable|string|max:5000',
            'sortType' => 'nullable|string|in:abc,kozepre,megjegyzesben,mindegy',
            'studentDescription' => 'nullable|string',
            'teacherDescription' => 'nullable|string',
        ];
    }
}
