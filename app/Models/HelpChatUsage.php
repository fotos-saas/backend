<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HelpChatUsage - Napi token használat.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $partner_id
 * @property string $usage_date
 * @property int $message_count
 * @property int $total_input_tokens
 * @property int $total_output_tokens
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class HelpChatUsage extends Model
{
    protected $table = 'help_chat_usage';

    protected $fillable = [
        'user_id',
        'partner_id',
        'usage_date',
        'message_count',
        'total_input_tokens',
        'total_output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'message_count' => 'integer',
            'total_input_tokens' => 'integer',
            'total_output_tokens' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getOrCreateToday(int $userId, ?int $partnerId = null): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'usage_date' => now()->toDateString()],
            ['partner_id' => $partnerId, 'message_count' => 0, 'total_input_tokens' => 0, 'total_output_tokens' => 0]
        );
    }

    public function addUsage(int $inputTokens, int $outputTokens): void
    {
        $this->increment('message_count');
        $this->increment('total_input_tokens', $inputTokens);
        $this->increment('total_output_tokens', $outputTokens);
    }

    public function totalTokens(): int
    {
        return $this->total_input_tokens + $this->total_output_tokens;
    }
}
