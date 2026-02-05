<?php

use App\Http\Controllers\Api\MarketerController;
use App\Http\Controllers\Api\Marketer\MarketerProjectController;
use App\Http\Controllers\Api\Marketer\MarketerQrCodeController;
use App\Http\Controllers\Api\Marketer\MarketerProjectContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketer Routes
|--------------------------------------------------------------------------
| Marketing/sales representative routes for project and contact management
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('marketer')->middleware('role:marketer')->group(function () {
        // Dashboard & schools
        Route::get('/stats', [MarketerController::class, 'stats']);
        Route::get('/schools/all', [MarketerController::class, 'allSchools']);
        Route::get('/schools/cities', [MarketerController::class, 'cities']);
        Route::get('/schools', [MarketerController::class, 'schools']);

        // Projects CRUD
        Route::get('/projects', [MarketerProjectController::class, 'projects']);
        Route::post('/projects', [MarketerProjectController::class, 'storeProject']);
        Route::get('/projects/{projectId}', [MarketerProjectController::class, 'projectDetails']);

        // QR code management
        Route::get('/projects/{projectId}/qr-code', [MarketerQrCodeController::class, 'getQrCode']);
        Route::post('/projects/{projectId}/qr-code', [MarketerQrCodeController::class, 'generateQrCode']);
        Route::delete('/projects/{projectId}/qr-code', [MarketerQrCodeController::class, 'deactivateQrCode']);

        // Contact management
        Route::post('/projects/{projectId}/contacts', [MarketerProjectContactController::class, 'addContact']);
        Route::put('/projects/{projectId}/contacts/{contactId}', [MarketerProjectContactController::class, 'updateContact']);
        Route::delete('/projects/{projectId}/contacts/{contactId}', [MarketerProjectContactController::class, 'deleteContact']);
    });
});
