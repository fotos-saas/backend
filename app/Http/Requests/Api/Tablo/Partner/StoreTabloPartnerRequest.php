<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tabló partner létrehozása validáció
 */
class StoreTabloPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tablo_partners,slug',
            'local_id' => 'nullable|string|max:255|unique:tablo_partners,local_id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A partner neve kötelező.',
            'name.max' => 'A partner neve maximum 255 karakter lehet.',
            'slug.unique' => 'Ez a slug már foglalt.',
            'local_id.unique' => 'Ez a helyi azonosító már foglalt.',
        ];
    }
}
