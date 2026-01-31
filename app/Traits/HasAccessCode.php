<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for models that support 6-digit access code authentication.
 *
 * Required fields in database:
 * - access_code_enabled (boolean)
 * - access_code (string, 6 digits, unique)
 * - access_code_expires_at (datetime, nullable)
 *
 * For backward compatibility with WorkSession model which uses digit_code_* fields,
 * this trait provides generic access_code_* methods that can be aliased.
 */
trait HasAccessCode
{
    /**
     * Get the column name for access code enabled field.
     * Override in model if using different column name (e.g., digit_code_enabled)
     */
    protected function getAccessCodeEnabledColumn(): string
    {
        return property_exists($this, 'accessCodeEnabledColumn')
            ? $this->accessCodeEnabledColumn
            : (in_array('digit_code_enabled', $this->fillable ?? []) ? 'digit_code_enabled' : 'access_code_enabled');
    }

    /**
     * Get the column name for access code field.
     * Override in model if using different column name (e.g., digit_code)
     */
    protected function getAccessCodeColumn(): string
    {
        return property_exists($this, 'accessCodeColumn')
            ? $this->accessCodeColumn
            : (in_array('digit_code', $this->fillable ?? []) ? 'digit_code' : 'access_code');
    }

    /**
     * Get the column name for access code expiration field.
     * Override in model if using different column name (e.g., digit_code_expires_at)
     */
    protected function getAccessCodeExpiresAtColumn(): string
    {
        return property_exists($this, 'accessCodeExpiresAtColumn')
            ? $this->accessCodeExpiresAtColumn
            : (in_array('digit_code_expires_at', $this->fillable ?? []) ? 'digit_code_expires_at' : 'access_code_expires_at');
    }

    /**
     * Generate unique 6-digit access code.
     *
     * @return string 6-digit code
     */
    public function generateAccessCode(): string
    {
        $column = $this->getAccessCodeColumn();

        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where($column, $code)->exists());

        return $code;
    }

    /**
     * Check if access code is valid (enabled and not expired).
     */
    public function hasValidAccessCode(): bool
    {
        $enabledColumn = $this->getAccessCodeEnabledColumn();
        $codeColumn = $this->getAccessCodeColumn();
        $expiresColumn = $this->getAccessCodeExpiresAtColumn();

        if (! $this->{$enabledColumn} || ! $this->{$codeColumn}) {
            return false;
        }

        if (! $this->{$expiresColumn}) {
            return true;
        }

        return $this->{$expiresColumn}->isFuture();
    }

    /**
     * Scope query to find by access code.
     */
    public function scopeByAccessCode(Builder $query, string $code): Builder
    {
        $enabledColumn = $this->getAccessCodeEnabledColumn();
        $codeColumn = $this->getAccessCodeColumn();
        $expiresColumn = $this->getAccessCodeExpiresAtColumn();

        return $query->where($codeColumn, $code)
            ->where($enabledColumn, true)
            ->where(function ($q) use ($expiresColumn) {
                $q->whereNull($expiresColumn)
                    ->orWhere($expiresColumn, '>', now());
            });
    }

    /**
     * Get access code enabled status.
     */
    public function isAccessCodeEnabled(): bool
    {
        return (bool) $this->{$this->getAccessCodeEnabledColumn()};
    }

    /**
     * Get access code value.
     */
    public function getAccessCode(): ?string
    {
        return $this->{$this->getAccessCodeColumn()};
    }

    /**
     * Get access code expiration date.
     */
    public function getAccessCodeExpiresAt(): ?\Carbon\Carbon
    {
        return $this->{$this->getAccessCodeExpiresAtColumn()};
    }
}
