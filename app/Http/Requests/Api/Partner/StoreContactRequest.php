<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kontakt létrehozása validáció (inline projektben)
 */
class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A kapcsolattartó neve kötelező.',
            'name.max' => 'A kapcsolattartó neve maximum 255 karakter lehet.',
            'email.email' => 'Az email cím formátuma érvénytelen.',
            'email.max' => 'Az email cím maximum 255 karakter lehet.',
            'phone.max' => 'A telefonszám maximum 50 karakter lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'név',
            'email' => 'email cím',
            'phone' => 'telefonszám',
        ];
    }
}
