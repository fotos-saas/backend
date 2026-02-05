<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Marketer;

use Illuminate\Foundation\Http\FormRequest;

class AddContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'isPrimary' => 'nullable|boolean',
        ];
    }
}
