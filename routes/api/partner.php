<?php

use App\Http\Controllers\Api\Partner\InvitationController as PartnerInvitationController;
use App\Http\Controllers\Api\Partner\PartnerAlbumController;
use App\Http\Controllers\Api\Partner\PartnerContactController;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\Partner\PartnerPhotoController;
use App\Http\Controllers\Api\Partner\PartnerProjectController;
use App\Http\Controllers\Api\Partner\PartnerQrController;
use App\Http\Controllers\Api\Partner\PartnerSchoolController;
use App\Http\Controllers\Api\Partner\TeamController as PartnerTeamController;
use App\Http\Controllers\Api\PartnerClientController;
use App\Http\Controllers\Api\PartnerOrderAlbumController;
use App\Http\Controllers\Api\StorageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Partner Routes
|--------------------------------------------------------------------------
| Partner dashboard, projects, schools, contacts, albums, photos,
| subscriptions, storage, addons, team management, client orders
*/

Route::middleware('auth:sanctum')->group(function () {

    // Subscription Management (authenticated partners + team members for read)
    Route::prefix('subscription')->group(function () {
        // Csapattagok is lekérhetik (főnök nevéhez kell)
        Route::get('/', [\App\Http\Controllers\Api\SubscriptionController::class, 'getSubscription'])
            ->middleware('role:partner|designer|marketer|printer|assistant');

        // Csak partner végezheti ezeket
        Route::middleware('role:partner')->group(function () {
            Route::get('/invoices', [\App\Http\Controllers\Api\SubscriptionController::class, 'getInvoices']);
            Route::post('/portal', [\App\Http\Controllers\Api\SubscriptionController::class, 'createPortalSession'])
                ->middleware('throttle:10,1');
            // SECURITY: Pénzügyi műveletek rate limit-elve (3/perc)
            Route::post('/cancel', [\App\Http\Controllers\Api\SubscriptionController::class, 'cancelSubscription'])
                ->middleware('throttle:3,1');
            Route::post('/resume', [\App\Http\Controllers\Api\SubscriptionController::class, 'resumeSubscription'])
                ->middleware('throttle:3,1');
            Route::post('/pause', [\App\Http\Controllers\Api\SubscriptionController::class, 'pauseSubscription'])
                ->middleware('throttle:3,1');
            Route::post('/unpause', [\App\Http\Controllers\Api\SubscriptionController::class, 'unpauseSubscription'])
                ->middleware('throttle:3,1');
        });
    });

    // Account Management (authenticated partners + team members)
    Route::prefix('account')->middleware('role:partner|designer|marketer|printer|assistant')->group(function () {
        Route::get('/status', [\App\Http\Controllers\Api\AccountController::class, 'getStatus']);
        Route::delete('/', [\App\Http\Controllers\Api\AccountController::class, 'deleteAccount']);
        Route::post('/cancel-deletion', [\App\Http\Controllers\Api\AccountController::class, 'cancelDeletion']);
    });

    // Storage Management (authenticated partners) - Extra tárhely
    Route::prefix('storage')->middleware('role:partner')->group(function () {
        Route::get('/usage', [StorageController::class, 'usage']);
        Route::post('/addon', [StorageController::class, 'setAddon']);
        Route::delete('/addon', [StorageController::class, 'removeAddon']);
    });

    // Addon Management (authenticated partners) - Funkció csomagok
    Route::prefix('addons')->middleware('role:partner')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AddonController::class, 'index']);
        Route::get('/active', [\App\Http\Controllers\Api\AddonController::class, 'active']);
        Route::post('/{key}/subscribe', [\App\Http\Controllers\Api\AddonController::class, 'subscribe']);
        Route::delete('/{key}', [\App\Http\Controllers\Api\AddonController::class, 'cancel']);
    });

    // Partner Routes (Fotós/Partner + Csapattagok)
    Route::prefix('partner')->middleware('role:partner|designer|marketer|printer|assistant')->group(function () {
        Route::get('/stats', [PartnerDashboardController::class, 'stats']);

        // Team Management (Csapatkezelés)
        Route::get('/team', [PartnerTeamController::class, 'index']);
        Route::put('/team/{id}', [PartnerTeamController::class, 'update']);
        Route::delete('/team/{id}', [PartnerTeamController::class, 'destroy']);

        // Meghívók kezelése
        Route::get('/invitations', [PartnerInvitationController::class, 'index']);
        Route::post('/invitations', [PartnerInvitationController::class, 'store']);
        Route::delete('/invitations/{id}', [PartnerInvitationController::class, 'destroy']);
        Route::post('/invitations/{id}/resend', [PartnerInvitationController::class, 'resend']);

        // Projects
        Route::get('/projects', [PartnerDashboardController::class, 'projects']);
        Route::post('/projects', [PartnerProjectController::class, 'storeProject']);
        Route::get('/projects/autocomplete', [PartnerDashboardController::class, 'projectsAutocomplete']);
        Route::put('/projects/{projectId}', [PartnerProjectController::class, 'updateProject']);
        Route::patch('/projects/{projectId}/toggle-aware', [PartnerProjectController::class, 'toggleAware']);
        Route::delete('/projects/{projectId}', [PartnerProjectController::class, 'deleteProject']);
        Route::get('/projects/{projectId}', [PartnerDashboardController::class, 'projectDetails']);
        Route::get('/projects/{projectId}/samples', [PartnerProjectController::class, 'projectSamples']);
        Route::get('/projects/{projectId}/missing-persons', [PartnerProjectController::class, 'projectMissingPersons']);
        Route::get('/projects/{projectId}/qr-code', [PartnerQrController::class, 'getQrCode']);
        Route::post('/projects/{projectId}/qr-code', [PartnerQrController::class, 'generateQrCode']);
        Route::delete('/projects/{projectId}/qr-code', [PartnerQrController::class, 'deactivateQrCode']);

        // Contact management (project-specific)
        Route::post('/projects/{projectId}/contacts', [PartnerContactController::class, 'addContact']);
        Route::put('/projects/{projectId}/contacts/{contactId}', [PartnerContactController::class, 'updateContact']);
        Route::delete('/projects/{projectId}/contacts/{contactId}', [PartnerContactController::class, 'deleteContact']);

        // Schools management
        Route::get('/schools', [PartnerSchoolController::class, 'schools']);
        Route::get('/schools/all', [PartnerSchoolController::class, 'allSchools']);
        Route::post('/schools', [PartnerSchoolController::class, 'storeSchool']);
        Route::put('/schools/{schoolId}', [PartnerSchoolController::class, 'updateSchool']);
        Route::delete('/schools/{schoolId}', [PartnerSchoolController::class, 'deleteSchool']);

        // Contacts management (standalone)
        Route::get('/contacts', [PartnerContactController::class, 'contacts']);
        Route::get('/contacts/all', [PartnerContactController::class, 'allContacts']);
        Route::post('/contacts', [PartnerContactController::class, 'createStandaloneContact']);
        Route::post('/contacts/validate', [PartnerContactController::class, 'storeContact']);
        Route::put('/contacts/{contactId}', [PartnerContactController::class, 'updateStandaloneContact']);
        Route::delete('/contacts/{contactId}', [PartnerContactController::class, 'deleteStandaloneContact']);

        // Album management
        Route::get('/projects/{projectId}/albums', [PartnerAlbumController::class, 'getAlbums']);
        Route::get('/projects/{projectId}/albums/{album}', [PartnerAlbumController::class, 'getAlbum']);
        Route::delete('/projects/{projectId}/albums/{album}', [PartnerAlbumController::class, 'clearAlbum']);

        // Photo upload endpoints - rate limited (10 request/perc)
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/projects/{projectId}/albums/{album}/upload', [PartnerAlbumController::class, 'uploadToAlbum']);
            Route::post('/projects/{projectId}/photos/bulk-upload', [PartnerPhotoController::class, 'bulkUploadPhotos']);
            Route::post('/projects/{projectId}/missing-persons/{personId}/photo', [PartnerPhotoController::class, 'uploadPersonPhoto']);
        });

        // Photo management
        Route::get('/projects/{projectId}/photos/pending', [PartnerPhotoController::class, 'getPendingPhotos']);
        Route::post('/projects/{projectId}/photos/pending/delete', [PartnerPhotoController::class, 'deletePendingPhotos']);
        Route::post('/projects/{projectId}/photos/match', [PartnerPhotoController::class, 'matchPhotos']);
        Route::post('/projects/{projectId}/photos/assign', [PartnerPhotoController::class, 'assignPhotos']);
        Route::post('/projects/{projectId}/photos/assign-to-talon', [PartnerPhotoController::class, 'assignToTalon']);
        Route::get('/projects/{projectId}/photos/talon', [PartnerPhotoController::class, 'getTalonPhotos']);

        // Client Orders (Fotós Megrendelések)
        Route::prefix('orders')->middleware('partner.feature:client_orders')->group(function () {
            // Clients CRUD
            Route::get('/clients', [PartnerClientController::class, 'index']);
            Route::get('/clients/{id}', [PartnerClientController::class, 'show']);
            Route::post('/clients', [PartnerClientController::class, 'store']);
            Route::put('/clients/{id}', [PartnerClientController::class, 'update']);
            Route::delete('/clients/{id}', [PartnerClientController::class, 'destroy']);
            Route::post('/clients/{id}/generate-code', [PartnerClientController::class, 'generateCode']);
            Route::post('/clients/{id}/extend-code', [PartnerClientController::class, 'extendCode']);
            Route::post('/clients/{id}/disable-code', [PartnerClientController::class, 'disableCode']);

            // Albums CRUD
            Route::get('/albums', [PartnerOrderAlbumController::class, 'index']);
            Route::get('/albums/{id}', [PartnerOrderAlbumController::class, 'show']);
            Route::post('/albums', [PartnerOrderAlbumController::class, 'store']);
            Route::put('/albums/{id}', [PartnerOrderAlbumController::class, 'update']);
            Route::delete('/albums/{id}', [PartnerOrderAlbumController::class, 'destroy']);
            Route::post('/albums/{id}/activate', [PartnerOrderAlbumController::class, 'activate']);
            Route::post('/albums/{id}/deactivate', [PartnerOrderAlbumController::class, 'deactivate']);
            Route::post('/albums/{id}/reopen', [PartnerOrderAlbumController::class, 'reopen']);
            Route::post('/albums/{id}/extend-expiry', [PartnerOrderAlbumController::class, 'extendExpiry']);
            Route::post('/albums/{id}/toggle-download', [PartnerOrderAlbumController::class, 'toggleDownload']);

            // Album photo management
            Route::post('/albums/{id}/photos', [PartnerOrderAlbumController::class, 'uploadPhotos']);
            Route::delete('/albums/{albumId}/photos/{mediaId}', [PartnerOrderAlbumController::class, 'deletePhoto']);

            // Album export (ZIP, Excel)
            Route::post('/albums/{id}/download-zip', [PartnerOrderAlbumController::class, 'downloadZip']);
            Route::post('/albums/{id}/export-excel', [PartnerOrderAlbumController::class, 'exportExcel']);
        });
    });
});
