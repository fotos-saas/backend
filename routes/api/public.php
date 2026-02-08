<?php

use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\ImageConversionController;
use App\Http\Controllers\Api\MapConfigController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PackagePointController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PlansController;
use App\Http\Controllers\Api\PricingContextController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\Api\ShippingMethodController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\Tablo\WorkflowController as TabloWorkflowControllerNew;
use App\Http\Controllers\Api\WorkSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
| Health check, pricing, cart, orders, shares, photos, image conversion
*/

// Health Check Endpoint (for monitoring and deployment verification)
Route::get('/health', function () {
    $checks = [
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ];

    try {
        \DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Exception $e) {
        $checks['database'] = 'error';
        $checks['status'] = 'degraded';
    }

    try {
        \Illuminate\Support\Facades\Redis::connection()->ping();
        $checks['redis'] = 'ok';
    } catch (\Exception $e) {
        $checks['redis'] = 'error';
        $checks['status'] = 'degraded';
    }

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

// Guest Share Routes
// SECURITY: Rate limited to prevent token enumeration and abuse
Route::prefix('share')->middleware('throttle:30,1')->group(function () {
    Route::get('/{token}', [ShareController::class, 'validateToken']);
    Route::post('/{token}/selection', [ShareController::class, 'saveSelection']);
});

// Photo Previews (protected against IDOR)
Route::get('/photos/{photo}/preview', [PhotoController::class, 'preview'])
    ->middleware(['throttle:200,1']);

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
Route::prefix('cart')->middleware('throttle:60,1')->group(function () {
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
    Route::post('/checkout', [\App\Http\Controllers\Api\SubscriptionCheckoutController::class, 'createCheckoutSession']);
    Route::post('/verify', [\App\Http\Controllers\Api\SubscriptionCheckoutController::class, 'verifySession']);
    Route::post('/complete', [\App\Http\Controllers\Api\SubscriptionCheckoutController::class, 'completeRegistration']);
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

// Protected Public Routes (requires auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
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
        // Selection endpoints
        Route::post('/claiming', [TabloWorkflowControllerNew::class, 'saveClaiming']);
        Route::post('/retouch', [TabloWorkflowControllerNew::class, 'saveRetouch']);
        Route::post('/retouch/auto-save', [TabloWorkflowControllerNew::class, 'saveRetouch']);
        Route::post('/tablo/auto-save', [TabloWorkflowControllerNew::class, 'saveTabloPhoto']);
        Route::post('/tablo/clear', [TabloWorkflowControllerNew::class, 'clearTabloPhoto']);
        Route::post('/tablo', [TabloWorkflowControllerNew::class, 'saveTabloPhoto']);
        Route::post('/cart-comment', [TabloWorkflowControllerNew::class, 'saveCartComment']);

        // Navigation endpoints
        Route::post('/next-step', [TabloWorkflowControllerNew::class, 'nextStep']);
        Route::post('/previous-step', [TabloWorkflowControllerNew::class, 'previousStep']);
        Route::post('/move-to-step', [TabloWorkflowControllerNew::class, 'moveToStep']);
        Route::get('/progress/{gallery}', [TabloWorkflowControllerNew::class, 'getProgress']);
        Route::get('/step-data/{gallery}', [TabloWorkflowControllerNew::class, 'getStepData']);

        // Status & finalization
        Route::get('/workflow/status', [TabloWorkflowControllerNew::class, 'getStatus']);
        Route::post('/workflow/finalize', [TabloWorkflowControllerNew::class, 'finalize']);
        Route::post('/workflow/request-modification', [TabloWorkflowControllerNew::class, 'requestModification']);
    });

    // Work Sessions
    Route::prefix('work-sessions')->group(function () {
        Route::get('/{workSession}', [WorkSessionController::class, 'show']);
        Route::post('/{workSession}/send-email', [WorkSessionController::class, 'sendManualEmail']);
        Route::post('/{workSession}/download-manager-zip-async', [WorkSessionController::class, 'downloadManagerZipAsync']);
        Route::get('/download-progress/{downloadId}', [WorkSessionController::class, 'downloadProgressCheck']);
    });
});

// Client Routes (Partner Client - Token Auth)
Route::post('/client/login', [App\Http\Controllers\Api\ClientAuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::prefix('client')->middleware('auth.client')->group(function () {
    Route::get('/albums', [App\Http\Controllers\Api\ClientAlbumController::class, 'index']);
    Route::get('/albums/{id}', [App\Http\Controllers\Api\ClientAlbumController::class, 'show']);
    Route::post('/albums/{id}/selection', [App\Http\Controllers\Api\ClientAlbumController::class, 'saveSelection']);
    Route::get('/profile', [App\Http\Controllers\Api\ClientAuthController::class, 'profile']);
    Route::post('/register', [App\Http\Controllers\Api\ClientAuthController::class, 'register'])
        ->middleware('throttle:3,1');
    Route::patch('/notifications', [App\Http\Controllers\Api\ClientAuthController::class, 'updateNotifications']);
    Route::post('/change-password', [App\Http\Controllers\Api\ClientAuthController::class, 'changePassword'])
        ->middleware('throttle:3,1');
});
