<?php

declare(strict_types=1);

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;

class DownloadGalleryZipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_ids' => ['sometimes', 'array'],
            'person_ids.*' => ['integer'],
            'zip_content' => ['required', 'in:retouch_only,tablo_only,all,retouch_and_tablo'],
            'file_naming' => ['required', 'in:original,student_name,student_name_iptc'],
            'include_excel' => ['sometimes', 'boolean'],
            'person_type' => ['sometimes', 'nullable', 'in:student,teacher'],
            'effective_only' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'zip_content.required' => 'A ZIP tartalom típusa kötelező.',
            'zip_content.in' => 'Érvénytelen ZIP tartalom típus.',
            'file_naming.required' => 'A fájlnév stratégia kötelező.',
            'file_naming.in' => 'Érvénytelen fájlnév stratégia.',
        ];
    }
}
