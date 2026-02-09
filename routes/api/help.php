<?php

use App\Http\Controllers\Api\Help\HelpArticleController;
use App\Http\Controllers\Api\Help\HelpChatController;
use App\Http\Controllers\Api\Help\HelpTourController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Help API Routes
|--------------------------------------------------------------------------
|
| AI Chatbot, Tudásbázis cikkek, Guided Tour
|
*/

Route::middleware('auth:sanctum')->prefix('help')->group(function () {

    // Chat (rate limited: 30 kérés / perc)
    Route::post('/chat/send', [HelpChatController::class, 'send'])->middleware('throttle:30,1');
    Route::get('/chat/conversations', [HelpChatController::class, 'conversations']);
    Route::get('/chat/conversations/{conversation}/messages', [HelpChatController::class, 'messages']);
    Route::delete('/chat/conversations/{conversation}', [HelpChatController::class, 'destroy']);

    // Cikkek
    Route::get('/articles', [HelpArticleController::class, 'index']);
    Route::get('/articles/search', [HelpArticleController::class, 'search']);
    Route::get('/articles/for-route', [HelpArticleController::class, 'forRoute']);
    Route::get('/faq', [HelpArticleController::class, 'faq']);
    Route::get('/articles/{slug}', [HelpArticleController::class, 'show']);

    // Túrák
    Route::get('/tours', [HelpTourController::class, 'index']);
    Route::get('/tours/{tour}', [HelpTourController::class, 'show']);
    Route::post('/tours/{tour}/progress', [HelpTourController::class, 'updateProgress']);
    Route::get('/tours/progress/all', [HelpTourController::class, 'allProgress']);
});
