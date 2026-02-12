<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,archived,draft'],
            'flags' => ['nullable', 'array'],
            'flags.workflow' => ['nullable', 'string'],
            'flags.allowRetouch' => ['nullable', 'boolean'],
            'flags.allowGuestShare' => ['nullable', 'boolean'],
            'flags.enableCoupons' => ['nullable', 'boolean'],
            'flags.maxSelectable' => ['nullable', 'integer'],
            'flags.accessMode' => ['nullable', 'in:viewer,buyer,selector'],
        ];
    }
}
