<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|integer|exists:partner_clients,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:selection,tablo',
            'max_selections' => 'nullable|integer|min:1',
            'min_selections' => 'nullable|integer|min:1',
            'max_retouch_photos' => 'nullable|integer|min:1|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'Az ügyfél kiválasztása kötelező.',
            'client_id.exists' => 'A megadott ügyfél nem található.',
            'name.required' => 'Az album neve kötelező.',
            'name.max' => 'Az album neve maximum 255 karakter lehet.',
            'type.required' => 'Az album típusa kötelező.',
            'type.in' => 'Érvénytelen album típus. Használható: selection, tablo.',
            'max_selections.min' => 'A maximum kiválasztás minimum 1 lehet.',
            'min_selections.min' => 'A minimum kiválasztás minimum 1 lehet.',
            'max_retouch_photos.min' => 'A maximum retusálandó kép minimum 1 lehet.',
            'max_retouch_photos.max' => 'A maximum retusálandó kép maximum 20 lehet.',
        ];
    }
}
