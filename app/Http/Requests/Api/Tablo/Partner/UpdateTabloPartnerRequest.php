<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tabló partner frissítése validáció
 */
class UpdateTabloPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tablo_partners,slug,'.$id,
            'local_id' => 'nullable|string|max:255|unique:tablo_partners,local_id,'.$id,
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'A partner neve maximum 255 karakter lehet.',
            'slug.unique' => 'Ez a slug már foglalt.',
            'local_id.unique' => 'Ez a helyi azonosító már foglalt.',
        ];
    }
}
