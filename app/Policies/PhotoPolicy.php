<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;
use App\Models\PartnerClient;
use Illuminate\Support\Facades\DB;

/**
 * Authorization policy for Photo model.
 *
 * SECURITY: Protects against unauthorized photo access (IDOR attacks).
 */
class PhotoPolicy
{
    /**
     * Determine whether the user can view the photo preview.
     *
     * Photo access is granted if:
     * 1. User owns the photo's album (direct ownership)
     * 2. User is a partner who owns the project
     * 3. User has TabloProject access token (tablo frontend)
     * 4. Client has PartnerClient Bearer token (client orders feature)
     */
    public function preview(?User $user, Photo $photo): bool
    {
        $album = $photo->album;

        if (!$album) {
            return false;
        }

        // 1. Check direct album ownership
        if ($user && $album->user_id === $user->id) {
            return true;
        }

        // 2. Check if user is a partner who owns this album's project
        if ($user && $user->partner_id) {
            $tabloProject = $album->tabloProject ?? $album->parentAlbum?->tabloProject;
            if ($tabloProject && $tabloProject->partner_id === $user->partner_id) {
                return true;
            }
        }

        // 3. Check if user has tablo_project_id in their token (tablo frontend access)
        if ($user) {
            $token = $user->currentAccessToken();
            if ($token && $token->tablo_project_id) {
                $tabloProject = $album->tabloProject ?? $album->parentAlbum?->tabloProject;
                if ($tabloProject && $tabloProject->id === $token->tablo_project_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check PartnerClient Bearer token access (for client orders feature).
     *
     * This is separate because it requires the request to get the bearer token.
     *
     * @param string $bearerToken The bearer token from the request
     * @param Photo $photo The photo to check access for
     * @return bool Whether the client has access
     */
    public function checkClientAccess(string $bearerToken, Photo $photo): bool
    {
        $hashedToken = hash('sha256', $bearerToken);
        $tokenRecord = DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->whereNotNull('partner_client_id')
            ->first();

        if (!$tokenRecord) {
            return false;
        }

        $partnerClient = PartnerClient::find($tokenRecord->partner_client_id);
        if (!$partnerClient) {
            return false;
        }

        $album = $photo->album;
        if (!$album) {
            return false;
        }

        // Check if the photo's album belongs to this client
        return $partnerClient->albums()
            ->where('partner_order_albums.id', $album->id)
            ->exists();
    }

    /**
     * Determine whether the user can view the photo.
     */
    public function view(?User $user, Photo $photo): bool
    {
        return $this->preview($user, $photo);
    }

    /**
     * Determine whether the user can add notes to the photo.
     */
    public function addNote(?User $user, Photo $photo): bool
    {
        // Only authenticated users can add notes
        if (!$user) {
            return false;
        }

        return $this->view($user, $photo);
    }

    /**
     * Determine whether the user can update the photo.
     */
    public function update(?User $user, Photo $photo): bool
    {
        if (!$user) {
            return false;
        }

        $album = $photo->album;
        if (!$album) {
            return false;
        }

        // Only album owner can update photos
        return $album->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the photo.
     */
    public function delete(?User $user, Photo $photo): bool
    {
        return $this->update($user, $photo);
    }
}
