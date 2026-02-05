<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\MagicLinkController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\Auth\TabloLoginController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\Auth\VerificationController;
use App\Http\Controllers\Api\InviteRegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
| Login, registration, password management, email verification, magic links
*/

// Public Auth Routes
// SECURITY: All login endpoints have rate limiting to prevent brute force attacks
Route::prefix('auth')->group(function () {
    // Login endpoints with account lockout check
    // SECURITY: Named rate limiter with dual IP+email protection
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware(['account.lockout', 'throttle:login']);
    Route::post('/login-code', [LoginController::class, 'loginCode'])
        ->middleware('throttle:10,1'); // 10 attempts per minute (work session codes)
    // Unified access code login - supports both TabloProject codes and PartnerClient codes
    Route::post('/login-access-code', [TabloLoginController::class, 'loginTabloCode'])
        ->middleware('throttle:tablo-login');
    // Legacy alias for backward compatibility
    Route::post('/login-tablo-code', [TabloLoginController::class, 'loginTabloCode'])
        ->middleware('throttle:tablo-login');
    Route::post('/login-tablo-share', [TabloLoginController::class, 'loginTabloShare'])
        ->middleware('throttle:tablo-login');
    Route::post('/login-tablo-preview', [TabloLoginController::class, 'loginTabloPreview'])
        ->middleware('throttle:tablo-login');

    // Registration
    Route::post('/register', [RegisterController::class, 'register'])
        ->middleware('throttle:3,1'); // 3 registrations per minute per IP

    // Password management (public)
    Route::post('/forgot-password', [PasswordController::class, 'forgotPassword'])
        ->middleware('throttle:3,1');
    Route::post('/reset-password', [PasswordController::class, 'resetPassword'])
        ->middleware('throttle:5,1');

    // Email verification (public, signed URL)
    Route::get('/verify-email/{id}/{hash}', [VerificationController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('verification.verify');
    Route::post('/resend-verification', [VerificationController::class, 'resendVerification'])
        ->middleware('throttle:3,1');

    // Magic link
    Route::post('/request-magic-link', [MagicLinkController::class, 'requestMagicLink'])
        ->middleware('throttle:3,1');
    Route::get('/magic/{token}/validate', [MagicLinkController::class, 'validateMagicToken'])
        ->middleware('throttle:10,1');
    Route::get('/magic/{token}', [LoginController::class, 'loginMagic'])
        ->middleware('throttle:10,1');

    // QR Registration (public - for tablo frontend)
    Route::get('/qr-code/{code}/validate', [RegisterController::class, 'validateQrCode'])
        ->middleware('throttle:20,1');
    Route::post('/register-qr', [RegisterController::class, 'registerFromQr'])
        ->middleware('throttle:10,1');
});

// Invite Registration (Meghívó kóddal regisztráció)
Route::prefix('invite')->middleware('throttle:10,1')->group(function () {
    Route::post('/validate', [InviteRegisterController::class, 'validateCode']);
    Route::post('/register', [InviteRegisterController::class, 'register']);
});

// Protected Auth Routes (requires auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Session management
    Route::post('/auth/logout', [SessionController::class, 'logout']);
    Route::get('/auth/refresh', [SessionController::class, 'refresh']);
    Route::get('/auth/validate-session', [SessionController::class, 'validateSession']);

    // Password management
    Route::post('/auth/set-password', [PasswordController::class, 'setPassword']);
    Route::post('/auth/change-password', [PasswordController::class, 'changePassword']);

    // Magic link bulk invite
    Route::post('/auth/bulk-work-session-invite', [MagicLinkController::class, 'bulkWorkSessionInvite']);

    // Session management
    Route::get('/auth/sessions', [SessionController::class, 'activeSessions']);
    Route::delete('/auth/sessions/{tokenId}', [SessionController::class, 'revokeSession']);
    Route::delete('/auth/sessions', [SessionController::class, 'revokeAllSessions']);

    // 2FA (preparation - endpoints exist but return "not available" until implemented)
    Route::post('/auth/2fa/enable', [TwoFactorController::class, 'enable2FA']);
    Route::post('/auth/2fa/confirm', [TwoFactorController::class, 'confirm2FA']);
    Route::post('/auth/2fa/disable', [TwoFactorController::class, 'disable2FA']);
    Route::post('/auth/2fa/verify', [TwoFactorController::class, 'verify2FA']);
});
