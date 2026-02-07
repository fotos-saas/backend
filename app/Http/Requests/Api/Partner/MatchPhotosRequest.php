<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fotók AI párosítása validáció
 */
class MatchPhotosRequest extends FormRequest
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
        if ($this->has('photoIds') && is_array($this->photoIds)) {
            $this->merge([
                'photoIds' => array_map('intval', $this->photoIds),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'photoIds' => 'nullable|array',
            'photoIds.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'photoIds.array' => 'A kép azonosítóknak listának kell lennie.',
            'photoIds.*.integer' => 'A kép azonosítóknak számoknak kell lennie.',
        ];
    }

    public function attributes(): array
    {
        return [
            'photoIds' => 'kép azonosítók',
            'photoIds.*' => 'kép azonosító',
        ];
    }
}
