<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use App\Enums\InvoicingProviderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_provider' => ['required', Rule::in(InvoicingProviderType::values())],
            'invoice_enabled' => ['required', 'boolean'],
            'invoice_api_key' => ['nullable', 'string', 'max:500'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'invoice_currency' => ['required', 'string', 'size:3'],
            'invoice_language' => ['required', 'string', 'size:2'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'invoice_vat_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_comment' => ['nullable', 'string', 'max:1000'],
            'invoice_eu_vat' => ['required', 'boolean'],

            // Számlázz.hu specifikus
            'szamlazz_bank_name' => ['nullable', 'required_if:invoice_provider,szamlazz_hu', 'string', 'max:100'],
            'szamlazz_bank_account' => ['nullable', 'required_if:invoice_provider,szamlazz_hu', 'string', 'max:50'],
            'szamlazz_reply_email' => ['nullable', 'email', 'max:100'],

            // Billingo specifikus
            'billingo_block_id' => ['nullable', 'required_if:invoice_provider,billingo', 'string', 'max:50'],
            'billingo_bank_account_id' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_provider.required' => 'A számlázási szolgáltató megadása kötelező.',
            'invoice_provider.in' => 'Érvénytelen számlázási szolgáltató.',
            'invoice_enabled.required' => 'A számlázás állapot megadása kötelező.',
            'invoice_api_key.max' => 'Az API kulcs maximum 500 karakter lehet.',
            'invoice_prefix.required' => 'A számla előtag megadása kötelező.',
            'invoice_prefix.max' => 'A számla előtag maximum 20 karakter lehet.',
            'invoice_currency.required' => 'A pénznem megadása kötelező.',
            'invoice_currency.size' => 'A pénznem pontosan 3 karakter legyen (pl. HUF).',
            'invoice_language.required' => 'A nyelv megadása kötelező.',
            'invoice_due_days.required' => 'A fizetési határidő megadása kötelező.',
            'invoice_due_days.min' => 'A fizetési határidő minimum 0 nap.',
            'invoice_due_days.max' => 'A fizetési határidő maximum 365 nap.',
            'invoice_vat_percentage.required' => 'Az ÁFA kulcs megadása kötelező.',
            'invoice_vat_percentage.min' => 'Az ÁFA kulcs minimum 0%.',
            'invoice_vat_percentage.max' => 'Az ÁFA kulcs maximum 100%.',
            'szamlazz_bank_name.required_if' => 'A bank neve kötelező Számlázz.hu használat esetén.',
            'szamlazz_bank_account.required_if' => 'A bankszámlaszám kötelező Számlázz.hu használat esetén.',
            'szamlazz_reply_email.email' => 'Érvénytelen válaszcím.',
            'billingo_block_id.required_if' => 'A számlatömb azonosító kötelező Billingo használat esetén.',
        ];
    }
}
