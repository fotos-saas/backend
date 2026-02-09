<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HelpChatConversation - Chat beszélgetések.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string|null $context_role
 * @property string|null $context_plan
 * @property string|null $context_route
 * @property int $message_count
 * @property int $total_input_tokens
 * @property int $total_output_tokens
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class HelpChatConversation extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'context_role',
        'context_plan',
        'context_route',
        'message_count',
        'total_input_tokens',
        'total_output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
            'total_input_tokens' => 'integer',
            'total_output_tokens' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HelpChatMessage::class)->orderBy('created_at');
    }

    public function lastMessages(int $count = 10): HasMany
    {
        return $this->hasMany(HelpChatMessage::class)->latest()->limit($count);
    }

    public function addTokenUsage(int $inputTokens, int $outputTokens): void
    {
        $this->increment('message_count');
        $this->increment('total_input_tokens', $inputTokens);
        $this->increment('total_output_tokens', $outputTokens);
    }
}
