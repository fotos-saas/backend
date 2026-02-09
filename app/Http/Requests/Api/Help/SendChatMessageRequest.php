<?php

namespace App\Http\Requests\Api\Help;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer|exists:help_chat_conversations,id',
            'context_route' => 'nullable|string|max:255',
        ];
    }
}
