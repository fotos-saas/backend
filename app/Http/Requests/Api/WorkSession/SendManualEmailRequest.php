<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\WorkSession;

use Illuminate\Foundation\Http\FormRequest;

class SendManualEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => 'required|exists:email_templates,id',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'access_mode' => 'required|in:code,link,credentials,all',
        ];
    }

    public function messages(): array
    {
        return [
            'template_id.required' => 'Az email sablon kiválasztása kötelező.',
            'template_id.exists' => 'A megadott email sablon nem található.',
            'recipients.required' => 'Legalább egy címzett megadása kötelező.',
            'recipients.min' => 'Legalább egy címzettet meg kell adni.',
            'recipients.*.required' => 'A címzett email cím megadása kötelező.',
            'recipients.*.email' => 'Érvénytelen email cím formátum.',
            'access_mode.required' => 'A hozzáférési mód megadása kötelező.',
            'access_mode.in' => 'Érvénytelen hozzáférési mód.',
        ];
    }
}
