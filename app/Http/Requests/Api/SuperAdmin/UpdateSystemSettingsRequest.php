<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registrationEnabled' => 'sometimes|boolean',
            'trialDays' => 'sometimes|integer|min:0|max:90',
            'defaultPlan' => 'sometimes|string|in:alap,iskola,studio',
        ];
    }
}
