<?php

use App\Http\Controllers\Api\Dev\DevLoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('dev')->middleware('dev.local')->group(function () {
    Route::post('login', [DevLoginController::class, 'generate']);
    Route::get('login/{token}', [DevLoginController::class, 'consume']);
});
