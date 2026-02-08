<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use App\Enums\InvoiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(InvoiceType::values())],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'fulfillment_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_tax_number' => ['nullable', 'string', 'max:30'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
            'tablo_project_id' => ['nullable', 'integer', 'exists:tablo_projects,id'],
            'tablo_contact_id' => ['nullable', 'integer', 'exists:tablo_contacts,id'],
            'sync_immediately' => ['boolean'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'A számla típus megadása kötelező.',
            'type.in' => 'Érvénytelen számla típus.',
            'issue_date.required' => 'A kiállítás dátuma kötelező.',
            'due_date.required' => 'A fizetési határidő kötelező.',
            'due_date.after_or_equal' => 'A fizetési határidő nem lehet korábbi a kiállítás dátumánál.',
            'fulfillment_date.required' => 'A teljesítés dátuma kötelező.',
            'customer_name.required' => 'A vevő neve kötelező.',
            'customer_email.email' => 'Érvénytelen email cím.',
            'items.required' => 'Legalább egy tétel megadása kötelező.',
            'items.min' => 'Legalább egy tétel megadása kötelező.',
            'items.*.name.required' => 'A tétel megnevezése kötelező.',
            'items.*.quantity.required' => 'A mennyiség megadása kötelező.',
            'items.*.quantity.min' => 'A mennyiség minimum 0.01.',
            'items.*.unit_price.required' => 'Az egységár megadása kötelező.',
            'items.*.unit_price.min' => 'Az egységár nem lehet negatív.',
        ];
    }
}
