<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkImportStudentExecuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => 'required|integer|exists:tablo_schools,id',
            'items' => 'required|array|min:1|max:200',
            'items.*.input_name' => 'required|string|max:255',
            'items.*.action' => ['required', 'string', Rule::in(['create', 'update', 'skip'])],
            'items.*.student_id' => 'nullable|integer|exists:student_archive,id',
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.required' => 'Az iskola kiválasztása kötelező.',
            'school_id.exists' => 'A kiválasztott iskola nem létezik.',
            'items.required' => 'Legalább egy elemet kell megadni.',
            'items.min' => 'Legalább egy elemet kell megadni.',
            'items.max' => 'Egyszerre maximum 200 elemet adhatsz meg.',
            'items.*.input_name.required' => 'A név megadása kötelező.',
            'items.*.action.required' => 'A művelet kiválasztása kötelező.',
            'items.*.action.in' => 'Érvénytelen művelet. Lehetséges: létrehozás, frissítés, kihagyás.',
            'items.*.student_id.exists' => 'A megadott diák nem létezik.',
        ];
    }
}
