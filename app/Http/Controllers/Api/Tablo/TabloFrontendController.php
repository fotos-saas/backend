<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloContact;
use App\Models\TabloProject;
use App\Models\TabloSampleTemplate;
use App\Models\TabloSchool;
use App\Services\Tablo\FinalizationSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Tablo Frontend Controller
 *
 * API végpontok a frontend-tablo Angular alkalmazáshoz.
 * Minden végpont auth:sanctum + CheckTabloProjectStatus middleware mögött van.
 */
class TabloFrontendController extends Controller
{
    /**
     * Get samples (minta képek) for a tablo project.
     * Legújabb elől rendezve, dátummal.
     */
    public function getSamples(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Get samples from media collection, ordered by newest first
        // Only return active samples
        $samples = $tabloProject->getMedia('samples')
            ->filter(fn ($media) => $media->getCustomProperty('is_active', true))
            ->sortByDesc('created_at')
            ->map(function ($media) {
                // Convert full URL to relative path for Angular proxy compatibility
                $url = $media->getUrl();
                $thumbUrl = $media->getUrl('thumb');

                // Extract path from full URL (remove http://localhost:8000 or similar)
                $urlPath = parse_url($url, PHP_URL_PATH);
                $thumbPath = parse_url($thumbUrl, PHP_URL_PATH);

                return [
                    'id' => $media->id,
                    'fileName' => $media->file_name,
                    'url' => $urlPath,
                    'thumbUrl' => $thumbPath,
                    'description' => $media->getCustomProperty('description'),
                    'createdAt' => $media->created_at->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $samples,
            'totalCount' => $samples->count(),
        ]);
    }

    /**
     * Get order data (megrendelési adatok) for a tablo project.
     * Visszaadja a projekt data mezőjéből az eredeti megrendelési adatokat,
     * és ha van TabloOrderAnalysis, annak az összegzését is.
     */
    public function getOrderData(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['school', 'contacts'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $data = $tabloProject->data ?? [];

        // Check if we have any order data from API
        $hasOrderData = ! empty($data['description'])
            || ! empty($data['student_description'])
            || ! empty($data['teacher_description'])
            || ! empty($data['order_form']);

        if (! $hasOrderData) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Nincs még leadott megrendelés',
            ]);
        }

        // Build PDF URL from api.tablokiraly.hu
        $pdfUrl = null;
        if (! empty($data['order_form'])) {
            $pdfUrl = 'https://api.tablokiraly.hu/storage/'.$data['order_form'];
        }

        // Count students and teachers from description
        $studentCount = ! empty($data['student_description'])
            ? count(array_filter(preg_split('/[\r\n]+/', $data['student_description'])))
            : null;
        $teacherCount = ! empty($data['teacher_description'])
            ? count(array_filter(preg_split('/[\r\n]+/', $data['teacher_description'])))
            : null;

        // Get order analysis summary if exists
        $orderAnalysis = $tabloProject->latestOrderAnalysis;
        $aiSummary = $orderAnalysis?->ai_summary;
        $tags = $orderAnalysis?->tags ?? [];

        // Get first contact
        $contact = $tabloProject->contacts->first();

