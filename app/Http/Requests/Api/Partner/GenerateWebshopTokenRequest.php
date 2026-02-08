<?php

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

class GenerateWebshopTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'album_id' => ['nullable', 'integer'],
            'gallery_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->input('album_id') && !$this->input('gallery_id')) {
                $validator->errors()->add('album_id', 'album_id vagy gallery_id kötelező.');
            }
        });
    }
}
