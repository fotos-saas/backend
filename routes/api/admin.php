<?php

use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| Super admin system administration routes
*/

Route::middleware('auth:sanctum')->group(function () {
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
});