        return response()->json([
            'success' => true,
            'data' => [
                // Kontakt
                'contactName' => $contact?->name,
                'contactPhone' => $contact?->phone,
                'contactEmail' => $contact?->email,

                // Iskola/osztály
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,

                // Létszám
                'studentCount' => $studentCount,
                'teacherCount' => $teacherCount,

                // Design beállítások
                'color' => $data['color'] ?? null,
                'fontFamily' => $data['font_family'] ?? null,
                'sortType' => $data['sort_type'] ?? null,

                // Leírások
                'description' => $data['description'] ?? null,
                'studentDescription' => $data['student_description'] ?? null,
                'teacherDescription' => $data['teacher_description'] ?? null,
                'quote' => $data['quote'] ?? null,

                // AI elemzés (ha van)
                'aiSummary' => $aiSummary,
                'tags' => $tags,

                // PDF
                'pdfUrl' => $pdfUrl,

                // Dátum (API-ból jövő eredeti created_at)
                'orderDate' => $data['original_created_at'] ?? $tabloProject->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get project info (projekt alapadatok).
     */
    public function getProjectInfo(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['school', 'partner.users', 'missingPersons', 'tabloStatus', 'gallery'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Get ügyintézők (partner users with tablo role)
        $coordinators = $tabloProject->partner?->users
            ->filter(fn ($user) => $user->hasRole('tablo'))
            ->map(fn ($user) => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->values() ?? collect();

        // Count missing persons
        $missingPersonsCount = $tabloProject->missingPersons->count();

        // Count active polls
        $activePollsCount = $tabloProject->polls()->active()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tabloProject->id,
                'name' => $tabloProject->display_name,
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'status' => $tabloProject->status->value,
                'hasOrderAnalysis' => $tabloProject->hasOrderAnalysis(),
                'samplesCount' => $tabloProject->getMedia('samples')->count(),
                'coordinators' => $coordinators,
                // Has missing persons flag for navbar menu
                'hasMissingPersons' => $missingPersonsCount > 0,
                // Has template chooser flag - shown if there are active templates
                'hasTemplateChooser' => TabloSampleTemplate::active()->exists(),
                // Active polls count for voting menu
                'activePollsCount' => $activePollsCount,
                // Tablo status - structured status from TabloStatus model
                'tabloStatus' => $tabloProject->tabloStatus?->toApiResponse(),
                // Legacy user status fields (deprecated)
                'userStatus' => $tabloProject->tabloStatus?->name ?? $tabloProject->user_status,
                'userStatusColor' => $tabloProject->tabloStatus?->color ?? $tabloProject->user_status_color,
                // Work session ID for photo selection workflow
                'workSessionId' => $tabloProject->work_session_id,
                // Has photo selection enabled (work session attached)
                'hasPhotoSelection' => $tabloProject->work_session_id !== null,
                // Gallery ID for gallery view (if no work session)
                'tabloGalleryId' => $tabloProject->tablo_gallery_id,
                // Has gallery attached
                'hasGallery' => $tabloProject->gallery !== null,
            ],
        ]);
    }

    /**
     * Get finalization data - existing data for prefill.
     * Konzisztens az api.tablokiraly.hu mezőneveivel.
     */
    public function getFinalizationData(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['school', 'contacts'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $data = $tabloProject->data ?? [];
        $contact = $tabloProject->contacts->where('is_primary', true)->first()
            ?? $tabloProject->contacts->first();

        return response()->json([
            'success' => true,
            'data' => [
                // Step 1: Kapcsolattartó
                'name' => $contact?->name,
                'contactEmail' => $contact?->email,
                'contactPhone' => $contact?->phone,

                // Step 2: Alap adatok
                'schoolName' => $tabloProject->school?->name,
                'schoolCity' => $tabloProject->school?->city,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'quote' => $data['quote'] ?? null,

                // Step 3: Elképzelés
                'fontFamily' => $data['font_family'] ?? null,
                'color' => $data['color'] ?? '#000000',
                'description' => $data['description'] ?? null,
                'background' => $data['background'] ?? null,
                'otherFile' => $data['other_file'] ?? null,

                // Step 4: Névsor
                'sortType' => $data['sort_type'] ?? 'abc',
                'studentDescription' => $data['student_description'] ?? null,
                'teacherDescription' => $data['teacher_description'] ?? null,

                // Meta
                'isFinalized' => ! empty($data['finalized_at']),
                'finalizedAt' => $data['finalized_at'] ?? null,
            ],
        ]);
    }

    /**
     * Save finalization data - save order form data.
     * Konzisztens az api.tablokiraly.hu mezőneveivel.
     */
    public function saveFinalizationData(Request $request, FinalizationSecurityService $security): JsonResponse
    {
        // Input sanitization BEFORE validation (XSS védelem)
        $sanitized = $security->sanitizeFormData($request->all());
        $request->merge($sanitized);

        $validated = $request->validate([
            // Step 1: Kapcsolattartó
            'name' => 'required|string|max:255',
            'contactEmail' => 'required|email|max:255',
            'contactPhone' => ['required', 'string', 'max:50', 'regex:/^[\d\s\+\-\(\)]+$/'],

            // Step 2: Alap adatok
            'schoolName' => 'required|string|max:255',
            'schoolCity' => 'nullable|string|max:255',
            'className' => 'required|string|max:255',
            'classYear' => ['required', 'string', 'max:50'],
            'quote' => 'nullable|string|max:1000',

            // Step 3: Elképzelés
            'fontFamily' => 'nullable|string|max:255',
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'description' => 'nullable|string|max:5000',

            // Step 4: Névsor
            'sortType' => 'nullable|string|in:abc,kozepre,megjegyzesben,mindegy',
            'studentDescription' => 'required|string',
            'teacherDescription' => 'required|string',
            'acceptTerms' => 'required|accepted',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['school', 'contacts'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Update or create primary contact
            $contact = $tabloProject->contacts->where('is_primary', true)->first();
            if ($contact) {
                $contact->update([
                    'name' => $validated['name'],
                    'email' => $validated['contactEmail'],
                    'phone' => $validated['contactPhone'],
                ]);
            } else {
                TabloContact::create([
                    'tablo_project_id' => $projectId,
                    'name' => $validated['name'],
                    'email' => $validated['contactEmail'],
                    'phone' => $validated['contactPhone'],
                    'is_primary' => true,
                ]);
            }

            // Update or create school
            if (! empty($validated['schoolName'])) {
                $school = $tabloProject->school;
                if ($school) {
                    $school->update([
                        'name' => $validated['schoolName'],
                        'city' => $validated['schoolCity'] ?? $school->city,
                    ]);
                } else {
                    $school = TabloSchool::create([
                        'name' => $validated['schoolName'],
                        'city' => $validated['schoolCity'],
                    ]);
                    $tabloProject->school_id = $school->id;
                }
            }

            // Update project basic info
            $tabloProject->class_name = $validated['className'];
            $tabloProject->class_year = $validated['classYear'];

            // Update project data JSON
            $existingData = $tabloProject->data ?? [];
            $tabloProject->data = array_merge($existingData, [
                'quote' => $validated['quote'],
                'font_family' => $validated['fontFamily'],
                'color' => $validated['color'],
                'description' => $validated['description'],
                'sort_type' => $validated['sortType'] ?? 'abc',
                'student_description' => $validated['studentDescription'],
                'teacher_description' => $validated['teacherDescription'],
                'finalized_at' => now()->toIso8601String(),
                'finalized_from' => 'frontend-tablo',
            ]);

            $tabloProject->save();

            DB::commit();

            // Audit logging
            $security->logSecurityEvent('finalization_saved', $projectId, [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Megrendelés sikeresen véglegesítve!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Log the real error internally (NEM a user-nek!)
            Log::error('Finalization save failed', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Generic error message to user (NO exception details!)
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a mentés során. Kérjük, próbáld újra később!',
            ], 500);
        }
    }

    /**
     * Auto-save draft - save partial form data without validation.
     * Debounced frontend hívásokhoz, nem követeli meg az összes kötelező mezőt.
     */
    public function saveDraft(Request $request, FinalizationSecurityService $security): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Input sanitization (XSS védelem)
        $sanitized = $security->sanitizeFormData($request->all());

        // Laza validáció - csak formátum ellenőrzés, nem required
        $validated = $request->merge($sanitized)->validate([
            'name' => 'nullable|string|max:255',
            'contactEmail' => 'nullable|email|max:255',
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'schoolName' => 'nullable|string|max:255',
            'schoolCity' => 'nullable|string|max:255',
            'className' => 'nullable|string|max:255',
            'classYear' => 'nullable|string|max:50',
            'quote' => 'nullable|string|max:1000',
            'fontFamily' => 'nullable|string|max:255',
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'description' => 'nullable|string|max:5000',
            'sortType' => 'nullable|string|in:abc,kozepre,megjegyzesben,mindegy',
            'studentDescription' => 'nullable|string',
            'teacherDescription' => 'nullable|string',
        ]);

        try {
            // Update project data JSON (csak ami van)
            $existingData = $tabloProject->data ?? [];

            // Csak a megadott mezőket frissítjük
            if (isset($validated['quote'])) {
                $existingData['quote'] = $validated['quote'];
            }
            if (isset($validated['fontFamily'])) {
                $existingData['font_family'] = $validated['fontFamily'];
            }
            if (isset($validated['color'])) {
                $existingData['color'] = $validated['color'];
            }
            if (isset($validated['description'])) {
                $existingData['description'] = $validated['description'];
            }
            if (isset($validated['sortType'])) {
                $existingData['sort_type'] = $validated['sortType'];
            }
            if (isset($validated['studentDescription'])) {
                $existingData['student_description'] = $validated['studentDescription'];
            }
            if (isset($validated['teacherDescription'])) {
                $existingData['teacher_description'] = $validated['teacherDescription'];
            }

            // Draft mentés időbélyeg
            $existingData['draft_saved_at'] = now()->toIso8601String();

            $tabloProject->data = $existingData;

            // Alap mezők (ha megadták)
            if (! empty($validated['className'])) {
                $tabloProject->class_name = $validated['className'];
            }
            if (! empty($validated['classYear'])) {
                $tabloProject->class_year = $validated['classYear'];
            }

            $tabloProject->save();

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Draft save failed', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a mentés során.',
            ], 500);
        }
    }

    /**
     * Upload file for finalization (background image or attachment).
     * Magic bytes validációval a MIME spoofing ellen.
     */
    public function uploadFinalizationFile(Request $request, FinalizationSecurityService $security): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:65536', // 64MB max
            'type' => 'required|string|in:background,attachment',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $file = $request->file('file');
        $type = $validated['type'];
        $extension = strtolower($file->getClientOriginalExtension());

        // Validate based on upload type
        if ($type === 'background') {
            // Check extension whitelist
            if (! in_array($extension, $security->getAllowedImageExtensions(), true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Csak JPG, JPEG vagy BMP fájl tölthető fel háttérképként!',
                ], 422);
            }

            // Magic bytes validation (MIME spoofing ellen)
            if (! $security->validateImageMagicBytes($file)) {
                $security->logSecurityEvent('invalid_image_magic_bytes', $projectId, [
                    'filename' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'A fájl tartalma nem egyezik a kiterjesztéssel!',
                ], 422);
            }

            // Size limit
            if ($file->getSize() > 16 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'A háttérkép maximum 16MB lehet!',
                ], 422);
            }
        } else {
            // Check extension whitelist
            if (! in_array($extension, $security->getAllowedArchiveExtensions(), true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Csak ZIP, RAR vagy 7Z fájl tölthető fel csatolmányként!',
                ], 422);
            }

