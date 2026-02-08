<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStripeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stripe_public_key' => ['nullable', 'string', 'starts_with:pk_'],
            'stripe_secret_key' => ['nullable', 'string', 'starts_with:sk_'],
            'stripe_webhook_secret' => ['nullable', 'string', 'starts_with:whsec_'],
            'stripe_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'stripe_public_key.starts_with' => 'A publikus kulcsnak pk_ előtaggal kell kezdődnie.',
            'stripe_secret_key.starts_with' => 'A titkos kulcsnak sk_ előtaggal kell kezdődnie.',
            'stripe_webhook_secret.starts_with' => 'A webhook secret-nek whsec_ előtaggal kell kezdődnie.',
        ];
    }
}
