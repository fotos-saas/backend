<?php

use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\Tablo\DiscussionPostController;
use App\Http\Controllers\Api\Tablo\DiscussionThreadController;
use App\Http\Controllers\Api\Tablo\GuestAdminController;
use App\Http\Controllers\Api\Tablo\GuestRegistrationController;
use App\Http\Controllers\Api\Tablo\NewsfeedCommentController;
use App\Http\Controllers\Api\Tablo\NewsfeedPostController;
use App\Http\Controllers\Api\Tablo\PollController;
use App\Http\Controllers\Api\Tablo\TabloContactController;
use App\Http\Controllers\Api\Tablo\TabloFinalizationController;
use App\Http\Controllers\Api\Tablo\TabloFrontendController;
use App\Http\Controllers\Api\Tablo\TabloOrderViewController;
use App\Http\Controllers\Api\Tablo\TabloPartnerController;
use App\Http\Controllers\Api\Tablo\TabloSampleController;
use App\Http\Controllers\Api\Tablo\TabloPersonController;
use App\Http\Controllers\Api\Tablo\TabloProjectController;
use App\Http\Controllers\Api\Tablo\TabloProjectSampleController;
use App\Http\Controllers\Api\Tablo\TeacherMatchController;
use App\Http\Middleware\SyncFotocmsId;
use App\Http\Middleware\TabloApiKeyAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tablo Routes
|--------------------------------------------------------------------------
| Tablo Management API (external integration) and Tablo Frontend routes
*/

// ============================================
// TABLO MANAGEMENT API (API Key Auth)
// ============================================
// External system integration for managing Tabló projects
// Requires X-Tablo-Api-Key header

