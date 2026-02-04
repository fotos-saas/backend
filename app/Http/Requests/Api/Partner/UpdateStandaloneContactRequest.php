<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Önálló kontakt frissítése validáció
 */
class UpdateStandaloneContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FormData stringeket intval-ra konvertálunk
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('project_id') && $this->project_id !== null) {
            $this->merge([
                'project_id' => (int) $this->project_id,
            ]);
        }

        if ($this->has('project_ids') && is_array($this->project_ids)) {
            $this->merge([
                'project_ids' => array_map('intval', $this->project_ids),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'project_id' => 'nullable|integer|exists:tablo_projects,id',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:tablo_projects,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'A kapcsolattartó neve maximum 255 karakter lehet.',
            'email.email' => 'Az email cím formátuma érvénytelen.',
            'email.max' => 'Az email cím maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'note.max' => 'A megjegyzés maximum 1000 karakter lehet.',
            'project_id.exists' => 'A megadott projekt nem található.',
            'project_ids.array' => 'A projekt azonosítók listának kell lennie.',
            'project_ids.*.exists' => 'Egy vagy több megadott projekt nem található.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'név',
            'email' => 'email cím',
            'phone' => 'telefonszám',
            'note' => 'megjegyzés',
            'project_id' => 'projekt',
            'project_ids' => 'projektek',
        ];
    }
}
