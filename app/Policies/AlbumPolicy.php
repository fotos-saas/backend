<?php

namespace App\Policies;

use App\Models\Album;
use App\Models\User;

/**
 * Authorization policy for Album model.
 *
 * SECURITY: Protects against unauthorized album access (IDOR attacks).
 */
class AlbumPolicy
{
    /**
     * Determine whether the user can view any albums.
     */
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can view the album.
     *
     * Album access is granted if:
     * 1. User owns the album
     * 2. User is a partner who owns the project
     * 3. User has TabloProject access token (tablo frontend)
     */
    public function view(?User $user, Album $album): bool
    {
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
     * Determine whether the user can create albums.
     */
    public function create(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can update the album.
     */
    public function update(?User $user, Album $album): bool
    {
        if (!$user) {
            return false;
        }

        // Only album owner can update
        if ($album->user_id === $user->id) {
            return true;
        }

        // Partner can update their project's albums
        if ($user->partner_id) {
            $tabloProject = $album->tabloProject ?? $album->parentAlbum?->tabloProject;
            if ($tabloProject && $tabloProject->partner_id === $user->partner_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the album.
     */
    public function delete(?User $user, Album $album): bool
    {
        return $this->update($user, $album);
    }

    /**
     * Determine whether the user can upload photos to the album.
     */
    public function uploadPhotos(?User $user, Album $album): bool
    {
        return $this->update($user, $album);
    }

    /**
     * Determine whether the user can view photos in the album.
     */
    public function viewPhotos(?User $user, Album $album): bool
    {
        return $this->view($user, $album);
    }

    /**
     * Determine whether the user can cluster faces in the album.
     */
    public function clusterFaces(?User $user, Album $album): bool
    {
        return $this->update($user, $album);
    }
}
