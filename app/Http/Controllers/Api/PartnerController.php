<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Partner\PartnerAlbumController;
use App\Http\Controllers\Api\Partner\PartnerContactController;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\Partner\PartnerPhotoController;
use App\Http\Controllers\Api\Partner\PartnerProjectController;
use App\Http\Controllers\Api\Partner\PartnerQrController;
use App\Http\Controllers\Api\Partner\PartnerSchoolController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner Controller - Facade for partner-related functionality.
 *
 * This controller delegates to specialized sub-controllers for better organization.
 * Kept for backward compatibility with existing routes.
 *
 * Sub-controllers:
 * - PartnerDashboardController: stats, projects listing, project details
 * - PartnerSchoolController: school management
 * - PartnerContactController: contact management
 * - PartnerAlbumController: album management
 * - PartnerPhotoController: photo upload and matching
 * - PartnerProjectController: project CRUD operations
 * - PartnerQrController: QR code management
 *
 * @see \App\Http\Controllers\Api\Partner\PartnerDashboardController
 * @see \App\Http\Controllers\Api\Partner\PartnerSchoolController
 * @see \App\Http\Controllers\Api\Partner\PartnerContactController
 * @see \App\Http\Controllers\Api\Partner\PartnerAlbumController
 * @see \App\Http\Controllers\Api\Partner\PartnerPhotoController
 * @see \App\Http\Controllers\Api\Partner\PartnerProjectController
 * @see \App\Http\Controllers\Api\Partner\PartnerQrController
 */
class PartnerController extends Controller
{
    // ============================================
    // DASHBOARD METHODS
    // ============================================

    public function stats(): JsonResponse
    {
        return app(PartnerDashboardController::class)->stats();
    }

    public function projects(Request $request): JsonResponse
    {
        return app(PartnerDashboardController::class)->projects($request);
    }

    public function projectDetails(int $projectId): JsonResponse
    {
        return app(PartnerDashboardController::class)->projectDetails($projectId);
    }

    public function projectsAutocomplete(Request $request): JsonResponse
    {
        return app(PartnerDashboardController::class)->projectsAutocomplete($request);
    }

    // ============================================
    // SCHOOL METHODS
    // ============================================

    public function schools(Request $request): JsonResponse
    {
        return app(PartnerSchoolController::class)->schools($request);
    }

    public function allSchools(Request $request): JsonResponse
    {
        return app(PartnerSchoolController::class)->allSchools($request);
    }

    public function storeSchool(Request $request): JsonResponse
    {
        return app(PartnerSchoolController::class)->storeSchool($request);
    }

    public function updateSchool(Request $request, int $schoolId): JsonResponse
    {
        return app(PartnerSchoolController::class)->updateSchool($request, $schoolId);
    }

    public function deleteSchool(int $schoolId): JsonResponse
    {
        return app(PartnerSchoolController::class)->deleteSchool($schoolId);
    }

    // ============================================
    // CONTACT METHODS
    // ============================================

    public function contacts(Request $request): JsonResponse
    {
        return app(PartnerContactController::class)->contacts($request);
    }

    public function allContacts(Request $request): JsonResponse
    {
        return app(PartnerContactController::class)->allContacts($request);
    }

    public function storeContact(Request $request): JsonResponse
    {
        return app(PartnerContactController::class)->storeContact($request);
    }

    public function createStandaloneContact(Request $request): JsonResponse
    {
        return app(PartnerContactController::class)->createStandaloneContact($request);
    }

    public function updateStandaloneContact(Request $request, int $contactId): JsonResponse
    {
        return app(PartnerContactController::class)->updateStandaloneContact($request, $contactId);
    }

    public function deleteStandaloneContact(int $contactId): JsonResponse
    {
        return app(PartnerContactController::class)->deleteStandaloneContact($contactId);
    }

    public function addContact(Request $request, int $projectId): JsonResponse
    {
        return app(PartnerContactController::class)->addContact($request, $projectId);
    }

    public function updateContact(Request $request, int $projectId, int $contactId): JsonResponse
    {
        return app(PartnerContactController::class)->updateContact($request, $projectId, $contactId);
    }

