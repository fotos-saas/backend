<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GetPricingContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_session_id' => 'required|integer|exists:work_sessions,id',
            'album_id' => 'required|integer|exists:albums,id',
            'current_step' => 'nullable|string|in:claiming,registration,retouch,tablo,completed',
            'context' => 'nullable|string|in:photo_selection,cart,checkout',
        ];
    }
}
