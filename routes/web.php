<?php

use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\WorkSessionController;
use App\Http\Controllers\EmailTrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Photo preview route (for admin panel)
Route::get('/photos/{photo}/preview', [PhotoController::class, 'preview'])
    ->name('photo.preview');

// Email tracking routes
Route::get('/track/open/{token}', [EmailTrackingController::class, 'trackOpen'])
    ->name('email.track.open');

Route::get('/track/click/{token}/{linkHash}', [EmailTrackingController::class, 'trackClick'])
        ->name('email.track.click');

// Order PDF view (inline - opens in browser)
Route::middleware('auth')->get('/order-pdf/{analysis}', function (\App\Models\TabloOrderAnalysis $analysis) {
    if (! $analysis->pdf_path) {
        abort(404, 'PDF nem található');
    }

    $path = \Illuminate\Support\Facades\Storage::disk('local')->path($analysis->pdf_path);

    if (! file_exists($path)) {
        abort(404, 'PDF fájl nem található');
    }

    return response()->file($path, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . ($analysis->pdf_filename ?? basename($path)) . '"',
    ]);
})->name('order-pdf.download');

// Work Session ZIP download (admin only, requires auth)
Route::middleware('auth')->group(function () {
    Route::get('/api/work-sessions/{workSession}/download-albums-zip', [WorkSessionController::class, 'downloadAlbumsZip'])
        ->name('api.work-sessions.download-albums-zip');

    Route::get('/api/work-sessions/{workSession}/download-manager-zip', [WorkSessionController::class, 'downloadManagerZip'])
        ->name('api.work-sessions.download-manager-zip');

    Route::get('/api/download-progress/{downloadId}', [WorkSessionController::class, 'downloadProgressCheck'])
        ->name('api.download-progress.check');

    // Manual photo matching for missing persons
    Route::post('/admin/api/missing-person-match', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'person_id' => 'required|integer|exists:tablo_missing_persons,id',
            'media_id' => 'required|integer|exists:media,id',
        ]);

        $person = \App\Models\TabloMissingPerson::find($validated['person_id']);
        $person->update(['media_id' => $validated['media_id']]);

        return response()->json(['success' => true]);
    })->name('admin.missing-person-match');

    // Quote PDF preview (inline - opens in browser)
    Route::get('/admin/quotes/{quote}/pdf', function (\App\Models\Quote $quote) {
        $quoteService = app(\App\Services\QuoteService::class);
        return $quoteService->previewPdf($quote);
    })->name('admin.quotes.pdf');

    // Quote PDF download (attachment)
    Route::get('/admin/quotes/{quote}/download', function (\App\Models\Quote $quote) {
        $quoteService = app(\App\Services\QuoteService::class);
        return $quoteService->downloadPdf($quote);
    })->name('admin.quotes.download');

    // Admin preview redirect - generates one-time token and redirects to frontend
    // This endpoint is used instead of direct URL generation to ensure token is only
    // created when the link is actually clicked, not when the action menu renders
    Route::get('/admin/tablo-projects/{tabloProject}/preview', function (\App\Models\TabloProject $tabloProject) {
        $url = $tabloProject->getAdminPreviewUrl();

        return redirect()->away($url);
    })->name('tablo-project.admin-preview');
});