Route::prefix('tablo-management')->middleware([TabloApiKeyAuth::class, SyncFotocmsId::class, 'throttle:60,1'])->group(function () {

    // Partners
    Route::get('/partners', [TabloPartnerController::class, 'index']);
    Route::get('/partners/{id}', [TabloPartnerController::class, 'show']);
    Route::post('/partners', [TabloPartnerController::class, 'store']);
    Route::put('/partners/{id}', [TabloPartnerController::class, 'update']);
    Route::delete('/partners/{id}', [TabloPartnerController::class, 'destroy']);

    // Projects
    Route::get('/projects', [TabloProjectController::class, 'index']);
    Route::get('/projects/export-persons', [TabloPersonController::class, 'exportPersons']);
    // Legacy alias for backward compatibility
    Route::get('/projects/export-missing-persons', [TabloPersonController::class, 'exportPersons']);
    Route::get('/projects/{id}', [TabloProjectController::class, 'show']);
    Route::post('/projects', [TabloProjectController::class, 'store']);
    Route::put('/projects/{id}', [TabloProjectController::class, 'update']);
    Route::patch('/projects/{id}/status', [TabloProjectController::class, 'updateStatus']);
    Route::post('/projects/sync-status', [TabloProjectController::class, 'syncStatus']);
    Route::delete('/projects/{id}', [TabloProjectController::class, 'destroy']);

    // Samples (Media)
    Route::get('/projects/{id}/samples', [TabloProjectSampleController::class, 'getSamples']);
    Route::post('/projects/{id}/samples', [TabloProjectSampleController::class, 'uploadSamples']);
    Route::post('/projects/sync-samples', [TabloProjectSampleController::class, 'syncSamples']);
    Route::patch('/projects/{projectId}/samples/{mediaId}', [TabloProjectSampleController::class, 'updateSample']);
    Route::delete('/projects/{projectId}/samples/{mediaId}', [TabloProjectSampleController::class, 'deleteSample']);

    // Persons (projekt tagjai: diákok és tanárok)
    Route::get('/projects/{projectId}/persons', [TabloPersonController::class, 'index']);
    Route::post('/projects/{projectId}/persons', [TabloPersonController::class, 'store']);
    Route::post('/projects/{projectId}/persons/batch', [TabloPersonController::class, 'batchStore']);
    Route::post('/projects/sync-persons', [TabloPersonController::class, 'syncPersons']);
    Route::delete('/projects/{projectId}/persons/batch', [TabloPersonController::class, 'batchDestroy']);
    Route::put('/persons/{id}', [TabloPersonController::class, 'update']);
    Route::delete('/persons/{id}', [TabloPersonController::class, 'destroy']);
    // Legacy aliases for backward compatibility
    Route::get('/projects/{projectId}/missing-persons', [TabloPersonController::class, 'index']);
    Route::post('/projects/{projectId}/missing-persons', [TabloPersonController::class, 'store']);
    Route::post('/projects/{projectId}/missing-persons/batch', [TabloPersonController::class, 'batchStore']);
    Route::post('/projects/sync-missing-persons', [TabloPersonController::class, 'syncPersons']);
    Route::delete('/projects/{projectId}/missing-persons/batch', [TabloPersonController::class, 'batchDestroy']);
    Route::put('/missing-persons/{id}', [TabloPersonController::class, 'update']);
    Route::delete('/missing-persons/{id}', [TabloPersonController::class, 'destroy']);

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
        Route::post('/logout', [SessionController::class, 'logout']);
        Route::get('/refresh', [SessionController::class, 'refresh']);
        Route::get('/validate-session', [TabloFrontendController::class, 'validateTabloSession']);

        // Project data endpoints (olvasás - vendég is elérheti)
        Route::get('/project-info', [TabloFrontendController::class, 'getProjectInfo']);
        Route::get('/samples', [TabloSampleController::class, 'getSamples']);
        Route::get('/order-data', [TabloOrderViewController::class, 'getOrderData']);
        Route::get('/gallery-photos', [TabloFrontendController::class, 'getGalleryPhotos']);

        // Order sheet PDF
        Route::post('/order-data/view-pdf', [TabloOrderViewController::class, 'viewOrderPdf']);

        // Protected endpoints - csak teljes jogú felhasználók (kódos belépés)
        Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
            Route::post('/update-schedule', [TabloFrontendController::class, 'updateSchedule']);
            Route::put('/contact', [TabloFrontendController::class, 'updateContact']);
        });

        // Teacher name matching (AI) - rate limited
        Route::post('/match-teachers', [TeacherMatchController::class, 'matchTeachers'])
            ->middleware('throttle:10,1');

        // Order finalization endpoints
        Route::prefix('finalization')
            ->middleware(\App\Http\Middleware\CheckFinalizationAccess::class)
            ->group(function () {
                Route::get('/', [TabloFinalizationController::class, 'getFinalizationData']);
                Route::post('/', [TabloFinalizationController::class, 'saveFinalizationData']);
                Route::post('/draft', [TabloFinalizationController::class, 'saveDraft']);
                Route::post('/upload', [TabloFinalizationController::class, 'uploadFinalizationFile']);
                Route::delete('/file', [TabloFinalizationController::class, 'deleteFinalizationFile']);
                Route::post('/preview-pdf', [TabloFinalizationController::class, 'generatePreviewPdf']);
            });

        // Template chooser endpoints
        Route::prefix('templates')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'getCategories']);
            Route::get('/', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'getTemplates']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'getTemplate']);
            Route::get('/selections/current', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'getSelections']);

            Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
                Route::post('/{id}/select', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'selectTemplate']);
                Route::delete('/{id}/select', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'deselectTemplate']);
                Route::patch('/{id}/priority', [\App\Http\Controllers\Api\Tablo\TabloTemplateController::class, 'updatePriority']);
            });
        });

        // Guest Session Management
        Route::prefix('guest')->group(function () {
            Route::post('/register', [GuestRegistrationController::class, 'register'])
                ->middleware('throttle:20,1');
            Route::post('/validate', [GuestRegistrationController::class, 'validate'])
                ->middleware('throttle:60,1');
            Route::put('/update', [GuestRegistrationController::class, 'update'])
                ->middleware('throttle:30,1');
            Route::post('/send-link', [GuestRegistrationController::class, 'sendLink'])
                ->middleware('throttle:5,1');
            Route::post('/heartbeat', [GuestRegistrationController::class, 'heartbeat'])
                ->middleware('throttle:30,1');

            Route::get('/session-status', [GuestRegistrationController::class, 'sessionStatus'])
                ->middleware('throttle:120,1');

            Route::get('/missing-persons/search', [GuestRegistrationController::class, 'searchPersons'])
                ->middleware('throttle:60,1');
            Route::post('/register-with-identification', [GuestRegistrationController::class, 'registerWithIdentification'])
                ->middleware('throttle:20,1');
            Route::get('/verification-status', [GuestRegistrationController::class, 'checkVerificationStatus'])
                ->middleware('throttle:120,1');

            Route::post('/request-restore-link', [GuestRegistrationController::class, 'requestRestoreLink'])
                ->middleware('throttle:5,1');
        });

        // Polls
        Route::prefix('polls')
            ->middleware('partner.feature:polls')
            ->group(function () {
                Route::get('/', [PollController::class, 'index']);
                Route::get('/{id}', [PollController::class, 'show']);
                Route::get('/{id}/results', [PollController::class, 'results']);

                Route::post('/{id}/vote', [PollController::class, 'vote'])
                    ->middleware('throttle:30,1');
                Route::delete('/{id}/vote', [PollController::class, 'removeVote'])
                    ->middleware('throttle:30,1');

                Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
                    Route::post('/', [PollController::class, 'store']);
                    Route::put('/{id}', [PollController::class, 'update']);
                    Route::delete('/{id}', [PollController::class, 'destroy']);
                    Route::post('/{id}/close', [PollController::class, 'close']);
                    Route::post('/{id}/reopen', [PollController::class, 'reopen']);
                });
            });

        // Discussions (Forum) - Thread CRUD + moderation
        Route::prefix('discussions')
            ->middleware('partner.feature:forum')
            ->group(function () {
                Route::get('/', [DiscussionThreadController::class, 'index']);
                Route::get('/{slugOrId}', [DiscussionThreadController::class, 'show']);

                Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
                    Route::post('/', [DiscussionThreadController::class, 'store']);
                    Route::put('/{id}', [DiscussionThreadController::class, 'update']);
                    Route::delete('/{id}', [DiscussionThreadController::class, 'destroy']);
                    Route::post('/{id}/lock', [DiscussionThreadController::class, 'lock']);
                    Route::post('/{id}/unlock', [DiscussionThreadController::class, 'unlock']);
                    Route::post('/{id}/pin', [DiscussionThreadController::class, 'pin']);
                    Route::post('/{id}/unpin', [DiscussionThreadController::class, 'unpin']);
                });

                Route::post('/{id}/posts', [DiscussionPostController::class, 'createPost'])
                    ->middleware('throttle:60,1');
            });

        // Discussion posts - Post CRUD + reactions
        Route::prefix('posts')
            ->middleware('partner.feature:forum')
            ->group(function () {
                Route::put('/{id}', [DiscussionPostController::class, 'updatePost']);
                Route::delete('/{id}', [DiscussionPostController::class, 'deletePost']);
                Route::post('/{id}/like', [DiscussionPostController::class, 'toggleLike'])
                    ->middleware('throttle:60,1');
            });

        // Newsfeed
        Route::prefix('newsfeed')->group(function () {
            Route::get('/', [NewsfeedPostController::class, 'index']);
            Route::get('/events/upcoming', [NewsfeedPostController::class, 'upcomingEvents']);
            Route::delete('/media/{mediaId}', [NewsfeedPostController::class, 'deleteMedia']);
            Route::get('/{id}', [NewsfeedPostController::class, 'show']);
            Route::get('/{id}/comments', [NewsfeedCommentController::class, 'getComments']);
            Route::post('/', [NewsfeedPostController::class, 'store'])
                ->middleware('throttle:newsfeed-post');
            Route::put('/{id}', [NewsfeedPostController::class, 'update']);
            Route::post('/{id}', [NewsfeedPostController::class, 'update']);
            Route::delete('/{id}', [NewsfeedPostController::class, 'destroy']);
            Route::post('/{id}/like', [NewsfeedCommentController::class, 'toggleLike'])
                ->middleware('throttle:newsfeed-like');
            Route::post('/{id}/comments', [NewsfeedCommentController::class, 'createComment'])
                ->middleware('throttle:newsfeed-comment');

            Route::middleware(\App\Http\Middleware\RequireFullAccess::class)->group(function () {
                Route::post('/{id}/pin', [NewsfeedPostController::class, 'pin']);
                Route::post('/{id}/unpin', [NewsfeedPostController::class, 'unpin']);
            });
        });

        Route::delete('/newsfeed-comments/{id}', [NewsfeedCommentController::class, 'deleteComment']);
        Route::post('/newsfeed-comments/{id}/like', [NewsfeedCommentController::class, 'toggleCommentLike'])
            ->middleware('throttle:newsfeed-like');

        // Admin - Guest Management (Kapcsolattartó only)
        Route::prefix('admin')
            ->middleware(\App\Http\Middleware\RequireFullAccess::class)
            ->group(function () {
                Route::get('/guests', [GuestAdminController::class, 'getGuests']);
                Route::post('/guests/{id}/ban', [GuestAdminController::class, 'ban']);
                Route::post('/guests/{id}/unban', [GuestAdminController::class, 'unban']);
                Route::put('/guests/{id}/extra', [GuestAdminController::class, 'toggleExtra']);
                Route::put('/class-size', [GuestAdminController::class, 'setClassSize']);
                Route::get('/pending-sessions', [GuestAdminController::class, 'getPendingSessions']);
                Route::post('/guests/{id}/resolve-conflict', [GuestAdminController::class, 'resolveConflict']);
            });

        // Public participants list
        Route::get('/participants', [GuestAdminController::class, 'getGuests']);
        Route::get('/participants/search', [GuestRegistrationController::class, 'searchParticipants'])
            ->middleware('throttle:60,1');

        // Billing (Fizetéseim)
        Route::prefix('billing')->group(function () {
            Route::get('/summary', [\App\Http\Controllers\Api\Tablo\GuestBillingController::class, 'summary']);
            Route::get('/', [\App\Http\Controllers\Api\Tablo\GuestBillingController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Tablo\GuestBillingController::class, 'show']);
            Route::post('/{id}/checkout', [\App\Http\Controllers\Api\Tablo\GuestBillingController::class, 'createCheckoutSession'])
                ->middleware('throttle:10,1');
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Tablo\NotificationController::class, 'index']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\Tablo\NotificationController::class, 'unreadCount']);
            Route::post('/{id}/read', [\App\Http\Controllers\Api\Tablo\NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [\App\Http\Controllers\Api\Tablo\NotificationController::class, 'markAllAsRead']);
        });

        // Gamification
        Route::prefix('gamification')->group(function () {
            Route::get('/stats', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'stats']);
            Route::get('/badges', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'badges']);
            Route::post('/badges/viewed', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'markBadgesViewed']);
            Route::get('/rank', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'rank']);
            Route::get('/leaderboard', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'leaderboard']);
            Route::get('/leaderboard/weekly', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'weeklyLeaderboard']);
            Route::get('/points/history', [\App\Http\Controllers\Api\Tablo\GamificationController::class, 'pointHistory']);
        });

        // Poke System
        Route::prefix('pokes')->group(function () {
            Route::get('/presets', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'presets']);
            Route::get('/sent', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'sent']);
            Route::get('/received', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'received']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'unreadCount']);
            Route::get('/daily-limit', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'dailyLimit']);
            Route::get('/can-poke/{targetId}', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'canPoke']);

            Route::post('/', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'store'])
                ->middleware('throttle:30,1');
            Route::post('/{id}/reaction', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'reaction'])
                ->middleware('throttle:60,1');
            Route::post('/{id}/read', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'read']);
            Route::post('/read-all', [\App\Http\Controllers\Api\Tablo\PokeController::class, 'readAll']);
        });

        // Missing Users
        Route::prefix('missing')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'index']);
            Route::get('/voting', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'voting']);
            Route::get('/photoshoot', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'photoshoot']);
            Route::get('/image-selection', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'imageSelection']);
            Route::get('/my-status', [\App\Http\Controllers\Api\Tablo\MissingUserController::class, 'myStatus']);
        });
    });