    public function deleteContact(int $projectId, int $contactId): JsonResponse
    {
        return app(PartnerContactController::class)->deleteContact($projectId, $contactId);
    }

    // ============================================
    // ALBUM METHODS
    // ============================================

    public function getAlbums(int $projectId): JsonResponse
    {
        return app(PartnerAlbumController::class)->getAlbums($projectId);
    }

    public function getAlbum(int $projectId, string $album): JsonResponse
    {
        return app(PartnerAlbumController::class)->getAlbum($projectId, $album);
    }

    public function uploadToAlbum(int $projectId, string $album, Request $request): JsonResponse
    {
        return app(PartnerAlbumController::class)->uploadToAlbum($projectId, $album, $request);
    }

    public function clearAlbum(int $projectId, string $album): JsonResponse
    {
        return app(PartnerAlbumController::class)->clearAlbum($projectId, $album);
    }

    // ============================================
    // PHOTO METHODS
    // ============================================

    public function bulkUploadPhotos(int $projectId, Request $request): JsonResponse
    {
        return app(PartnerPhotoController::class)->bulkUploadPhotos($projectId, $request);
    }

    public function getPendingPhotos(int $projectId): JsonResponse
    {
        return app(PartnerPhotoController::class)->getPendingPhotos($projectId);
    }

    public function deletePendingPhotos(int $projectId, Request $request): JsonResponse
    {
        return app(PartnerPhotoController::class)->deletePendingPhotos($projectId, $request);
    }

    public function matchPhotos(int $projectId, Request $request): JsonResponse
    {
        return app(PartnerPhotoController::class)->matchPhotos($projectId, $request);
    }

    public function assignPhotos(int $projectId, Request $request): JsonResponse
    {
        return app(PartnerPhotoController::class)->assignPhotos($projectId, $request);
    }

    public function assignToTalon(int $projectId, Request $request): JsonResponse
    {
        return app(PartnerPhotoController::class)->assignToTalon($projectId, $request);
    }

    public function uploadPersonPhoto(int $projectId, int $personId, Request $request): JsonResponse
    {
        return app(PartnerPhotoController::class)->uploadPersonPhoto($projectId, $personId, $request);
    }

    public function getTalonPhotos(int $projectId): JsonResponse
    {
        return app(PartnerPhotoController::class)->getTalonPhotos($projectId);
    }

    // ============================================
    // PROJECT METHODS
    // ============================================

    public function storeProject(Request $request): JsonResponse
    {
        return app(PartnerProjectController::class)->storeProject($request);
    }

    public function updateProject(Request $request, int $projectId): JsonResponse
    {
        return app(PartnerProjectController::class)->updateProject($request, $projectId);
    }

    public function deleteProject(int $projectId): JsonResponse
    {
        return app(PartnerProjectController::class)->deleteProject($projectId);
    }

    public function toggleAware(int $projectId): JsonResponse
    {
        return app(PartnerProjectController::class)->toggleAware($projectId);
    }

    public function projectSamples(int $projectId): JsonResponse
    {
        return app(PartnerProjectController::class)->projectSamples($projectId);
    }

    public function projectPersons(int $projectId, Request $request): JsonResponse
    {
        return app(PartnerProjectController::class)->projectPersons($projectId, $request);
    }

    /**
     * @deprecated Use projectPersons() instead
     */
    public function projectMissingPersons(int $projectId, Request $request): JsonResponse
    {
        return $this->projectPersons($projectId, $request);
    }

    // ============================================
    // QR CODE METHODS
    // ============================================

    public function getQrCode(int $projectId): JsonResponse
    {
        return app(PartnerQrController::class)->getQrCode($projectId);
    }

    public function generateQrCode(int $projectId, Request $request): JsonResponse
    {
        return app(PartnerQrController::class)->generateQrCode($projectId, $request);
    }

    public function deactivateQrCode(int $projectId): JsonResponse
    {
        return app(PartnerQrController::class)->deactivateQrCode($projectId);
    }
}
