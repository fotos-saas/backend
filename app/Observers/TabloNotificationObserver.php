<?php

namespace App\Observers;

use App\Events\NewNotification;
use App\Models\TabloNotification;

/**
 * TabloNotification Observer
 *
 * Automatikus broadcast minden notification létrehozáskor
 */
class TabloNotificationObserver
{
    /**
     * Handle the TabloNotification "created" event.
     */
    public function created(TabloNotification $notification): void
    {
        // Broadcast real-time notification
        broadcast(new NewNotification($notification))->toOthers();
    }
}
