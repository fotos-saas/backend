<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Private notification channel - Guest sessions
 *
 * Channel format: notifications.{projectId}.guest.{guestSessionId}
 */
Broadcast::channel('notifications.{projectId}.guest.{guestSessionId}', function ($user, int $projectId, int $guestSessionId) {
    // A guest session validálás a middleware-ben történik
    // Itt ellenőrizzük, hogy a kérés tartalmazza-e a megfelelő session tokent
    $sessionToken = request()->header('X-Guest-Session');

    if (!$sessionToken) {
        return false;
    }

    // Session validálás
    $session = \App\Models\TabloGuestSession::where('session_token', $sessionToken)
        ->where('id', $guestSessionId)
        ->where('tablo_project_id', $projectId)
        ->where('is_banned', false)
        ->first();

    return $session !== null;
});

/**
 * Private notification channel - Contacts
 *
 * Channel format: notifications.{projectId}.contact.{contactId}
 */
Broadcast::channel('notifications.{projectId}.contact.{contactId}', function ($user, int $projectId, int $contactId) {
    // Contact autorizáció a projekt alapján
    $contact = \App\Models\TabloContact::where('id', $contactId)
        ->whereHas('project', function ($q) use ($projectId) {
            $q->where('id', $projectId);
        })
        ->first();

    return $contact !== null;
});

/**
 * Public forum channel
 *
 * Channel format: forum.project.{projectId}
 * Ez egy public channel, mindenki hallgathatja aki a projekthez tartozik
 */
Broadcast::channel('forum.project.{projectId}', function ($user, int $projectId) {
    // Public channel - mindenki hallgathatja
    // A jogosultság ellenőrzés az event dispatch-nél történik
    return true;
});
