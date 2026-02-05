<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'local_id' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ];
    }
}
