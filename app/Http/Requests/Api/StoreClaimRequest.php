<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'classCode' => 'nullable|string|max:50',
            'photoIds' => 'required|array',
            'photoIds.*' => 'integer|exists:photos,id',
        ];
    }
}
