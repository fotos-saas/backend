<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class ImportContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Kérjük válasszon ki egy Excel fájlt.',
            'file.file' => 'Érvénytelen fájl.',
            'file.mimes' => 'Csak .xlsx és .xls fájlok engedélyezettek.',
            'file.max' => 'A fájl mérete nem haladhatja meg az 5 MB-ot.',
        ];
    }
}
