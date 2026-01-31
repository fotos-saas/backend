<?php

namespace App\Observers;

use App\Models\TabloMissingPerson;
use App\Services\TabloMissingPersonService;

/**
 * Observer a TabloMissingPerson modellhez - cache invalidálás
 */
class TabloMissingPersonObserver
{
    public function __construct(
        private TabloMissingPersonService $service
    ) {}

    public function created(TabloMissingPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);
    }

    public function updated(TabloMissingPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);

        // Ha projekt változott, régi projekt cache-t is töröljük
        if ($person->isDirty('tablo_project_id')) {
            $this->service->clearCountCache($person->getOriginal('tablo_project_id'));
        }
    }

    public function deleted(TabloMissingPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);
    }

    /**
     * Soft delete után is töröljük a cache-t
     */
    public function forceDeleted(TabloMissingPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);
    }

    /**
     * Restore után is frissítjük a cache-t
     */
    public function restored(TabloMissingPerson $person): void
    {
        $this->service->clearCountCache($person->tablo_project_id);
    }
}
