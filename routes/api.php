<?php

use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\ImageConversionController;
use App\Http\Controllers\Api\MapConfigController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PackagePointController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PricingContextController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\Api\ShippingMethodController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\Tablo\DiscussionController;
use App\Http\Controllers\Api\Tablo\GuestSessionController;
use App\Http\Controllers\Api\Tablo\NewsfeedController;
use App\Http\Controllers\Api\Tablo\PollController;
use App\Http\Controllers\Api\Tablo\TabloContactController;
use App\Http\Controllers\Api\Tablo\TabloFrontendController;
use App\Http\Controllers\Api\Tablo\TabloMissingPersonController;
use App\Http\Controllers\Api\Tablo\TabloPartnerController;
use App\Http\Controllers\Api\Tablo\TabloProjectController;
use App\Http\Controllers\Api\TabloWorkflowController;
use App\Http\Controllers\Api\WorkSessionController;
use App\Http\Controllers\Api\MarketerController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PlansController;
use App\Http\Controllers\Api\StorageController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\PartnerClientController;
use App\Http\Controllers\Api\PartnerOrderAlbumController;
use App\Http\Controllers\Api\ClientAlbumController;
use App\Http\Controllers\Api\ClientAuthController;
use App\Http\Middleware\SyncFotocmsId;
use App\Http\Middleware\TabloApiKeyAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ============================================
// PUBLIC ROUTES (No Auth Required)
// ============================================

// Health Check Endpoint (for monitoring and deployment verification)
Route::get('/health', function () {
    $checks = [
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ];

    try {
        // Check database connection
        \DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Exception $e) {
        $checks['database'] = 'error';
        $checks['status'] = 'degraded';
    }

    try {
        // Check Redis connection
        \Illuminate\Support\Facades\Redis::connection()->ping();
        $checks['redis'] = 'ok';
    } catch (\Exception $e) {
        $checks['redis'] = 'error';
        $checks['status'] = 'degraded';
    }

    // Check storage is writable
    try {
        $testFile = storage_path('app/health-check-test.txt');
        file_put_contents($testFile, 'test');
        unlink($testFile);
        $checks['storage'] = 'ok';
    } catch (\Exception $e) {
        $checks['storage'] = 'error';
        $checks['status'] = 'degraded';
    }

    $statusCode = $checks['status'] === 'ok' ? 200 : 503;

    return response()->json($checks, $statusCode);
});

// Auth Routes
// SECURITY: All login endpoints have rate limiting to prevent brute force attacks
Route::prefix('auth')->group(function () {
    // Login endpoints with account lockout check
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['account.lockout', 'throttle:5,1']); // 5 attempts per minute
    Route::post('/login-code', [AuthController::class, 'loginCode'])
        ->middleware('throttle:10,1'); // 10 attempts per minute (work session codes)
    // Unified access code login - supports both TabloProject codes and PartnerClient codes
    Route::post('/login-access-code', [AuthController::class, 'loginTabloCode'])
        ->middleware('throttle:tablo-login');
    // Legacy alias for backward compatibility
    Route::post('/login-tablo-code', [AuthController::class, 'loginTabloCode'])
        ->middleware('throttle:tablo-login');
    Route::post('/login-tablo-share', [AuthController::class, 'loginTabloShare'])
        ->middleware('throttle:tablo-login');
    Route::post('/login-tablo-preview', [AuthController::class, 'loginTabloPreview'])
        ->middleware('throttle:tablo-login');

    // Registration
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:3,1'); // 3 registrations per minute per IP

    // Password management (public)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,1'); // 3 password reset requests per minute
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1'); // 5 resets per minute

    // Email verification (public, signed URL)
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('verification.verify');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1');

    // Magic link
    Route::post('/request-magic-link', [AuthController::class, 'requestMagicLink'])
        ->middleware('throttle:3,1'); // 3 magic link requests per minute
    Route::get('/magic/{token}/validate', [AuthController::class, 'validateMagicToken'])
        ->middleware('throttle:10,1'); // Token validation
    Route::get('/magic/{token}', [AuthController::class, 'loginMagic'])
        ->middleware('throttle:10,1'); // Magic link login
    Route::post('/magic/consume', [AuthController::class, 'consumeMagicToken']); // Legacy

    // QR Registration (public - for tablo frontend)
    Route::get('/qr-code/{code}/validate', [AuthController::class, 'validateQrCode'])
        ->middleware('throttle:20,1');
    Route::post('/register-qr', [AuthController::class, 'registerFromQr'])
        ->middleware('throttle:10,1');
});

// Guest Share Routes
Route::prefix('share')->group(function () {
    Route::get('/{token}', [ShareController::class, 'validateToken']);
    Route::post('/{token}/selection', [ShareController::class, 'saveSelection']);
});

// Public Photos (for previews)
Route::get('/photos/{photo}/preview', [PhotoController::class, 'preview']);

// Pricing & Coupons (public for frontend cart)
Route::get('/pricing-rules', [PricingController::class, 'index']);
Route::get('/pricing-context', [PricingContextController::class, 'index']);
Route::get('/coupons/{code}', [CouponController::class, 'show']);

// Payment & Shipping (public for checkout)
Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::get('/shipping-methods', [ShippingMethodController::class, 'index']);
Route::post('/shipping-methods/calculate', [ShippingMethodController::class, 'calculate']);
Route::get('/package-points', [PackagePointController::class, 'index']);
Route::get('/package-points/search', [PackagePointController::class, 'searchNearby']);

// Map Configuration (public)
Route::get('/map-config', [MapConfigController::class, 'getConfig']);

// Plans Configuration (public) - Single source of truth for plan pricing & features
Route::get('/plans', [PlansController::class, 'index']);

// Cart (public - supports both authenticated and guest users)
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/items', [CartController::class, 'addItem']);
    Route::put('/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/items/{cartItem}', [CartController::class, 'removeItem']);
    Route::post('/sync', [CartController::class, 'sync']);
    Route::delete('/clear', [CartController::class, 'clear']);
});

// User photos endpoint for admin lightbox navigation
Route::get('/users/{user}/photos', [PhotoController::class, 'userPhotos']);

