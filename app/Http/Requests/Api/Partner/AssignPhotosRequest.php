<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fotók hozzárendelése személyekhez validáció
 */
class AssignPhotosRequest extends FormRequest
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
        if ($this->has('assignments') && is_array($this->assignments)) {
            $assignments = array_map(function ($assignment) {
                return [
                    'personId' => isset($assignment['personId']) ? (int) $assignment['personId'] : null,
                    'mediaId' => isset($assignment['mediaId']) ? (int) $assignment['mediaId'] : null,
                ];
            }, $this->assignments);

            $this->merge(['assignments' => $assignments]);
        }
    }

    public function rules(): array
    {
        return [
            'assignments' => 'required|array|min:1',
            'assignments.*.personId' => 'required|integer',
            'assignments.*.mediaId' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'assignments.required' => 'A hozzárendelések kötelezőek.',
            'assignments.array' => 'A hozzárendeléseknek listának kell lennie.',
            'assignments.min' => 'Legalább egy hozzárendelés szükséges.',
            'assignments.*.personId.required' => 'A személy azonosító kötelező.',
            'assignments.*.personId.integer' => 'A személy azonosítónak számnak kell lennie.',
            'assignments.*.mediaId.required' => 'A média azonosító kötelező.',
            'assignments.*.mediaId.integer' => 'A média azonosítónak számnak kell lennie.',
        ];
    }

    public function attributes(): array
    {
        return [
            'assignments' => 'hozzárendelések',
            'assignments.*.personId' => 'személy azonosító',
            'assignments.*.mediaId' => 'média azonosító',
        ];
    }
}
