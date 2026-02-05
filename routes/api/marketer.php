<?php

use App\Http\Controllers\Api\MarketerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketer Routes
|--------------------------------------------------------------------------
| Marketing/sales representative routes for project and contact management
*/

Route::middleware('auth:sanctum')->group(function () {
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
});