            // Magic bytes validation
            if (! $security->validateArchiveMagicBytes($file)) {
                $security->logSecurityEvent('invalid_archive_magic_bytes', $projectId, [
                    'filename' => $file->getClientOriginalName(),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'A fájl tartalma nem egyezik a kiterjesztéssel!',
                ], 422);
            }
        }

        // Generate safe filename (path traversal védelem)
        $originalName = $file->getClientOriginalName();
        $filename = $security->generateSafeFilename($originalName, $extension);

        // Store file
        $path = $file->storeAs(
            "tablo-projects/{$projectId}/{$type}",
            $filename,
            'public'
        );

        // Update project data
        $existingData = $tabloProject->data ?? [];
        if ($type === 'background') {
            // Delete old background if exists
            if (! empty($existingData['background'])) {
                Storage::disk('public')->delete($existingData['background']);
            }
            $existingData['background'] = $path;
        } else {
            // Add to other_file array
            $otherFiles = $existingData['other_files'] ?? [];
            $otherFiles[] = [
                'path' => $path,
                'filename' => $originalName,
                'uploaded_at' => now()->toIso8601String(),
            ];
            $existingData['other_files'] = $otherFiles;
        }

        $tabloProject->data = $existingData;
        $tabloProject->save();

        $security->logSecurityEvent('file_uploaded', $projectId, [
            'type' => $type,
            'filename' => $filename,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fájl sikeresen feltöltve!',
            'fileId' => $path,
            'filename' => $originalName,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Delete uploaded file.
     * IDOR és path traversal védelemmel.
     */
    public function deleteFinalizationFile(Request $request, FinalizationSecurityService $security): JsonResponse
    {
        $validated = $request->validate([
            'fileId' => 'required|string|max:500',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $fileId = $validated['fileId'];

        // IDOR védelem: ellenőrizzük, hogy a fájl ehhez a projekthez tartozik-e
        if (! $security->validateFileOwnership($fileId, $projectId)) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs jogosultságod ehhez a fájlhoz!',
            ], 403);
        }

        // Path traversal védelem
        if (! $security->validatePathTraversal($fileId, $projectId)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen fájl útvonal!',
            ], 400);
        }

        $existingData = $tabloProject->data ?? [];

        // Check if it's background
        if (($existingData['background'] ?? null) === $fileId) {
            Storage::disk('public')->delete($fileId);
            unset($existingData['background']);
        } else {
            // Check in other_files
            $otherFiles = $existingData['other_files'] ?? [];
            $otherFiles = array_filter($otherFiles, function ($file) use ($fileId) {
                if ($file['path'] === $fileId) {
                    Storage::disk('public')->delete($fileId);

                    return false;
                }

                return true;
            });
            $existingData['other_files'] = array_values($otherFiles);
        }

        $tabloProject->data = $existingData;
        $tabloProject->save();

        $security->logSecurityEvent('file_deleted', $projectId, [
            'file_id' => $fileId,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fájl sikeresen törölve!',
        ]);
    }

    /**
     * Update primary contact from home page.
     * Token-ból azonosítja a projektet.
     * Input sanitization a XSS védelem miatt.
     */
    public function updateContact(Request $request, FinalizationSecurityService $security): JsonResponse
    {
        // Input sanitization BEFORE validation (XSS védelem)
        $request->merge([
            'name' => $security->sanitizeInput($request->input('name')),
            'email' => $security->sanitizeInput($request->input('email')),
            'phone' => $security->sanitizePhone($request->input('phone')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:50', 'regex:/^[\d\s\+\-\(\)]+$/'],
        ], [
            'name.required' => 'A név megadása kötelező.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
            'phone.required' => 'A telefonszám megadása kötelező.',
            'phone.regex' => 'Érvénytelen telefonszám formátum.',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['contacts'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Update or create primary contact
        $contact = $tabloProject->contacts->where('is_primary', true)->first()
            ?? $tabloProject->contacts->first();

        if ($contact) {
            $contact->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ]);
        } else {
            $contact = TabloContact::create([
                'tablo_project_id' => $projectId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'is_primary' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kapcsolattartó sikeresen frissítve!',
            'data' => [
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ],
        ]);
    }

    /**
     * Generate preview PDF from current finalization data.
     * Generates a PDF document and returns URL to download/view.
     */
    public function generatePreviewPdf(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['school', 'contacts'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        try {
            // Collect data for PDF from project and request (current form state)
            $requestData = $request->all();
            $projectData = $tabloProject->data ?? [];
            $contact = $tabloProject->contacts->first();

            // Merge data: request data takes priority, then project data, then project fields
            $pdfData = [
                // Step 1: Contact
                'name' => $requestData['name'] ?? $contact?->name ?? $tabloProject->name ?? '',
                'contactEmail' => $requestData['contactEmail'] ?? $contact?->email ?? '',
                'contactPhone' => $requestData['contactPhone'] ?? $contact?->phone ?? '',

                // Step 2: Basic info
                'schoolName' => $requestData['schoolName'] ?? $tabloProject->school?->name ?? $tabloProject->school_name ?? '',
                'schoolCity' => $requestData['schoolCity'] ?? $tabloProject->school?->city ?? $tabloProject->school_city ?? '',
                'className' => $requestData['className'] ?? $tabloProject->class_name ?? '',
                'classYear' => $requestData['classYear'] ?? $tabloProject->class_year ?? '',
                'quote' => $requestData['quote'] ?? $projectData['quote'] ?? '',

                // Step 3: Design
                'fontFamily' => $requestData['fontFamily'] ?? $projectData['font_family'] ?? '',
                'color' => $requestData['color'] ?? $projectData['color'] ?? '#000000',
                'description' => $requestData['description'] ?? $projectData['description'] ?? '',

                // Step 4: Roster
                'sortType' => $requestData['sortType'] ?? $projectData['sort_type'] ?? 'abc',
                'studentDescription' => $requestData['studentDescription'] ?? $projectData['student_description'] ?? '',
                'teacherDescription' => $requestData['teacherDescription'] ?? $projectData['teacher_description'] ?? '',
            ];

            // Generate PDF
            $pdf = Pdf::loadView('pdf.tablo-order-preview', ['data' => $pdfData])
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('defaultFont', 'DejaVu Sans');

            // Generate unique filename
            $filename = sprintf(
                'preview-%d-%s.pdf',
                $projectId,
                now()->format('YmdHis')
            );

            // Save to storage
            $path = 'tablo-projects/' . $projectId . '/previews/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            // Return URL
            $pdfUrl = config('app.url') . '/storage/' . $path;

            return response()->json([
                'success' => true,
                'pdfUrl' => $pdfUrl,
                'message' => 'PDF előnézet sikeresen elkészítve!',
            ]);
        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a PDF generálása során. Kérjük, próbáld újra!',
            ], 500);
        }
    }

    /**
     * Get gallery photos for the project.
     *
     * Returns all photos from the project's attached gallery.
     * If no gallery is attached, returns empty data.
     */
    public function getGalleryPhotos(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with('gallery')->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        if (! $tabloProject->gallery) {
            return response()->json([
                'success' => true,
                'message' => 'Nincs galéria csatolva a projekthez',
                'data' => [],
                'gallery' => null,
            ]);
        }

        $photos = $tabloProject->gallery->getMedia('photos')
            ->map(function ($media) {
                // Convert full URL to relative path for Angular proxy compatibility
                $url = $media->getUrl();
                $thumbUrl = $media->getUrl('thumb');
                $previewUrl = $media->getUrl('preview');

                // Extract path from full URL (remove http://localhost:8000 or similar)
                $urlPath = parse_url($url, PHP_URL_PATH);
                $thumbPath = parse_url($thumbUrl, PHP_URL_PATH);
                $previewPath = parse_url($previewUrl, PHP_URL_PATH);

                return [
                    'id' => $media->id,
                    'url' => $urlPath,
                    'thumbUrl' => $thumbPath,
                    'previewUrl' => $previewPath,
                    'fileName' => $media->file_name,
                    'size' => $media->human_readable_size,
                    'createdAt' => $media->created_at->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $photos,
            'gallery' => [
                'id' => $tabloProject->gallery->id,
                'name' => $tabloProject->gallery->name,
                'photosCount' => $photos->count(),
            ],
        ]);
    }

    /**
     * View order PDF (read-only - available for all authenticated users including preview/share)
     *
     * Generates PDF from saved project data only (not from request).
     * This endpoint is accessible by all authenticated users, including preview and share tokens.
     */
    public function viewOrderPdf(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::with(['school', 'contacts'])->find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Check if project has order data (completed finalization)
        if (! $tabloProject->hasOrderData()) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs leadott megrendelés ehhez a projekthez',
            ], 404);
        }

        try {
            // Use only saved project data (read-only)
            $projectData = $tabloProject->data ?? [];
            $contact = $tabloProject->contacts->first();

            $pdfData = [
                // Contact
                'name' => $contact?->name ?? $tabloProject->name ?? '',
                'contactEmail' => $contact?->email ?? '',
                'contactPhone' => $contact?->phone ?? '',

                // Basic info
                'schoolName' => $tabloProject->school?->name ?? $tabloProject->school_name ?? '',
                'schoolCity' => $tabloProject->school?->city ?? $tabloProject->school_city ?? '',
                'className' => $tabloProject->class_name ?? '',
                'classYear' => $tabloProject->class_year ?? '',
                'quote' => $projectData['quote'] ?? '',

                // Design
                'fontFamily' => $projectData['font_family'] ?? '',
                'color' => $projectData['color'] ?? '#000000',
                'description' => $projectData['description'] ?? '',

                // Roster
                'sortType' => $projectData['sort_type'] ?? 'abc',
                'studentDescription' => $projectData['student_description'] ?? '',
                'teacherDescription' => $projectData['teacher_description'] ?? '',
            ];

            // Generate PDF
            $pdf = Pdf::loadView('pdf.tablo-order-preview', ['data' => $pdfData])
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('defaultFont', 'DejaVu Sans');

            // Generate unique filename
            $filename = sprintf(
                'order-view-%d-%s.pdf',
                $projectId,
                now()->format('YmdHis')
            );

            // Save to storage
            $path = 'tablo-projects/' . $projectId . '/views/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            // Return URL
            $pdfUrl = config('app.url') . '/storage/' . $path;

            return response()->json([
                'success' => true,
                'pdfUrl' => $pdfUrl,
                'message' => 'Megrendelőlap sikeresen elkészítve!',
            ]);
        } catch (\Exception $e) {
            Log::error('Order PDF view generation failed', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt az előnézet generálásakor',
            ], 500);
        }
    }
}
