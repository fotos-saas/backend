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
            'samples.*.url' => [
                'required',
                'url',
                'regex:/^https?:\/\//',
                function ($attribute, $value, $fail) {
                    $host = parse_url($value, PHP_URL_HOST);
                    if (! $host) {
                        $fail('Érvénytelen URL.');

                        return;
                    }
                    $ip = gethostbyname($host);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                        $fail('Belső hálózati címek nem engedélyezettek.');
                    }
                },
            ],
            'samples.*.name' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'samples.required' => 'A minták tömb megadása kötelező.',
            'samples.*.url.required' => 'Minden mintához URL megadása kötelező.',
            'samples.*.url.url' => 'Érvényes URL-t kell megadni.',
            'samples.*.url.regex' => 'Csak HTTP/HTTPS protokoll engedélyezett.',
            'samples.*.name.max' => 'A minta neve maximum 255 karakter lehet.',
        ];
    }
}
