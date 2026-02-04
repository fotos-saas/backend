<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Projekt létrehozása validáció
 */
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => 'nullable|integer|exists:tablo_schools,id',
            'class_name' => 'nullable|string|max:255',
            'class_year' => 'nullable|string|max:50',
            'photo_date' => 'nullable|date',
            'deadline' => 'nullable|date|after_or_equal:today',
            'expected_class_size' => 'nullable|integer|min:1|max:500',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
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
            'deadline.after_or_equal' => 'A határidő nem lehet a mai napnál korábbi.',
            'expected_class_size.integer' => 'A várható létszámnak számnak kell lennie.',
            'expected_class_size.min' => 'A várható létszám minimum 1 lehet.',
            'expected_class_size.max' => 'A várható létszám maximum 500 lehet.',
            'contact_name.max' => 'A kapcsolattartó neve maximum 255 karakter lehet.',
            'contact_email.email' => 'A kapcsolattartó email címe érvénytelen.',
            'contact_email.max' => 'A kapcsolattartó email címe maximum 255 karakter lehet.',
            'contact_phone.max' => 'A kapcsolattartó telefonszáma maximum 50 karakter lehet.',
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
            'contact_name' => 'kapcsolattartó neve',
            'contact_email' => 'kapcsolattartó email',
            'contact_phone' => 'kapcsolattartó telefon',
        ];
    }
}
