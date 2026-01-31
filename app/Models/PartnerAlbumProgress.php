<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PartnerAlbumProgress Model
 *
 * Tablo típusú albumok workflow progress-e.
 * Tárolja az egyes lépésekben kiválasztott képeket.
 */
class PartnerAlbumProgress extends Model
{
    use HasFactory;

    protected $table = 'partner_album_progress';

    public const STEP_CLAIMING = 'claiming';
    public const STEP_RETOUCH = 'retouch';
    public const STEP_TABLO = 'tablo';

    protected $fillable = [
        'partner_album_id',
        'partner_client_id',
        'current_step',
        'steps_data',
    ];

    protected $casts = [
        'steps_data' => 'array',
    ];

    /**
     * Default steps_data structure
     */
    public static function getDefaultStepsData(): array
    {
        return [
            'claimed_ids' => [],
            'retouch_ids' => [],
            'tablo_id' => null,
        ];
    }

    /**
     * Get the album
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(PartnerAlbum::class, 'partner_album_id');
    }

    /**
     * Get the client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerClient::class, 'partner_client_id');
    }

    /**
     * Get claimed photo IDs
     */
    public function getClaimedIds(): array
    {
        return $this->steps_data['claimed_ids'] ?? [];
    }

    /**
     * Set claimed photo IDs
     */
    public function setClaimedIds(array $ids): void
    {
        $this->updateStepsData('claimed_ids', $ids);
    }

    /**
     * Get retouch photo IDs
     */
    public function getRetouchIds(): array
    {
        return $this->steps_data['retouch_ids'] ?? [];
    }

    /**
     * Set retouch photo IDs
     */
    public function setRetouchIds(array $ids): void
    {
        $this->updateStepsData('retouch_ids', $ids);
    }

    /**
     * Get tablo photo ID
     */
    public function getTabloId(): ?int
    {
        return $this->steps_data['tablo_id'] ?? null;
    }

    /**
     * Set tablo photo ID
     */
    public function setTabloId(?int $id): void
    {
        $this->updateStepsData('tablo_id', $id);
    }

    /**
     * Update a specific key in steps_data
     */
    protected function updateStepsData(string $key, mixed $value): void
    {
        $data = $this->steps_data ?? self::getDefaultStepsData();
        $data[$key] = $value;
        $this->update(['steps_data' => $data]);
    }

    /**
     * Advance to next step
     */
    public function advanceToNextStep(): ?string
    {
        $nextStep = match ($this->current_step) {
            self::STEP_CLAIMING => self::STEP_RETOUCH,
            self::STEP_RETOUCH => self::STEP_TABLO,
            default => null,
        };

        if ($nextStep) {
            $this->update(['current_step' => $nextStep]);
        }

        return $nextStep;
    }

    /**
     * Check if workflow is complete
     */
    public function isComplete(): bool
    {
        return $this->current_step === self::STEP_TABLO && $this->getTabloId() !== null;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        return match ($this->current_step) {
            self::STEP_CLAIMING => 33,
            self::STEP_RETOUCH => 66,
            self::STEP_TABLO => $this->getTabloId() ? 100 : 80,
            default => 0,
        };
    }

    /**
     * Get human-readable step name
     */
    public function getStepName(): string
    {
        return match ($this->current_step) {
            self::STEP_CLAIMING => 'Képek kiválasztása',
            self::STEP_RETOUCH => 'Retusálandó képek',
            self::STEP_TABLO => 'Tablókép választás',
            default => 'Ismeretlen',
        };
    }
}
