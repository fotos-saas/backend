<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Függő fotók törlése validáció
 */
class DeletePendingPhotosRequest extends FormRequest
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
        if ($this->has('media_ids') && is_array($this->media_ids)) {
            $this->merge([
                'media_ids' => array_map('intval', $this->media_ids),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'media_ids.required' => 'A törölni kívánt képek azonosítói kötelezőek.',
            'media_ids.array' => 'A képek azonosítóinak listának kell lennie.',
            'media_ids.min' => 'Legalább egy kép azonosító szükséges.',
            'media_ids.*.integer' => 'A kép azonosítóknak számoknak kell lennie.',
        ];
    }

    public function attributes(): array
    {
        return [
            'media_ids' => 'kép azonosítók',
            'media_ids.*' => 'kép azonosító',
        ];
    }
}
