<?php

use App\Http\Controllers\Api\Admin\BugReportController as AdminBugReportController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\SuperAdminSubscriberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| Super admin system administration routes
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('super-admin')->middleware('role:super_admin')->group(function () {
        // Bug Reports (Hibajelentések kezelése)
        Route::prefix('bug-reports')->group(function () {
            Route::get('/', [AdminBugReportController::class, 'index']);
            Route::get('/unread-count', [AdminBugReportController::class, 'unreadCount']);
            Route::get('/{bugReport}', [AdminBugReportController::class, 'show']);
            Route::patch('/{bugReport}/status', [AdminBugReportController::class, 'updateStatus']);
            Route::patch('/{bugReport}/priority', [AdminBugReportController::class, 'updatePriority']);
            Route::post('/{bugReport}/comments', [AdminBugReportController::class, 'addComment']);
        });

        Route::get('/stats', [SuperAdminController::class, 'stats']);
        Route::get('/partners', [SuperAdminController::class, 'partners']);
        Route::get('/settings', [SuperAdminController::class, 'getSettings']);
        Route::put('/settings', [SuperAdminController::class, 'updateSettings']);

        // Subscriber management
        Route::get('/subscribers', [SuperAdminSubscriberController::class, 'subscribers']);
        Route::get('/subscribers/{id}', [SuperAdminSubscriberController::class, 'getSubscriber'])
            ->where('id', '[0-9]+');
        // SECURITY: Pénzügyi műveletek rate limit-elve (5/perc)
        Route::post('/subscribers/{id}/charge', [SuperAdminSubscriberController::class, 'chargeSubscriber'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:5,1');
        Route::put('/subscribers/{id}/change-plan', [SuperAdminSubscriberController::class, 'changePlan'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:5,1');
        Route::delete('/subscribers/{id}/subscription', [SuperAdminSubscriberController::class, 'cancelSubscription'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:5,1');
        Route::get('/subscribers/{id}/audit-logs', [SuperAdminSubscriberController::class, 'getAuditLogs'])
            ->where('id', '[0-9]+');

        // Discount management
        Route::post('/subscribers/{id}/discount', [SuperAdminSubscriberController::class, 'setDiscount'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:10,1');
        Route::delete('/subscribers/{id}/discount', [SuperAdminSubscriberController::class, 'removeDiscount'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:10,1');
    });
});
