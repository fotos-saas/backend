<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HelpChatMessage - Chat üzenetek.
 *
 * @property int $id
 * @property int $help_chat_conversation_id
 * @property string $role
 * @property string $content
 * @property array|null $metadata
 * @property int $input_tokens
 * @property int $output_tokens
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class HelpChatMessage extends Model
{
    protected $fillable = [
        'help_chat_conversation_id',
        'role',
        'content',
        'metadata',
        'input_tokens',
        'output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(HelpChatConversation::class, 'help_chat_conversation_id');
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }
}