// Claim endpoint
Route::post('/claim', [ClaimController::class, 'store']);

// ZIP Download (signed URL - no auth needed, expires in 24h)
Route::get('/downloads/zip/ready', [WorkSessionController::class, 'downloadReadyZip'])
    ->name('api.work-sessions.download-ready-zip');

// Stripe Webhook (must be public and without CSRF protection)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Subscription / Partner Registration (public)
Route::prefix('subscription')->group(function () {
    Route::post('/checkout', [\App\Http\Controllers\Api\SubscriptionController::class, 'createCheckoutSession']);
    Route::post('/verify', [\App\Http\Controllers\Api\SubscriptionController::class, 'verifySession']);
    Route::post('/complete', [\App\Http\Controllers\Api\SubscriptionController::class, 'completeRegistration']);
});

// Subscription Management (authenticated partners)
Route::prefix('subscription')->middleware(['auth:sanctum', 'role:partner'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\SubscriptionController::class, 'getSubscription']);
    Route::get('/invoices', [\App\Http\Controllers\Api\SubscriptionController::class, 'getInvoices']);
    Route::post('/portal', [\App\Http\Controllers\Api\SubscriptionController::class, 'createPortalSession'])
        ->middleware('throttle:10,1');
    // SECURITY: Rate limiting subscription lifecycle műveleteknél (3/perc)
    Route::post('/cancel', [\App\Http\Controllers\Api\SubscriptionController::class, 'cancelSubscription'])
        ->middleware('throttle:3,1');
    Route::post('/resume', [\App\Http\Controllers\Api\SubscriptionController::class, 'resumeSubscription'])
        ->middleware('throttle:3,1');
    Route::post('/pause', [\App\Http\Controllers\Api\SubscriptionController::class, 'pauseSubscription'])
        ->middleware('throttle:3,1');
    Route::post('/unpause', [\App\Http\Controllers\Api\SubscriptionController::class, 'unpauseSubscription'])
        ->middleware('throttle:3,1');
});

// Account Management (authenticated partners)
Route::prefix('account')->middleware(['auth:sanctum', 'role:partner'])->group(function () {
    Route::get('/status', [\App\Http\Controllers\Api\AccountController::class, 'getStatus']);
    Route::delete('/', [\App\Http\Controllers\Api\AccountController::class, 'deleteAccount']);
    Route::post('/cancel-deletion', [\App\Http\Controllers\Api\AccountController::class, 'cancelDeletion']);
});

// Storage Management (authenticated partners) - Extra tárhely
Route::prefix('storage')->middleware(['auth:sanctum', 'role:partner'])->group(function () {
    Route::get('/usage', [StorageController::class, 'usage']);
    Route::post('/addon', [StorageController::class, 'setAddon']);
    Route::delete('/addon', [StorageController::class, 'removeAddon']);
});

// Addon Management (authenticated partners) - Funkció csomagok
Route::prefix('addons')->middleware(['auth:sanctum', 'role:partner'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\AddonController::class, 'index']);
    Route::get('/active', [\App\Http\Controllers\Api\AddonController::class, 'active']);
    Route::post('/{key}/subscribe', [\App\Http\Controllers\Api\AddonController::class, 'subscribe']);
    Route::delete('/{key}', [\App\Http\Controllers\Api\AddonController::class, 'cancel']);
});

// Orders (public - supports both guest and authenticated checkout)
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::post('/orders/{order}/checkout', [OrderController::class, 'checkout']);
Route::get('/orders/{order}/verify-payment', [OrderController::class, 'verifyPayment']);
Route::get('/orders/{order}/invoice/download', [OrderController::class, 'downloadInvoice'])
    ->name('api.orders.invoice.download');

// Image Conversion (public - vendég is elérheti)
Route::prefix('image-conversion')->middleware('throttle:image-conversion')->group(function () {
    Route::post('/upload', [ImageConversionController::class, 'upload']);
    Route::post('/batch-upload', [ImageConversionController::class, 'batchUpload']);
    Route::post('/{job}/convert', [ImageConversionController::class, 'convert']);
    Route::get('/{job}/status', [ImageConversionController::class, 'status']);
    Route::get('/{job}/download', [ImageConversionController::class, 'download']);
    Route::get('/{job}/download-progress', [ImageConversionController::class, 'downloadProgress']);
    Route::delete('/{job}', [ImageConversionController::class, 'delete']);
});

// Note: Tablo registration moved to protected routes (requires auth:sanctum)

