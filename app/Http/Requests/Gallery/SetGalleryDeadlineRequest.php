<?php

declare(strict_types=1);

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;

class SetGalleryDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'deadline.date' => 'A határidő érvényes dátum kell legyen.',
            'deadline.after_or_equal' => 'A határidő nem lehet múltbeli dátum.',
        ];
    }
}
