<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportTeacherPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => 'required|integer|exists:tablo_schools,id',
            'names' => 'required_without:file|array|min:1|max:200',
            'names.*' => 'string|max:255',
            'file' => 'required_without:names|file|mimes:csv,xlsx,xls,txt|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.required' => 'Az iskola kiválasztása kötelező.',
            'school_id.exists' => 'A kiválasztott iskola nem létezik.',
            'names.required_without' => 'Adj meg tanárneveket vagy tölts fel egy fájlt.',
            'names.min' => 'Legalább egy nevet adj meg.',
            'names.max' => 'Egyszerre maximum 200 nevet adhatsz meg.',
            'names.*.max' => 'Egy név maximum 255 karakter lehet.',
            'file.required_without' => 'Adj meg tanárneveket vagy tölts fel egy fájlt.',
            'file.mimes' => 'A fájl típusa CSV, XLSX, XLS vagy TXT lehet.',
            'file.max' => 'A fájl maximum 2 MB lehet.',
        ];
    }
}
