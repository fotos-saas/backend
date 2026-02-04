<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fotók hozzárendelése talonhoz validáció
 */
class AssignToTalonRequest extends FormRequest
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
        if ($this->has('mediaIds') && is_array($this->mediaIds)) {
            $this->merge([
                'mediaIds' => array_map('intval', $this->mediaIds),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'mediaIds' => 'required|array|min:1',
            'mediaIds.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'mediaIds.required' => 'A média azonosítók kötelezőek.',
            'mediaIds.array' => 'A média azonosítóknak listának kell lennie.',
            'mediaIds.min' => 'Legalább egy média azonosító szükséges.',
            'mediaIds.*.integer' => 'A média azonosítóknak számoknak kell lennie.',
        ];
    }

    public function attributes(): array
    {
        return [
            'mediaIds' => 'média azonosítók',
            'mediaIds.*' => 'média azonosító',
        ];
    }
}
