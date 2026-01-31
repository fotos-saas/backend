<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateManagerZipJob;
use App\Models\EmailTemplate;
use App\Models\WorkSession;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use App\Services\ExifService;
use App\Services\WorkSessionZipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WorkSessionController extends Controller
{
    /**
     * Constructor
     */
    public function __construct(
        protected WorkSessionZipService $zipService,
        protected EmailService $emailService,
        protected EmailVariableService $variableService,
        protected ExifService $exifService
    ) {}

    /**
     * Download all albums from work session as ZIP
     */
    public function downloadAlbumsZip(WorkSession $workSession): Response
    {
        try {
            // Get optional album IDs from request
            $albumIds = request()->input('album_ids');

            // Convert to array if needed (query string might send comma-separated)
            if (is_string($albumIds)) {
                $albumIds = array_filter(array_map('intval', explode(',', $albumIds)));
            }

            // Generate ZIP file
            $zipPath = $this->zipService->generateAlbumsZip($workSession, $albumIds);

            // Get filename for download
            $fileName = basename($zipPath);

            // Return download response and delete file after send
            return response()->download($zipPath, $fileName, [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Failed to generate work session albums ZIP', [
                'work_session_id' => $workSession->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to generate ZIP file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download manager ZIP (SYNC - csak kis fájlokhoz)
     */
    public function downloadManagerZip(Request $request, WorkSession $workSession)
    {
        // Validation
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'photo_type' => 'required|in:claimed,retus,tablo',
            'filename_mode' => 'required|in:original,user_name,original_exif',
            'download_id' => 'nullable|string',
        ]);

        try {
            // Generate ZIP via service with optional download_id for progress tracking
            $zipPath = $this->zipService->generateManagerZip(
                $workSession,
                $validated['user_ids'],
                $validated['photo_type'],
                $validated['filename_mode'],
                $validated['download_id'] ?? null
            );

            // Return download response
            return response()->download($zipPath, basename($zipPath), [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Failed to generate manager ZIP', [
                'work_session_id' => $workSession->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate ZIP file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download manager ZIP ASYNC (háttérben készül, értesítés emailben)
     */
    public function downloadManagerZipAsync(Request $request, WorkSession $workSession)
    {
        // Validation
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'photo_type' => 'required|in:claimed,retus,tablo',
            'filename_mode' => 'required|in:original,user_name,original_exif',
        ]);

        // Generate unique download ID for progress tracking
        $downloadId = Str::uuid()->toString();

        // Dispatch job to queue
        GenerateManagerZipJob::dispatch(
            $workSession,
            $validated['user_ids'],
            $validated['photo_type'],
            $validated['filename_mode'],
            auth()->id(), // Requesting user
            $downloadId
        );

        Log::info('Manager ZIP generation queued', [
            'work_session_id' => $workSession->id,
            'download_id' => $downloadId,
            'user_id' => auth()->id(),
        ]);

        // Return 202 Accepted with download ID
        return response()->json([
            'message' => 'ZIP előkészítése megkezdődött. Értesítést kapsz emailben, amikor elkészült.',
            'download_id' => $downloadId,
            'status' => 'queued',
        ], 202);
    }

    /**
     * Download ready ZIP file (signed URL)
     */
    public function downloadReadyZip(Request $request)
    {
        try {
            // Get encrypted storage path and filename from request
            $encryptedPath = $request->input('storagePath');
            $filename = $request->input('filename');

            // Decrypt storage path
            $storagePath = decrypt($encryptedPath);

            // Verify file exists
            if (!Storage::exists($storagePath)) {
                return response()->json([
                    'error' => 'A fájl már nem elérhető vagy lejárt.',
                ], 404);
            }

            // Stream file download
            return Storage::download($storagePath, $filename, [
                'Content-Type' => 'application/zip',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to download ready ZIP', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Hiba történt a fájl letöltése során.',
            ], 500);
        }
    }

    /**
     * Check download progress
     */
    public function downloadProgressCheck(Request $request, string $downloadId)
    {
        $progress = Cache::get("download_progress:{$downloadId}");

        if (!$progress) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Download progress not found or expired',
            ], 404);
        }

        return response()->json($progress);
    }

    /**
     * Send manual email from work session
     */
    public function sendManualEmail(Request $request, WorkSession $workSession)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'access_mode' => 'required|in:code,link,credentials,all',
        ]);

        $template = EmailTemplate::findOrFail($validated['template_id']);

        // Resolve variables based on access mode
        $variables = $this->variableService->resolveVariables(
            workSession: $workSession,
        );

        // Add access-specific variables based on selected mode
        $mode = $validated['access_mode'];

        if (in_array($mode, ['code', 'all'])) {
            $variables['access_code'] = $workSession->access_code ?? '';
        }

        if (in_array($mode, ['link', 'all'])) {
            $variables['work_session_url'] = config('app.url').'/auth/code?code='.$workSession->access_code;
        }

        // Note: Credentials mode requires user context
        // This will be empty if there's no user associated with the recipients
        if (in_array($mode, ['credentials', 'all'])) {
            $variables['username_info'] = 'A belépési adatokat az egyéni fiókodhoz tartozó e-mailben küldtük ki.';
        }

        // Send email to each recipient
        $successCount = 0;
        $failedRecipients = [];

        foreach ($validated['recipients'] as $recipientEmail) {
            try {
                $this->emailService->sendFromTemplate(
                    template: $template,
                    recipientEmail: $recipientEmail,
                    variables: $variables,
                    recipientUser: null,
                    eventType: 'manual',
                    attachments: [],
                );
                $successCount++;
            } catch (\Exception $e) {
                $failedRecipients[] = $recipientEmail;
                Log::error('Failed to send manual email', [
                    'recipient' => $recipientEmail,
                    'work_session_id' => $workSession->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => "{$successCount} e-mail sikeresen elküldve",
            'success_count' => $successCount,
            'failed_count' => count($failedRecipients),
            'failed_recipients' => $failedRecipients,
        ]);
    }

    /**
     * Get work session details
     */
    public function show(WorkSession $workSession)
    {
        return response()->json([
            'data' => $workSession,
        ]);
    }
}
