<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tablo;

use Illuminate\Foundation\Http\FormRequest;

class BatchStorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'persons' => 'required|array|min:1',
            'persons.*.name' => 'required|string|max:255',
            'persons.*.local_id' => 'nullable|string|max:255',
            'persons.*.note' => 'nullable|string',
        ];
    }
}
