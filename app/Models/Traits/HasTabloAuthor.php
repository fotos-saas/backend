<?php

namespace App\Models\Traits;

use App\Models\TabloContact;
use App\Models\TabloGuestSession;
use Illuminate\Database\Eloquent\Model;

/**
 * HasTabloAuthor Trait
 *
 * Polymorphic author kezelés Tablo modelekhez.
 * Használat: TabloNewsfeedPost, TabloDiscussionPost, TabloNewsfeedComment, TabloNote
 *
 * Követelmények:
 * - $author_type mező: 'contact' vagy 'guest'
 * - $author_id mező: polymorphic ID
 * - AUTHOR_TYPE_CONTACT és AUTHOR_TYPE_GUEST konstansok
 *
 * @property string $author_type
 * @property int $author_id
 */
trait HasTabloAuthor
{
    /**
     * Get the author model (TabloContact or TabloGuestSession)
     */
    public function getAuthorModelAttribute(): ?Model
    {
        if ($this->author_type === static::AUTHOR_TYPE_CONTACT) {
            return TabloContact::find($this->author_id);
        }

        if ($this->author_type === static::AUTHOR_TYPE_GUEST) {
            return TabloGuestSession::find($this->author_id);
        }

        return null;
    }

    /**
     * Get the author's display name
     */
    public function getAuthorNameAttribute(): string
    {
        $author = $this->author_model;

        if ($author instanceof TabloContact) {
            return $author->name;
        }

        if ($author instanceof TabloGuestSession) {
            return $author->guest_name;
        }

        return 'Ismeretlen';
    }

    /**
     * Is the author a contact (admin)?
     */
    public function isAuthorContact(): bool
    {
        return $this->author_type === static::AUTHOR_TYPE_CONTACT;
    }

    /**
     * Is the author a guest?
     */
    public function isAuthorGuest(): bool
    {
        return $this->author_type === static::AUTHOR_TYPE_GUEST;
    }

    /**
     * Check if user is the author
     */
    public function isAuthoredBy(string $authorType, int $authorId): bool
    {
        return $this->author_type === $authorType && $this->author_id === $authorId;
    }
}
