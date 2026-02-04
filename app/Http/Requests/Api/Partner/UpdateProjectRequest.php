<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Projekt frissítése validáció
 */
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => 'sometimes|nullable|integer|exists:tablo_schools,id',
            'class_name' => 'sometimes|nullable|string|max:255',
            'class_year' => 'sometimes|nullable|string|max:50',
            'photo_date' => 'sometimes|nullable|date',
            'deadline' => 'sometimes|nullable|date',
            'expected_class_size' => 'sometimes|nullable|integer|min:1|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.exists' => 'A megadott iskola nem található.',
            'school_id.integer' => 'Az iskola azonosító számnak kell lennie.',
            'class_name.max' => 'Az osztály neve maximum 255 karakter lehet.',
            'class_year.max' => 'Az évfolyam maximum 50 karakter lehet.',
            'photo_date.date' => 'A fotózás dátuma érvénytelen formátumú.',
            'deadline.date' => 'A határidő érvénytelen formátumú.',
            'expected_class_size.integer' => 'A várható létszámnak számnak kell lennie.',
            'expected_class_size.min' => 'A várható létszám minimum 1 lehet.',
            'expected_class_size.max' => 'A várható létszám maximum 500 lehet.',
        ];
    }

    public function attributes(): array
    {
        return [
            'school_id' => 'iskola',
            'class_name' => 'osztály neve',
            'class_year' => 'évfolyam',
            'photo_date' => 'fotózás dátuma',
            'deadline' => 'határidő',
            'expected_class_size' => 'várható létszám',
        ];
    }
}
