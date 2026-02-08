<?php

use App\Http\Controllers\Api\BugReportController;
use App\Http\Controllers\Api\Partner\InvoiceController;
use App\Http\Controllers\Api\Partner\InvoiceSettingsController;
use App\Http\Controllers\Api\Partner\InvitationController as PartnerInvitationController;
use App\Http\Controllers\Api\Partner\PartnerAlbumController;
use App\Http\Controllers\Api\Partner\PartnerBillingController;
use App\Http\Controllers\Api\Partner\PartnerGalleryController;
use App\Http\Controllers\Api\Partner\PartnerGalleryMonitoringController;
use App\Http\Controllers\Api\Partner\PartnerContactController;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\Partner\PartnerProjectContactController;
use App\Http\Controllers\Api\Partner\PartnerPhotoController;
use App\Http\Controllers\Api\Partner\PartnerProjectController;
use App\Http\Controllers\Api\Partner\PartnerProjectUsersController;
use App\Http\Controllers\Api\Partner\PartnerQrController;
use App\Http\Controllers\Api\Partner\PartnerAiSummaryController;
use App\Http\Controllers\Api\Partner\PartnerSamplePackageController;
use App\Http\Controllers\Api\Partner\PartnerSchoolController;
use App\Http\Controllers\Api\Partner\TeamController as PartnerTeamController;
use App\Http\Controllers\Api\Partner\PartnerBrandingController;
use App\Http\Controllers\Api\Partner\PartnerServiceController;
use App\Http\Controllers\Api\Partner\PartnerSettingsController;
use App\Http\Controllers\Api\Partner\PartnerStripeSettingsController;
use App\Http\Controllers\Api\Partner\PartnerWebshopSettingsController;
use App\Http\Controllers\Api\Partner\PartnerWebshopProductController;
use App\Http\Controllers\Api\Partner\PartnerWebshopOrderController;
use App\Http\Controllers\Api\PartnerClientController;
use App\Http\Controllers\Api\PartnerOrderAlbumController;
use App\Http\Controllers\Api\PartnerOrderAlbumPhotoController;
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

    // Bug Reports (Hibajelentések - Partner + Csapattagok)
    Route::prefix('bug-reports')->middleware('role:partner|designer|printer|assistant')->group(function () {
        Route::get('/', [BugReportController::class, 'index']);
        Route::post('/', [BugReportController::class, 'store']);
        Route::get('/{bugReport}', [BugReportController::class, 'show']);
        Route::post('/{bugReport}/comments', [BugReportController::class, 'addComment']);
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
        Route::get('/projects/{projectId}/order-data', [PartnerDashboardController::class, 'getProjectOrderData']);
        Route::post('/projects/{projectId}/order-data/view-pdf', [PartnerDashboardController::class, 'viewProjectOrderPdf']);
        Route::get('/projects/{projectId}/samples', [PartnerProjectController::class, 'projectSamples']);
        Route::get('/projects/{projectId}/missing-persons', [PartnerProjectController::class, 'projectPersons']);
        Route::get('/projects/{projectId}/qr-codes', [PartnerQrController::class, 'getQrCodes']);
        Route::post('/projects/{projectId}/qr-codes', [PartnerQrController::class, 'generateQrCode']);
        Route::delete('/projects/{projectId}/qr-codes/{codeId}', [PartnerQrController::class, 'deactivateQrCode']);
        Route::post('/projects/{projectId}/qr-codes/{codeId}/pin', [PartnerQrController::class, 'pinQrCode']);

        // Project settings
        Route::get('/projects/{projectId}/settings', [PartnerSettingsController::class, 'getProjectSettings']);
        Route::put('/projects/{projectId}/settings', [PartnerSettingsController::class, 'updateProjectSettings']);

        // Global partner settings
        Route::get('/settings', [PartnerSettingsController::class, 'getGlobalSettings']);
        Route::put('/settings', [PartnerSettingsController::class, 'updateGlobalSettings']);

        // Guest session management (project users)
        Route::get('/projects/{projectId}/guest-sessions', [PartnerProjectUsersController::class, 'index']);
        Route::put('/projects/{projectId}/guest-sessions/{sessionId}', [PartnerProjectUsersController::class, 'update']);
        Route::delete('/projects/{projectId}/guest-sessions/{sessionId}', [PartnerProjectUsersController::class, 'destroy']);
        Route::patch('/projects/{projectId}/guest-sessions/{sessionId}/ban', [PartnerProjectUsersController::class, 'toggleBan']);

        // AI summary generation
        Route::post('/ai/generate-summary', [PartnerAiSummaryController::class, 'generateSummary'])
            ->middleware('throttle:10,1');

        // Sample packages management
        Route::get('/projects/{projectId}/sample-packages', [PartnerSamplePackageController::class, 'index']);
        Route::post('/projects/{projectId}/sample-packages', [PartnerSamplePackageController::class, 'store']);
        Route::put('/projects/{projectId}/sample-packages/{pkgId}', [PartnerSamplePackageController::class, 'update']);
        Route::delete('/projects/{projectId}/sample-packages/{pkgId}', [PartnerSamplePackageController::class, 'destroy']);
        Route::post('/projects/{projectId}/sample-packages/{pkgId}/versions', [PartnerSamplePackageController::class, 'storeVersion']);
        Route::put('/projects/{projectId}/sample-packages/{pkgId}/versions/{verId}', [PartnerSamplePackageController::class, 'updateVersion']);
        Route::delete('/projects/{projectId}/sample-packages/{pkgId}/versions/{verId}', [PartnerSamplePackageController::class, 'destroyVersion']);

        // Gallery management (project-specific)
        Route::get('/projects/{projectId}/gallery', [PartnerGalleryController::class, 'getGallery']);
        Route::post('/projects/{projectId}/gallery', [PartnerGalleryController::class, 'createOrGetGallery']);
        Route::get('/projects/{projectId}/gallery/progress', [PartnerGalleryController::class, 'getProgress']);
        Route::post('/projects/{projectId}/gallery/photos', [PartnerGalleryController::class, 'uploadPhotos'])
            ->middleware('throttle:60,1');
        Route::post('/projects/{projectId}/gallery/deadline', [PartnerGalleryController::class, 'setDeadline']);
        Route::delete('/projects/{projectId}/gallery/photos', [PartnerGalleryController::class, 'deletePhotos']);
        Route::delete('/projects/{projectId}/gallery/photos/{mediaId}', [PartnerGalleryController::class, 'deletePhoto']);
        Route::get('/projects/{projectId}/gallery/monitoring', [PartnerGalleryMonitoringController::class, 'getMonitoring']);
        Route::post('/projects/{projectId}/gallery/monitoring/export-excel', [PartnerGalleryMonitoringController::class, 'exportExcel']);
        Route::post('/projects/{projectId}/gallery/monitoring/download-zip', [PartnerGalleryMonitoringController::class, 'downloadZip']);
        Route::get('/projects/{projectId}/gallery/monitoring/person/{personId}/selections', [PartnerGalleryMonitoringController::class, 'getPersonSelections']);

        // Contact management (project-specific)
        Route::post('/projects/{projectId}/contacts', [PartnerProjectContactController::class, 'addContact']);
        Route::put('/projects/{projectId}/contacts/{contactId}', [PartnerProjectContactController::class, 'updateContact']);
        Route::delete('/projects/{projectId}/contacts/{contactId}', [PartnerProjectContactController::class, 'deleteContact']);

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
        Route::post('/contacts/export-excel', [PartnerContactController::class, 'exportExcel']);
        Route::post('/contacts/export-vcard', [PartnerContactController::class, 'exportVcard']);
        Route::post('/contacts/import-excel', [PartnerContactController::class, 'importExcel']);
        Route::put('/contacts/{contactId}', [PartnerContactController::class, 'updateStandaloneContact']);
        Route::delete('/contacts/{contactId}', [PartnerContactController::class, 'deleteStandaloneContact']);

        // Album management
        Route::get('/projects/{projectId}/albums', [PartnerAlbumController::class, 'getAlbums']);
        Route::get('/projects/{projectId}/albums/{album}', [PartnerAlbumController::class, 'getAlbum']);
        Route::delete('/projects/{projectId}/albums/{album}', [PartnerAlbumController::class, 'clearAlbum']);

        // Photo upload endpoints - rate limited (10 request/perc)
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/projects/{projectId}/albums/{album}/upload', [PartnerAlbumController::class, 'uploadToAlbum']);
            Route::post('/projects/{projectId}/missing-persons/{personId}/photo', [PartnerPhotoController::class, 'uploadPersonPhoto']);
        });

        // Photo management
        Route::get('/projects/{projectId}/photos/pending', [PartnerPhotoController::class, 'getPendingPhotos']);
        Route::post('/projects/{projectId}/photos/pending/delete', [PartnerPhotoController::class, 'deletePendingPhotos']);
        Route::post('/projects/{projectId}/photos/match', [PartnerPhotoController::class, 'matchPhotos']);
        Route::post('/projects/{projectId}/photos/assign', [PartnerPhotoController::class, 'assignPhotos']);
        Route::post('/projects/{projectId}/photos/assign-to-talon', [PartnerPhotoController::class, 'assignToTalon']);
        Route::get('/projects/{projectId}/photos/talon', [PartnerPhotoController::class, 'getTalonPhotos']);

        // Branding (Márkajelzés) - READ: mindenki (csapattagok is), WRITE: csak feature gate-tel
        Route::get('/branding', [PartnerBrandingController::class, 'show']);
        Route::prefix('branding')->middleware('partner.feature:branding')->group(function () {
            Route::post('/', [PartnerBrandingController::class, 'update']);
            Route::post('/logo', [PartnerBrandingController::class, 'uploadLogo']);
            Route::post('/favicon', [PartnerBrandingController::class, 'uploadFavicon']);
            Route::post('/og-image', [PartnerBrandingController::class, 'uploadOgImage']);
            Route::delete('/logo', [PartnerBrandingController::class, 'deleteLogo']);
            Route::delete('/favicon', [PartnerBrandingController::class, 'deleteFavicon']);
            Route::delete('/og-image', [PartnerBrandingController::class, 'deleteOgImage']);
        });

        // Invoice Settings & Invoices (Számlázás) - feature gated
        Route::middleware('partner.feature:invoicing')->group(function () {
            Route::prefix('invoice-settings')->group(function () {
                Route::get('/', [InvoiceSettingsController::class, 'show']);
                Route::put('/', [InvoiceSettingsController::class, 'update']);
                Route::post('/validate', [InvoiceSettingsController::class, 'validateApiKey']);
            });

            Route::prefix('invoices')->group(function () {
                Route::get('/', [InvoiceController::class, 'index']);
                Route::get('/statistics', [InvoiceController::class, 'statistics']);
                Route::post('/', [InvoiceController::class, 'store']);
                Route::get('/{invoice}', [InvoiceController::class, 'show']);
                Route::post('/{invoice}/sync', [InvoiceController::class, 'sync']);
                Route::post('/{invoice}/cancel', [InvoiceController::class, 'cancel']);
                Route::get('/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
            });
        });

        // Partner Services (Szolgáltatás katalógus)
        Route::prefix('services')->group(function () {
            Route::get('/', [PartnerServiceController::class, 'index']);
            Route::post('/', [PartnerServiceController::class, 'store']);
            Route::post('/seed-defaults', [PartnerServiceController::class, 'seedDefaults']);
            Route::put('/{id}', [PartnerServiceController::class, 'update']);
            Route::delete('/{id}', [PartnerServiceController::class, 'destroy']);
        });

        // Partner Billing (Terhelés kezelés)
        Route::prefix('billing')->group(function () {
            Route::get('/', [PartnerBillingController::class, 'index']);
            Route::get('/summary', [PartnerBillingController::class, 'summary']);
            Route::post('/', [PartnerBillingController::class, 'store']);
            Route::put('/{id}', [PartnerBillingController::class, 'update']);
            Route::post('/{id}/cancel', [PartnerBillingController::class, 'cancel']);
        });

        // Partner Stripe Settings (Fizetés beállítások)
        Route::prefix('stripe-settings')->group(function () {
            Route::get('/', [PartnerStripeSettingsController::class, 'show']);
            Route::put('/', [PartnerStripeSettingsController::class, 'update']);
            Route::post('/validate', [PartnerStripeSettingsController::class, 'validateKeys'])
                ->middleware('throttle:10,1');
        });

        // Webshop (Fotónyomtatás rendelés)
        Route::prefix('webshop')->group(function () {
            // Settings
            Route::get('/settings', [PartnerWebshopSettingsController::class, 'getSettings']);
            Route::put('/settings', [PartnerWebshopSettingsController::class, 'updateSettings']);
            Route::post('/initialize', [PartnerWebshopSettingsController::class, 'initializeWebshop']);

            // Paper sizes
            Route::get('/paper-sizes', [PartnerWebshopSettingsController::class, 'getPaperSizes']);
            Route::post('/paper-sizes', [PartnerWebshopSettingsController::class, 'createPaperSize']);
            Route::put('/paper-sizes/{id}', [PartnerWebshopSettingsController::class, 'updatePaperSize']);
            Route::delete('/paper-sizes/{id}', [PartnerWebshopSettingsController::class, 'deletePaperSize']);

            // Paper types
            Route::get('/paper-types', [PartnerWebshopSettingsController::class, 'getPaperTypes']);
            Route::post('/paper-types', [PartnerWebshopSettingsController::class, 'createPaperType']);
            Route::put('/paper-types/{id}', [PartnerWebshopSettingsController::class, 'updatePaperType']);
            Route::delete('/paper-types/{id}', [PartnerWebshopSettingsController::class, 'deletePaperType']);

            // Products (pricing matrix)
            Route::get('/products', [PartnerWebshopProductController::class, 'getProducts']);
            Route::put('/products/pricing', [PartnerWebshopProductController::class, 'bulkUpdatePricing']);
            Route::patch('/products/{id}/toggle', [PartnerWebshopProductController::class, 'toggleProductStatus']);

            // Orders
            Route::get('/orders', [PartnerWebshopOrderController::class, 'index']);
            Route::get('/orders/stats', [PartnerWebshopOrderController::class, 'getStats']);
            Route::get('/orders/{id}', [PartnerWebshopOrderController::class, 'show']);
            Route::patch('/orders/{id}/status', [PartnerWebshopOrderController::class, 'updateStatus']);
        });

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
            Route::post('/albums/{id}/photos', [PartnerOrderAlbumPhotoController::class, 'uploadPhotos']);
            Route::delete('/albums/{albumId}/photos/{mediaId}', [PartnerOrderAlbumPhotoController::class, 'deletePhoto']);

            // Album export (ZIP, Excel)
            Route::post('/albums/{id}/download-zip', [PartnerOrderAlbumPhotoController::class, 'downloadZip']);
            Route::post('/albums/{id}/export-excel', [PartnerOrderAlbumPhotoController::class, 'exportExcel']);
        });
    });
});
