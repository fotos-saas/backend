<?php

namespace App\Observers;

use App\Models\TabloPerson;
use App\Services\TabloPersonService;

/**
 * Observer a TabloPerson modellhez - cache invalidálás
 */
class TabloPersonObserver
{
    public function __construct(
        private TabloPersonService $service
    ) {}

    public function created(TabloPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);
    }

    public function updated(TabloPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);

        // Ha projekt változott, régi projekt cache-t is töröljük
        if ($person->isDirty('tablo_project_id')) {
            $this->service->clearCountCache($person->getOriginal('tablo_project_id'));
        }
    }

    public function deleted(TabloPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);
    }

    /**
     * Soft delete után is töröljük a cache-t
     */
    public function forceDeleted(TabloPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);
    }

    /**
     * Restore után is frissítjük a cache-t
     */
    public function restored(TabloPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);
    }
}
