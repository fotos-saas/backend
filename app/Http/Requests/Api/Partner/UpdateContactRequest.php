<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kontakt frissítése projektben validáció
 */
class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'isPrimary' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'A kapcsolattartó neve maximum 255 karakter lehet.',
            'email.email' => 'Az email cím formátuma érvénytelen.',
            'email.max' => 'Az email cím maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
            'isPrimary.boolean' => 'Az elsődleges mező csak igaz vagy hamis lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'név',
            'email' => 'email cím',
            'phone' => 'telefonszám',
            'isPrimary' => 'elsődleges kapcsolattartó',
        ];
    }
}
