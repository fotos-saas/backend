<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo\Sample;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Minták szinkronizálása külső URL-ről validáció
 */
class SyncSamplesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fotocms_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'samples' => 'required|array',
            'samples.*.url' => 'required|url',
            'samples.*.name' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'samples.required' => 'A minták tömb megadása kötelező.',
            'samples.*.url.required' => 'Minden mintához URL megadása kötelező.',
            'samples.*.url.url' => 'Érvényes URL-t kell megadni.',
            'samples.*.name.max' => 'A minta neve maximum 255 karakter lehet.',
        ];
    }
}
