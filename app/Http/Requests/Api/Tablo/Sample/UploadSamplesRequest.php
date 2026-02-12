<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Sample;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Minták feltöltése projekthez validáció
 */
class UploadSamplesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'samples' => 'required|array|min:1',
            'samples.*' => 'required|image|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'samples.required' => 'Legalább egy mintát fel kell tölteni.',
            'samples.min' => 'Legalább egy mintát fel kell tölteni.',
            'samples.*.image' => 'Csak képfájlok tölthetők fel.',
            'samples.*.max' => 'A kép maximális mérete 10MB.',
        ];
    }
}