// ============================================
// PROTECTED ROUTES (Auth Required)
// ============================================

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/set-password', [AuthController::class, 'setPassword']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::get('/auth/validate-session', [AuthController::class, 'validateSession']);
    Route::post('/auth/bulk-work-session-invite', [AuthController::class, 'bulkWorkSessionInvite']);

    // Session management
    Route::get('/auth/sessions', [AuthController::class, 'activeSessions']);
    Route::delete('/auth/sessions/{tokenId}', [AuthController::class, 'revokeSession']);
    Route::delete('/auth/sessions', [AuthController::class, 'revokeAllSessions']);

    // 2FA (preparation - endpoints exist but return "not available" until implemented)
    Route::post('/auth/2fa/enable', [AuthController::class, 'enable2FA']);
    Route::post('/auth/2fa/confirm', [AuthController::class, 'confirm2FA']);
    Route::post('/auth/2fa/disable', [AuthController::class, 'disable2FA']);
    Route::post('/auth/2fa/verify', [AuthController::class, 'verify2FA']);

    // Current User
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Albums (protected - user can only see their own albums)
    Route::get('/albums', [AlbumController::class, 'index']);
    Route::get('/albums/{album}', [AlbumController::class, 'show']);
    Route::get('/albums/{album}/photos', [PhotoController::class, 'index']);
    Route::post('/albums', [AlbumController::class, 'store']);
    Route::post('/albums/{album}/cluster-faces', [AlbumController::class, 'clusterFaces']);

    // Share (send link requires auth)
    Route::post('/share/send-link', [ShareController::class, 'sendLink']);

    // Photos
    Route::get('/photos/{photo}', [PhotoController::class, 'show']);
    Route::post('/photos/{photo}/notes', [PhotoController::class, 'addNote']);
    Route::get('/photos/unassigned', [PhotoController::class, 'unassigned']);

    // Cart & Pricing
    Route::post('/cart/price', [CartController::class, 'calculatePrice']);
    Route::post('/cart/calculate', [PricingController::class, 'calculate']);
    Route::post('/cart/merge-guest', [CartController::class, 'mergeGuestCart']);

    // Orders (authenticated user only)
    Route::get('/orders', [OrderController::class, 'index']);

    // Profile
    Route::get('/profile', function (Request $request) {
        return $request->user();
    });

    Route::put('/profile', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|array',
        ]);

        $request->user()->update($validated);

        return $request->user();
    });

    // Tablo Workflow (all endpoints require auth:sanctum)
    Route::prefix('tablo')->group(function () {
        Route::post('/register', [TabloWorkflowController::class, 'registerAndInitialize']);
        Route::post('/claiming', [TabloWorkflowController::class, 'saveClaimingSelection']);
        Route::post('/retouch', [TabloWorkflowController::class, 'saveRetouchSelection']);
        Route::post('/retouch/auto-save', [TabloWorkflowController::class, 'autoSaveRetouchSelection']);
        Route::post('/tablo/auto-save', [TabloWorkflowController::class, 'autoSaveTabloSelection']);
        Route::post('/tablo/clear', [TabloWorkflowController::class, 'clearTabloSelection']);
        Route::post('/tablo', [TabloWorkflowController::class, 'saveTabloSelection']);
        Route::post('/cart-comment', [TabloWorkflowController::class, 'saveCartComment']);

        // Backend-driven navigation (auto-skip registration for customer users)
        Route::post('/next-step', [TabloWorkflowController::class, 'nextStep']);
        Route::post('/previous-step', [TabloWorkflowController::class, 'previousStep']);

        Route::post('/move-to-step', [TabloWorkflowController::class, 'moveToStep']);
        Route::get('/progress/{gallery}', [TabloWorkflowController::class, 'getProgress']);
        Route::get('/step-data/{gallery}', [TabloWorkflowController::class, 'getStepData']);

        // Unified workflow status endpoint
        Route::get('/workflow/status', [TabloWorkflowController::class, 'getWorkflowStatus']);

        // Step-by-step save endpoints (US-014)
        Route::post('/workflow/retouch', [TabloWorkflowController::class, 'saveWorkflowRetouch']);
        Route::post('/workflow/tablo-photo', [TabloWorkflowController::class, 'saveWorkflowTabloPhoto']);
        Route::post('/workflow/finalize', [TabloWorkflowController::class, 'finalizeWorkflow']);
    });

    // Work Sessions
    Route::prefix('work-sessions')->group(function () {
        Route::get('/{workSession}', [WorkSessionController::class, 'show']);
        Route::post('/{workSession}/send-email', [WorkSessionController::class, 'sendManualEmail']);
        Route::post('/{workSession}/download-manager-zip-async', [WorkSessionController::class, 'downloadManagerZipAsync']);
        Route::get('/download-progress/{downloadId}', [WorkSessionController::class, 'downloadProgressCheck']);
    });

    // ============================================
    // SUPER ADMIN ROUTES (Rendszer adminisztráció)
    // ============================================
    Route::prefix('super-admin')->middleware('role:super_admin')->group(function () {
        Route::get('/stats', [SuperAdminController::class, 'stats']);
        Route::get('/partners', [SuperAdminController::class, 'partners']);
        Route::get('/subscribers', [SuperAdminController::class, 'subscribers']);
        Route::get('/settings', [SuperAdminController::class, 'getSettings']);
        Route::put('/settings', [SuperAdminController::class, 'updateSettings']);

        // Subscriber management
        Route::get('/subscribers/{id}', [SuperAdminController::class, 'getSubscriber'])
            ->where('id', '[0-9]+');
        // SECURITY: Pénzügyi műveletek rate limit-elve (5/perc)
        Route::post('/subscribers/{id}/charge', [SuperAdminController::class, 'chargeSubscriber'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:5,1');
        Route::put('/subscribers/{id}/change-plan', [SuperAdminController::class, 'changePlan'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:5,1');
        Route::delete('/subscribers/{id}/subscription', [SuperAdminController::class, 'cancelSubscription'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:5,1');
        Route::get('/subscribers/{id}/audit-logs', [SuperAdminController::class, 'getAuditLogs'])
            ->where('id', '[0-9]+');

        // Discount management
        Route::post('/subscribers/{id}/discount', [SuperAdminController::class, 'setDiscount'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:10,1');
        Route::delete('/subscribers/{id}/discount', [SuperAdminController::class, 'removeDiscount'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:10,1');
    });

    // ============================================
    // MARKETER ROUTES (Marketinges/Ügyintéző)
    // ============================================
    Route::prefix('marketer')->middleware('role:marketer')->group(function () {
        Route::get('/stats', [MarketerController::class, 'stats']);
        Route::get('/projects', [MarketerController::class, 'projects']);
        Route::post('/projects', [MarketerController::class, 'storeProject']);
        Route::get('/schools/all', [MarketerController::class, 'allSchools']);
        Route::get('/schools/cities', [MarketerController::class, 'cities']);
        Route::get('/schools', [MarketerController::class, 'schools']);

        // Project specific routes
        Route::get('/projects/{projectId}', [MarketerController::class, 'projectDetails']);
        Route::get('/projects/{projectId}/qr-code', [MarketerController::class, 'getQrCode']);
        Route::post('/projects/{projectId}/qr-code', [MarketerController::class, 'generateQrCode']);
        Route::delete('/projects/{projectId}/qr-code', [MarketerController::class, 'deactivateQrCode']);

        // Contact management
        Route::post('/projects/{projectId}/contacts', [MarketerController::class, 'addContact']);
        Route::put('/projects/{projectId}/contacts/{contactId}', [MarketerController::class, 'updateContact']);
        Route::delete('/projects/{projectId}/contacts/{contactId}', [MarketerController::class, 'deleteContact']);
    });

    // ============================================
    // PARTNER ROUTES (Fotós/Partner)
    // ============================================
    Route::prefix('partner')->middleware('role:partner')->group(function () {
        Route::get('/stats', [PartnerController::class, 'stats']);
        Route::get('/projects', [PartnerController::class, 'projects']);
        Route::post('/projects', [PartnerController::class, 'storeProject']);
        Route::get('/projects/autocomplete', [PartnerController::class, 'projectsAutocomplete']);
        Route::get('/projects/{projectId}', [PartnerController::class, 'projectDetails']);
        Route::get('/projects/{projectId}/samples', [PartnerController::class, 'projectSamples']);
        Route::get('/projects/{projectId}/missing-persons', [PartnerController::class, 'projectMissingPersons']);
        Route::get('/projects/{projectId}/qr-code', [PartnerController::class, 'getQrCode']);
        Route::post('/projects/{projectId}/qr-code', [PartnerController::class, 'generateQrCode']);
        Route::delete('/projects/{projectId}/qr-code', [PartnerController::class, 'deactivateQrCode']);

        // Contact management (project-specific)
        Route::post('/projects/{projectId}/contacts', [PartnerController::class, 'addContact']);
        Route::put('/projects/{projectId}/contacts/{contactId}', [PartnerController::class, 'updateContact']);
        Route::delete('/projects/{projectId}/contacts/{contactId}', [PartnerController::class, 'deleteContact']);

        // Schools management (partner's schools list)
        Route::get('/schools', [PartnerController::class, 'schools']);
        Route::get('/schools/all', [PartnerController::class, 'allSchools']);
        Route::post('/schools', [PartnerController::class, 'storeSchool']);
        Route::put('/schools/{schoolId}', [PartnerController::class, 'updateSchool']);
        Route::delete('/schools/{schoolId}', [PartnerController::class, 'deleteSchool']);

        // Contacts management (partner's contacts list)
        Route::get('/contacts', [PartnerController::class, 'contacts']);
        Route::get('/contacts/all', [PartnerController::class, 'allContacts']);
        Route::post('/contacts', [PartnerController::class, 'createStandaloneContact']);
        Route::post('/contacts/validate', [PartnerController::class, 'storeContact']);
        Route::put('/contacts/{contactId}', [PartnerController::class, 'updateStandaloneContact']);
        Route::delete('/contacts/{contactId}', [PartnerController::class, 'deleteStandaloneContact']);

        // Album management (új - diákok/tanárok szeparálva)
        Route::get('/projects/{projectId}/albums', [PartnerController::class, 'getAlbums']);
        Route::get('/projects/{projectId}/albums/{album}', [PartnerController::class, 'getAlbum']);
        Route::delete('/projects/{projectId}/albums/{album}', [PartnerController::class, 'clearAlbum']);

        // Photo upload endpoints - rate limited (10 request/perc)
        // Véd a túlzott feltöltési kísérletek ellen
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/projects/{projectId}/albums/{album}/upload', [PartnerController::class, 'uploadToAlbum']);
            Route::post('/projects/{projectId}/photos/bulk-upload', [PartnerController::class, 'bulkUploadPhotos']);
            Route::post('/projects/{projectId}/missing-persons/{personId}/photo', [PartnerController::class, 'uploadPersonPhoto']);
        });

        // Photo management (no rate limit needed - read/light operations)
        Route::get('/projects/{projectId}/photos/pending', [PartnerController::class, 'getPendingPhotos']);
        Route::post('/projects/{projectId}/photos/pending/delete', [PartnerController::class, 'deletePendingPhotos']);
        Route::post('/projects/{projectId}/photos/match', [PartnerController::class, 'matchPhotos']);
        Route::post('/projects/{projectId}/photos/assign', [PartnerController::class, 'assignPhotos']);
        Route::post('/projects/{projectId}/photos/assign-to-talon', [PartnerController::class, 'assignToTalon']);
        Route::get('/projects/{projectId}/photos/talon', [PartnerController::class, 'getTalonPhotos']);

        // ============================================
        // CLIENT ORDERS (Fotós Megrendelések)
        // ============================================
        // Partner's clients and albums management (feature flag required)
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

// ============================================
// TABLO MANAGEMENT API (API Key Auth)
// ============================================
// External system integration for managing Tabló projects
// Requires X-Tablo-Api-Key header

Route::prefix('tablo-management')->middleware([TabloApiKeyAuth::class, SyncFotocmsId::class])->group(function () {

    // Partners
    Route::get('/partners', [TabloPartnerController::class, 'index']);
    Route::get('/partners/{id}', [TabloPartnerController::class, 'show']);
    Route::post('/partners', [TabloPartnerController::class, 'store']);
    Route::put('/partners/{id}', [TabloPartnerController::class, 'update']);
    Route::delete('/partners/{id}', [TabloPartnerController::class, 'destroy']);

    // Projects
    Route::get('/projects', [TabloProjectController::class, 'index']);
    Route::get('/projects/export-missing-persons', [TabloMissingPersonController::class, 'exportMissingPersons']);
    Route::get('/projects/{id}', [TabloProjectController::class, 'show']);
    Route::post('/projects', [TabloProjectController::class, 'store']);
    Route::put('/projects/{id}', [TabloProjectController::class, 'update']);
    Route::patch('/projects/{id}/status', [TabloProjectController::class, 'updateStatus']);
    Route::post('/projects/sync-status', [TabloProjectController::class, 'syncStatus']);
    Route::delete('/projects/{id}', [TabloProjectController::class, 'destroy']);

    // Samples (Media)
    Route::get('/projects/{id}/samples', [TabloProjectController::class, 'getSamples']);
    Route::post('/projects/{id}/samples', [TabloProjectController::class, 'uploadSamples']);
    Route::post('/projects/sync-samples', [TabloProjectController::class, 'syncSamples']);
    Route::patch('/projects/{projectId}/samples/{mediaId}', [TabloProjectController::class, 'updateSample']);
    Route::delete('/projects/{projectId}/samples/{mediaId}', [TabloProjectController::class, 'deleteSample']);

    // Missing Persons
    Route::get('/projects/{projectId}/missing-persons', [TabloMissingPersonController::class, 'index']);
    Route::post('/projects/{projectId}/missing-persons', [TabloMissingPersonController::class, 'store']);
    Route::post('/projects/{projectId}/missing-persons/batch', [TabloMissingPersonController::class, 'batchStore']);
    Route::post('/projects/sync-missing-persons', [TabloMissingPersonController::class, 'syncMissingPersons']);
    Route::delete('/projects/{projectId}/missing-persons/batch', [TabloMissingPersonController::class, 'batchDestroy']);
    Route::put('/missing-persons/{id}', [TabloMissingPersonController::class, 'update']);
    Route::delete('/missing-persons/{id}', [TabloMissingPersonController::class, 'destroy']);

    // Contacts
    Route::get('/projects/{projectId}/contacts', [TabloContactController::class, 'index']);
    Route::post('/projects/{projectId}/contacts', [TabloContactController::class, 'store']);
    Route::put('/contacts/{id}', [TabloContactController::class, 'update']);
    Route::delete('/contacts/{id}', [TabloContactController::class, 'destroy']);
});

// ============================================
// TABLO FRONTEND ROUTES (Sanctum + TabloProject Auth)
// ============================================
// Protected routes for frontend-tablo Angular app
// Requires auth:sanctum + CheckTabloProjectStatus middleware

Route::prefix('tablo-frontend')
    ->middleware(['auth:sanctum', 'throttle:200,1', \App\Http\Middleware\CheckTabloProjectStatus::class])
    ->group(function () {
        // Auth endpoints
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/refresh', [AuthController::class, 'refresh']);

        // Validate current tablo project session
        Route::get('/validate-session', function (Request $request) {
            $token = $request->user()->currentAccessToken();

            if (! $token || ! $token->tablo_project_id) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Nincs érvényes tablo projekt session',
                ], 401);
            }

            $tabloProject = \App\Models\TabloProject::with(['school', 'partner.users', 'contacts', 'missingPersons', 'tabloStatus', 'gallery'])->find($token->tablo_project_id);

            if (! $tabloProject) {
                return response()->json([
                    'valid' => false,
                    'message' => 'A tablo projekt nem található',
                ], 401);
            }

            // Get ügyintézők (partner users with tablo role)
            $coordinators = $tabloProject->partner?->users
                ->filter(fn ($user) => $user->hasRole('tablo'))
                ->map(fn ($user) => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ])
                ->values() ?? collect();

            // Get elsődleges kapcsolattartó (is_primary=true, vagy a legújabb)
            $primaryContact = $tabloProject->contacts
                ->filter(fn ($c) => $c->is_primary)
                ->first()
                ?? $tabloProject->contacts->sortByDesc('created_at')->first();

            $contacts = $primaryContact ? [[
                'id' => $primaryContact->id,
                'name' => $primaryContact->name,
                'email' => $primaryContact->email,
                'phone' => $primaryContact->phone,
            ]] : [];

            // Get missing persons data
            $missingPersons = $tabloProject->missingPersons
                ->sortBy('position')
                ->map(fn ($person) => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'type' => $person->type,
                    'localId' => $person->local_id,
                    'hasPhoto' => $person->hasPhoto(),
                ])
                ->values();

            // Calculate missing photos count by type
            $missingWithoutPhoto = $missingPersons->where('hasPhoto', false);
            $studentsWithoutPhoto = $missingWithoutPhoto->where('type', 'student')->count();
            $teachersWithoutPhoto = $missingWithoutPhoto->where('type', 'teacher')->count();

            // Determine token type from token name
            $tokenName = $token->name;
            $tokenType = match($tokenName) {
                'tablo-auth-token' => 'code',
                'qr-registration' => 'code',  // QR regisztráció = teljes jogú kódos belépés
                'tablo-share-token' => 'share',
                'tablo-preview-token' => 'preview',
                default => 'unknown',
            };
            $isGuest = in_array($tokenType, ['share', 'preview']);
            $canFinalize = $tokenType === 'code';

            // Check if already finalized
            $isFinalized = ! empty($tabloProject->data['finalized_at'] ?? null);

            // Count active polls for voting menu
            $activePollsCount = $tabloProject->polls()->active()->count();

            return response()->json([
                'valid' => true,
                'project' => [
                    'id' => $tabloProject->id,
                    'name' => $tabloProject->display_name,
                    'schoolName' => $tabloProject->school?->name,
                    'className' => $tabloProject->class_name,
                    'classYear' => $tabloProject->class_year,
                    'partnerName' => $tabloProject->partner?->name,
                    'partnerEmail' => $tabloProject->partner?->email,
                    'partnerPhone' => $tabloProject->partner?->phone,
                    'coordinators' => $coordinators,
                    'contacts' => $contacts,
                    'hasOrderData' => $tabloProject->hasOrderAnalysis(),
                    'lastActivityAt' => $tabloProject->lastEmailDate?->toIso8601String(),
                    'photoDate' => $tabloProject->photo_date?->format('Y-m-d'),
                    'deadline' => $tabloProject->deadline?->format('Y-m-d'),
                    'missingPersons' => $missingPersons,
                    'missingStats' => [
                        'total' => $missingPersons->count(),
                        'withoutPhoto' => $missingWithoutPhoto->count(),
                        'studentsWithoutPhoto' => $studentsWithoutPhoto,
                        'teachersWithoutPhoto' => $teachersWithoutPhoto,
                    ],
                    // Has missing persons flag for navbar menu
                    'hasMissingPersons' => $missingPersons->count() > 0,
                    // Has template chooser flag - shown if there are active templates
                    'hasTemplateChooser' => \App\Models\TabloSampleTemplate::active()->exists(),
                    // Samples count - if > 0, no finalization/template chooser needed
                    'samplesCount' => $tabloProject->getMedia('samples')->count(),
                    // Active polls count for voting menu
                    'activePollsCount' => $activePollsCount,
                    // Expected class size for participation rate calculation
                    'expectedClassSize' => $tabloProject->expected_class_size,
                    // Tablo status - structured status from TabloStatus model
                    'tabloStatus' => $tabloProject->tabloStatus?->toApiResponse(),
                    // Legacy user status fields (deprecated)
                    'userStatus' => $tabloProject->tabloStatus?->name ?? $tabloProject->user_status,
                    'userStatusColor' => $tabloProject->tabloStatus?->color ?? $tabloProject->user_status_color,
                    // Share URL (if share token is enabled and valid)
                    'shareUrl' => $tabloProject->hasValidShareToken() ? $tabloProject->getShareUrl() : null,
                    'shareEnabled' => $tabloProject->share_token_enabled,
                    // Finalization status
                    'isFinalized' => $isFinalized,
                    // Work session ID for photo selection workflow
                    'workSessionId' => $tabloProject->work_session_id,
                    // Has photo selection enabled (work session attached)
                    'hasPhotoSelection' => $tabloProject->work_session_id !== null,
                    // Gallery ID for gallery view (if no work session)
                    'tabloGalleryId' => $tabloProject->tablo_gallery_id,
                    // Has gallery attached
                    'hasGallery' => $tabloProject->gallery !== null,
                    // Photo selection current step (if gallery exists)
                    'photoSelectionCurrentStep' => $tabloProject->tablo_gallery_id
                        ? (\App\Models\TabloUserProgress::where('user_id', $request->user()->id)
                            ->where('tablo_gallery_id', $tabloProject->tablo_gallery_id)
                            ->first()?->current_step ?? 'claiming')
                        : null,
                    // Photo selection finalized (if gallery exists)
                    'photoSelectionFinalized' => $tabloProject->tablo_gallery_id
                        ? (\App\Models\TabloUserProgress::where('user_id', $request->user()->id)
                            ->where('tablo_gallery_id', $tabloProject->tablo_gallery_id)
                            ->first()?->isFinalized() ?? false)
                        : false,
                    // Photo selection progress (for intelligent reminder step detection)
                    'photoSelectionProgress' => $tabloProject->tablo_gallery_id
                        ? (function () use ($request, $tabloProject) {
                            $progress = \App\Models\TabloUserProgress::where('user_id', $request->user()->id)
                                ->where('tablo_gallery_id', $tabloProject->tablo_gallery_id)
                                ->first();

                            if (! $progress) {
                                return null;
                            }

                            $stepsData = $progress->steps_data ?? [];

                            return [
                                'claimedCount' => count($stepsData['claimed_media_ids'] ?? []),
                                'retouchCount' => count($stepsData['retouch_media_ids'] ?? []),
                                'hasTabloPhoto' => isset($stepsData['tablo_media_id']),
                            ];
                        })()
                        : null,
                ],
                // Token access info for route guards and conditional menus
                'tokenType' => $tokenType,
                'isGuest' => $isGuest,
                'canFinalize' => $canFinalize,
                // User info for password set check
                'user' => [
                    'passwordSet' => (bool) $request->user()->password_set,
                ],
            ]);
        });

        // Project data endpoints (olvasás - vendég is elérheti)
        Route::get('/project-info', [TabloFrontendController::class, 'getProjectInfo']);
        Route::get('/samples', [TabloFrontendController::class, 'getSamples']);
        Route::get('/order-data', [TabloFrontendController::class, 'getOrderData']);
        Route::get('/gallery-photos', [TabloFrontendController::class, 'getGalleryPhotos']);

        // Order sheet PDF (olvasás - vendég is elérheti, ha van leadott megrendelés)
        Route::post('/order-data/view-pdf', [TabloFrontendController::class, 'viewOrderPdf']);

        // Protected endpoints - csak teljes jogú felhasználók (kódos belépés)
        Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
            // Update schedule (photo_date)
            Route::post('/update-schedule', function (Request $request) {
                $token = $request->user()->currentAccessToken();

                if (! $token || ! $token->tablo_project_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nincs érvényes session',
                    ], 401);
                }

                $tabloProject = \App\Models\TabloProject::find($token->tablo_project_id);

                if (! $tabloProject) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A projekt nem található',
                    ], 404);
                }

                // Validáció (max 1 év a jövőben)
                $validated = $request->validate([
                    'photo_date' => [
                        'required',
                        'date',
                        'date_format:Y-m-d',
                        'after_or_equal:today',
                        'before_or_equal:'.now()->addYear()->format('Y-m-d'),
                    ],
                ], [
                    'photo_date.required' => 'A fotózás dátuma kötelező.',
                    'photo_date.date' => 'Érvénytelen dátum formátum.',
                    'photo_date.after_or_equal' => 'A dátum nem lehet a múltban.',
                    'photo_date.before_or_equal' => 'A dátum maximum egy év múlva lehet.',
                ]);

                $tabloProject->update([
                    'photo_date' => $validated['photo_date'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Fotózás időpontja sikeresen mentve.',
                    'photoDate' => $tabloProject->photo_date->format('Y-m-d'),
                ]);
            });

            // Kapcsolattartó adatok módosítása
            Route::put('/contact', [TabloFrontendController::class, 'updateContact']);
        });

        // Order finalization endpoints (csak kódos belépéssel érhető el!)
        Route::prefix('finalization')
            ->middleware(\App\Http\Middleware\CheckFinalizationAccess::class)
            ->group(function () {
                Route::get('/', [TabloFrontendController::class, 'getFinalizationData']);
                Route::post('/', [TabloFrontendController::class, 'saveFinalizationData']);
                Route::post('/draft', [TabloFrontendController::class, 'saveDraft']);
                Route::post('/upload', [TabloFrontendController::class, 'uploadFinalizationFile']);
                Route::delete('/file', [TabloFrontendController::class, 'deleteFinalizationFile']);
                Route::post('/preview-pdf', [TabloFrontendController::class, 'generatePreviewPdf']);
            });

        // Template chooser endpoints
        Route::prefix('templates')->group(function () {
            // Olvasás - vendég is elérheti
            Route::get('/categories', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'getCategories']);
            Route::get('/', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'getTemplates']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'getTemplate']);
            Route::get('/selections/current', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'getSelections']);

            // Írás - csak teljes jogú felhasználók (kódos belépés)
            Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
                Route::post('/{id}/select', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'selectTemplate']);
                Route::delete('/{id}/select', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'deselectTemplate']);
                Route::patch('/{id}/priority', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'updatePriority']);
            });
        });

        // ============================================
        // GUEST SESSION MANAGEMENT
        // ============================================
        // Vendég regisztráció és session kezelés
        Route::prefix('guest')->group(function () {
            // Regisztráció és validálás - bárki elérheti
            Route::post('/register', [GuestSessionController::class, 'register'])
                ->middleware('throttle:20,1'); // 20/perc
            Route::post('/validate', [GuestSessionController::class, 'validate'])
                ->middleware('throttle:60,1');
            Route::put('/update', [GuestSessionController::class, 'update'])
                ->middleware('throttle:30,1'); // 30/perc - név módosítás
            Route::post('/send-link', [GuestSessionController::class, 'sendLink'])
                ->middleware('throttle:5,1'); // 5/perc rate limit email spamre
            Route::post('/heartbeat', [GuestSessionController::class, 'heartbeat'])
                ->middleware('throttle:30,1');

            // Session status polling - kitiltás/törlés ellenőrzés
            Route::get('/session-status', [GuestSessionController::class, 'sessionStatus'])
                ->middleware('throttle:120,1'); // 120/perc (30 sec polling = 2/perc)

            // Onboarding - Identification flow
            Route::get('/missing-persons/search', [GuestSessionController::class, 'searchMissingPersons'])
                ->middleware('throttle:60,1'); // 60/perc autocomplete-hez
            Route::post('/register-with-identification', [GuestSessionController::class, 'registerWithIdentification'])
                ->middleware('throttle:20,1'); // 20/perc
            Route::get('/verification-status', [GuestSessionController::class, 'checkVerificationStatus'])
                ->middleware('throttle:120,1'); // 120/perc (polling)

            // Session restore - Magic link email
            Route::post('/request-restore-link', [GuestSessionController::class, 'requestRestoreLink'])
                ->middleware('throttle:5,1'); // 5/perc rate limit email spamre
        });

        // ============================================
        // SZAVAZÁSOK (POLLS)
        // ============================================
        // Sablon szavazás - vendégek és kapcsolattartók
        // MEGJEGYZÉS: Partner addon szükséges (polls feature) - Alap csomagnál vásárolható
        Route::prefix('polls')
            ->middleware('partner.feature:polls')
            ->group(function () {
                // Olvasás - vendég is elérheti
                Route::get('/', [PollController::class, 'index']);
                Route::get('/{id}', [PollController::class, 'show']);
                Route::get('/{id}/results', [PollController::class, 'results']);

                // Szavazat leadás/visszavonás - vendég session kell
                Route::post('/{id}/vote', [PollController::class, 'vote'])
                    ->middleware('throttle:30,1');
                Route::delete('/{id}/vote', [PollController::class, 'removeVote'])
                    ->middleware('throttle:30,1');

                // Szavazás kezelés - csak kapcsolattartó (kódos belépés)
                Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
                    Route::post('/', [PollController::class, 'store']);
                    Route::put('/{id}', [PollController::class, 'update']);
                    Route::delete('/{id}', [PollController::class, 'destroy']);
                    Route::post('/{id}/close', [PollController::class, 'close']);
                    Route::post('/{id}/reopen', [PollController::class, 'reopen']);
                });
            });

        // ============================================
        // FÓRUM (DISCUSSIONS)
        // ============================================
        // Beszélgetések és hozzászólások
        // MEGJEGYZÉS: Partner addon szükséges (forum feature) - Alap csomagnál vásárolható
        Route::prefix('discussions')
            ->middleware('partner.feature:forum')
            ->group(function () {
                // Olvasás - vendég is elérheti
                Route::get('/', [DiscussionController::class, 'index']);
                Route::get('/{slugOrId}', [DiscussionController::class, 'show']);

                // Beszélgetés kezelés - csak kapcsolattartó
                Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
                    Route::post('/', [DiscussionController::class, 'store']);
                    Route::put('/{id}', [DiscussionController::class, 'update']);
                    Route::delete('/{id}', [DiscussionController::class, 'destroy']);
                    Route::post('/{id}/lock', [DiscussionController::class, 'lock']);
                    Route::post('/{id}/unlock', [DiscussionController::class, 'unlock']);
                    Route::post('/{id}/pin', [DiscussionController::class, 'pin']);
                    Route::post('/{id}/unpin', [DiscussionController::class, 'unpin']);
                });

                // Hozzászólás kezelés - vendég is írhat (guest session-nel)
                Route::post('/{id}/posts', [DiscussionController::class, 'createPost'])
                    ->middleware('throttle:60,1'); // 60 hozzászólás/perc
            });

        // Hozzászólás módosítás/törlés (külön prefix, mert post ID-val dolgozunk)
        // MEGJEGYZÉS: Partner addon szükséges (forum feature)
        Route::prefix('posts')
            ->middleware('partner.feature:forum')
            ->group(function () {
                Route::put('/{id}', [DiscussionController::class, 'updatePost']);
                Route::delete('/{id}', [DiscussionController::class, 'deletePost']);
                Route::post('/{id}/like', [DiscussionController::class, 'toggleLike'])
                    ->middleware('throttle:60,1');
            });

        // ============================================
        // HÍRFOLYAM (NEWSFEED)
        // ============================================
        // Bejelentések és események kezelése
        Route::prefix('newsfeed')->group(function () {
            // Olvasás - vendég is elérheti
            Route::get('/', [NewsfeedController::class, 'index']);
            Route::get('/events/upcoming', [NewsfeedController::class, 'upcomingEvents']);

            // Média törlés - szerző vagy admin
            // FONTOS: Ez a route a /{id} wildcard előtt kell legyen, különben a "media" stringet ID-ként értelmezi!
            Route::delete('/media/{mediaId}', [NewsfeedController::class, 'deleteMedia']);

            // Egyedi poszt lekérdezése
            Route::get('/{id}', [NewsfeedController::class, 'show']);
            Route::get('/{id}/comments', [NewsfeedController::class, 'getComments']);

            // Poszt létrehozás - vendég session-nel is írhat
            Route::post('/', [NewsfeedController::class, 'store'])
                ->middleware('throttle:newsfeed-post');

            // Poszt szerkesztés/törlés - szerző vagy admin
            // POST is támogatott a multipart/form-data miatt (média feltöltés)
            Route::put('/{id}', [NewsfeedController::class, 'update']);
            Route::post('/{id}', [NewsfeedController::class, 'update']);
            Route::delete('/{id}', [NewsfeedController::class, 'destroy']);

            // Like és komment - vendég session-nel
            Route::post('/{id}/like', [NewsfeedController::class, 'toggleLike'])
                ->middleware('throttle:newsfeed-like');
            Route::post('/{id}/comments', [NewsfeedController::class, 'createComment'])
                ->middleware('throttle:newsfeed-comment');

            // Kitűzés - csak admin (kapcsolattartó)
            Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
                Route::post('/{id}/pin', [NewsfeedController::class, 'pin']);
                Route::post('/{id}/unpin', [NewsfeedController::class, 'unpin']);
            });
        });

        // Newsfeed komment műveletek (külön prefix, mert comment ID-val dolgozunk)
        Route::delete('/newsfeed-comments/{id}', [NewsfeedController::class, 'deleteComment']);
        Route::post('/newsfeed-comments/{id}/like', [NewsfeedController::class, 'toggleCommentLike'])
            ->middleware('throttle:newsfeed-like');

        // ============================================
        // ADMIN - VENDÉG KEZELÉS (Kapcsolattartó only)
        // ============================================
        Route::prefix('admin')
            ->middleware(\App\Http\Middleware\RequireFullAccess::class)
            ->group(function () {
                // Vendégek listája és kezelése
                Route::get('/guests', [GuestSessionController::class, 'getGuests']);
                Route::post('/guests/{id}/ban', [GuestSessionController::class, 'ban']);
                Route::post('/guests/{id}/unban', [GuestSessionController::class, 'unban']);
                Route::put('/guests/{id}/extra', [GuestSessionController::class, 'toggleExtra']);

                // Osztálylétszám beállítás
                Route::put('/class-size', [GuestSessionController::class, 'setClassSize']);

                // Pending sessions kezelés - ütközéskezelés
                Route::get('/pending-sessions', [GuestSessionController::class, 'getPendingSessions']);
                Route::post('/guests/{id}/resolve-conflict', [GuestSessionController::class, 'resolveConflict']);
            });

        // ============================================
        // PUBLIC ADMIN - Résztvevők listája (mindenki látja)
        // ============================================
        // Vendégek is láthatják a résztvevőket, de nem módosíthatnak
        Route::get('/participants', [GuestSessionController::class, 'getGuests']);

        // Participants keresés @mention autocomplete-hez
        Route::get('/participants/search', [GuestSessionController::class, 'searchParticipants'])
            ->middleware('throttle:60,1'); // 60/perc autocomplete-hez

        // ============================================
        // ÉRTESÍTÉSEK (NOTIFICATIONS)
        // ============================================
        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Tablo\NotificationController::class, 'index']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\Tablo\NotificationController::class, 'unreadCount']);
            Route::post('/{id}/read', [\App\Http\Controllers\Api\Tablo\NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [\App\Http\Controllers\Api\Tablo\NotificationController::class, 'markAllAsRead']);
        });

        // ============================================
        // GAMIFICATION (PONTOK, BADGE-EK, TOPLISTA)
        // ============================================
        Route::prefix('gamification')->group(function () {
            Route::get('/stats', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'stats']);
            Route::get('/badges', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'badges']);
            Route::post('/badges/viewed', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'markBadgesViewed']);
            Route::get('/rank', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'rank']);
            Route::get('/leaderboard', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'leaderboard']);
            Route::get('/leaderboard/weekly', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'weeklyLeaderboard']);
            Route::get('/points/history', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'pointHistory']);
        });

        // ============================================
        // POKE SYSTEM (BÖKÉS RENDSZER)
        // ============================================
        Route::prefix('pokes')->group(function () {
            // Olvasás - vendég is elérheti
            Route::get('/presets', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'presets']);
            Route::get('/sent', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'sent']);
            Route::get('/received', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'received']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'unreadCount']);
            Route::get('/daily-limit', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'dailyLimit']);
            Route::get('/can-poke/{targetId}', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'canPoke']);

            // Írás - vendég session kell
            Route::post('/', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'store'])
                ->middleware('throttle:30,1');
            Route::post('/{id}/reaction', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'reaction'])
                ->middleware('throttle:60,1');
            Route::post('/{id}/read', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'read']);
            Route::post('/read-all', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'readAll']);
        });

        // ============================================
        // MISSING USERS (HIÁNYZÓK)
        // ============================================
        Route::prefix('missing')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'index']);
            Route::get('/voting', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'voting']);
            Route::get('/photoshoot', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'photoshoot']);
            Route::get('/image-selection', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'imageSelection']);
            Route::get('/my-status', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'myStatus']);
        });
    });

