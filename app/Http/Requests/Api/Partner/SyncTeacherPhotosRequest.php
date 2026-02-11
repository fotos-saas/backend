<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use App\Models\TabloProject;
use Illuminate\Foundation\Http\FormRequest;

class SyncTeacherPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schoolId = (int) $this->input('school_id');
        $partnerId = $this->user()?->tablo_partner_id;

        if (!$partnerId || !$schoolId) {
            return false;
        }

        // Van-e az iskola-partner kombinációhoz tartozó projekt?
        return TabloProject::where('partner_id', $partnerId)
            ->where('school_id', $schoolId)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:tablo_schools,id'],
            'class_year' => ['nullable', 'string', 'max:20'],
            'person_ids' => ['nullable', 'array'],
            'person_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.required' => 'Az iskola azonosító megadása kötelező.',
            'school_id.exists' => 'A megadott iskola nem létezik.',
        ];
    }
}
