<?php

namespace App\Http\Requests\Api\Partner;

use App\Models\GuestBillingCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = auth()->user()?->getEffectivePartner()?->id;

        return [
            'tablo_project_id' => ['required', 'integer', Rule::exists('tablo_projects', 'id')->where('tablo_partner_id', $partnerId)],
            'tablo_person_id' => ['required', 'integer', 'exists:tablo_persons,id'],
            'partner_service_id' => ['nullable', 'integer', Rule::exists('partner_services', 'id')->where('partner_id', $partnerId)],
            'service_type' => ['required', Rule::in(GuestBillingCharge::SERVICE_TYPES)],
            'description' => ['nullable', 'string', 'max:500'],
            'amount_huf' => ['required', 'integer', 'min:1', 'max:10000000'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