// ============================================
// CLIENT ROUTES (Partner Client - Token Auth)
// ============================================
// Partner ügyfelek album kezelése kódos vagy email/jelszó belépés után

// Public endpoint - email/jelszó alapú bejelentkezés (nincs auth)
Route::post('/client/login', [ClientAuthController::class, 'login'])
    ->middleware('throttle:5,1'); // 5 kísérlet/perc

// Protected endpoints - token szükséges
Route::prefix('client')->middleware('auth.client')->group(function () {
    // Album kezelés
    Route::get('/albums', [ClientAlbumController::class, 'index']);
    Route::get('/albums/{id}', [ClientAlbumController::class, 'show']);
    Route::post('/albums/{id}/selection', [ClientAlbumController::class, 'saveSelection']);

    // Profil
    Route::get('/profile', [ClientAuthController::class, 'profile']);

    // Regisztráció (kóddal bejelentkezett kliens számára)
    Route::post('/register', [ClientAuthController::class, 'register'])
        ->middleware('throttle:3,1'); // 3 kísérlet/perc

    // Értesítési beállítások (csak regisztrált)
    Route::patch('/notifications', [ClientAuthController::class, 'updateNotifications']);

    // Jelszócsere (csak regisztrált)
    Route::post('/change-password', [ClientAuthController::class, 'changePassword'])
        ->middleware('throttle:3,1');
});
