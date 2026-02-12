<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\WorkSession;

use Illuminate\Foundation\Http\FormRequest;

class DownloadManagerZipAsyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'photo_type' => 'required|in:claimed,retus,tablo',
            'filename_mode' => 'required|in:original,user_name,original_exif',
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Legalább egy felhasználó kiválasztása kötelező.',
            'user_ids.min' => 'Legalább egy felhasználót ki kell választani.',
            'user_ids.*.exists' => 'Az egyik megadott felhasználó nem található.',
            'photo_type.required' => 'A fotó típus megadása kötelező.',
            'photo_type.in' => 'Érvénytelen fotó típus.',
            'filename_mode.required' => 'A fájlnév mód megadása kötelező.',
            'filename_mode.in' => 'Érvénytelen fájlnév mód.',
        ];
    }
}
