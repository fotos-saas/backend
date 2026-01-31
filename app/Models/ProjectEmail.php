<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablo_project_id',
        'message_id',
        'thread_id',
        'in_reply_to',
        'from_email',
        'from_name',
        'to_email',
        'to_name',
        'cc',
        'subject',
        'body_text',
        'body_html',
        'direction',
        'is_read',
        'needs_reply',
        'is_replied',
        'attachments',
        'imap_uid',
        'imap_folder',
        'email_date',
    ];

    protected $casts = [
        'cc' => 'array',
        'attachments' => 'array',
        'is_read' => 'boolean',
        'needs_reply' => 'boolean',
        'is_replied' => 'boolean',
        'email_date' => 'datetime',
    ];

    /**
     * Kapcsolat a TabloProject-tel
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Kapcsolat az order elemzéssel
     */
    public function orderAnalysis(): HasOne
    {
        return $this->hasOne(TabloOrderAnalysis::class, 'project_email_id');
    }

    /**
     * Bejövő email-e?
     */
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Kimenő email-e?
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    /**
     * Válaszra vár-e (bejövő és nincs megválaszolva)?
     */
    public function awaitsReply(): bool
    {
        return $this->isInbound() && $this->needs_reply && ! $this->is_replied;
    }

    /**
     * Nincs válasz (kimenő és nincs válasz)?
     */
    public function noResponse(): bool
    {
        return $this->isOutbound() && ! $this->is_replied;
    }

    /**
     * Thread-ben lévő emailek lekérése
     */
    public function threadEmails()
    {
        if (! $this->thread_id) {
            return collect([$this]);
        }

        return static::where('thread_id', $this->thread_id)
            ->orWhere('message_id', $this->thread_id)
            ->orderBy('email_date')
            ->get();
    }

    /**
     * Feladó megjelenítendő neve
     */
    public function getFromDisplayAttribute(): string
    {
        return $this->from_name ?: $this->from_email;
    }

    /**
     * Címzett megjelenítendő neve
     */
    public function getToDisplayAttribute(): string
    {
        return $this->to_name ?: $this->to_email;
    }

    /**
     * Rövid body előnézet
     */
    public function getBodyPreviewAttribute(): string
    {
        $text = $this->body_text ?: strip_tags($this->body_html ?? '');

        return \Illuminate\Support\Str::limit($text, 150);
    }

    /**
     * Tisztított body HTML (idézett rész nélkül)
     */
    public function getCleanBodyHtmlAttribute(): ?string
    {
        if (! $this->body_html) {
            return $this->body_text ? nl2br(e($this->body_text)) : null;
        }

        $html = $this->body_html;

        // Gmail-style idézet eltávolítása: <div class="gmail_quote">...</div>
        $html = preg_replace('/<div[^>]*class="[^"]*gmail_quote[^"]*"[^>]*>.*$/is', '', $html);

        // Blockquote elemek eltávolítása
        $html = preg_replace('/<blockquote[^>]*>.*?<\/blockquote>/is', '', $html);

        // "On ... wrote:" vagy "... ezt írta:" pattern után minden eltávolítása
        $patterns = [
            '/On\s+\d{4}.*?wrote:.*$/is',                           // "On 2024-01-01 ... wrote:"
            '/\d{4}\.\s*\w+\.\s*\d+\..*?ezt írta:.*$/isu',          // "2024. jan. 1. ... ezt írta:"
            '/<[^>]+>.*?ezt írta.*?:.*$/isu',                       // HTML-ben "ezt írta:"
            '/_{10,}.*$/s',                                          // Hosszú aláhúzás vonal után
            '/\-{10,}.*$/s',                                         // Hosszú kötőjel vonal után
        ];

        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        return trim($html) ?: null;
    }

    /**
     * Tisztított body TEXT (idézett rész nélkül)
     */
    public function getCleanBodyTextAttribute(): ?string
    {
        if (! $this->body_text) {
            return null;
        }

        $lines = explode("\n", $this->body_text);
        $cleanLines = [];
        $foundQuote = false;

        foreach ($lines as $line) {
            // Ha idézet kezdődik, abbahagyjuk
            if (preg_match('/^>/', $line)) {
                $foundQuote = true;
                continue;
            }

            // "On ... wrote:" vagy "... ezt írta:" pattern
            if (preg_match('/(On\s+.*?wrote:|ezt írta.*?:)/iu', $line)) {
                break;
            }

            // Hosszú vonalak (separator)
            if (preg_match('/^[-_]{10,}$/', trim($line))) {
                break;
            }

            if (! $foundQuote) {
                $cleanLines[] = $line;
            }
        }

        return trim(implode("\n", $cleanLines)) ?: null;
    }

    /**
     * Van-e csatolmány?
     */
    public function hasAttachments(): bool
    {
        return ! empty($this->attachments);
    }

    /**
     * Csatolmányok száma
     */
    public function getAttachmentCountAttribute(): int
    {
        return count($this->attachments ?? []);
    }

    /**
     * Scope: Csak bejövő emailek
     */
    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * Scope: Csak kimenő emailek
     */
    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    /**
     * Scope: Válaszra váró emailek
     */
    public function scopeNeedsReply($query)
    {
        return $query->where('needs_reply', true)->where('is_replied', false);
    }

    /**
     * Scope: Még nincs projekthez rendelve
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('tablo_project_id');
    }

    /**
     * Scope: Projekthez rendelve
     */
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('tablo_project_id');
    }
}
