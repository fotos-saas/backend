<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Actions\Tablo\SaveDraftAction;
use App\Actions\Tablo\SaveFinalizationAction;
use App\Actions\Tablo\UploadFinalizationFileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\Finalization\SaveDraftRequest;
use App\Http\Requests\Api\Tablo\Finalization\SaveFinalizationRequest;
use App\Models\TabloProject;
use App\Services\Tablo\FinalizationSecurityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TabloFinalizationController extends Controller
{
    public function getFinalizationData(Request $request): JsonResponse
    {
        $tabloProject = $this->getProject($request);

        if (! $tabloProject) {
            return $this->projectNotFound();
        }

        $data = $tabloProject->data ?? [];
        $contact = $tabloProject->contacts->firstWhere('pivot.is_primary', true)
            ?? $tabloProject->contacts->first();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $contact?->name,
                'contactEmail' => $contact?->email,
                'contactPhone' => $contact?->phone,
                'schoolName' => $tabloProject->school?->name,
                'schoolCity' => $tabloProject->school?->city,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'quote' => $data['quote'] ?? null,
                'fontFamily' => $data['font_family'] ?? null,
                'color' => $data['color'] ?? '#000000',
                'description' => $data['description'] ?? null,
                'background' => $data['background'] ?? null,
                'otherFile' => $data['other_file'] ?? null,
                'sortType' => $data['sort_type'] ?? 'abc',
                'studentDescription' => $data['student_description'] ?? null,
                'teacherDescription' => $data['teacher_description'] ?? null,
                'teacherResolutions' => $data['teacher_resolutions'] ?? null,
                'isFinalized' => ! empty($data['finalized_at']),
                'finalizedAt' => $data['finalized_at'] ?? null,
            ],
        ]);
    }

    public function saveFinalizationData(
        SaveFinalizationRequest $request,
        FinalizationSecurityService $security,
        SaveFinalizationAction $action,
    ): JsonResponse {
        $sanitized = $security->sanitizeFormData($request->all());
        $request->merge($sanitized);

        $tabloProject = $this->getProject($request);

        if (! $tabloProject) {
            return $this->projectNotFound();
        }

        $result = $action->execute($tabloProject, $request->validated(), $request->ip());

        return response()->json(
            ['success' => $result['success'], 'message' => $result['message']],
            $result['status'] ?? 200
        );
    }

    public function saveDraft(
        SaveDraftRequest $request,
        FinalizationSecurityService $security,
        SaveDraftAction $action,
    ): JsonResponse {
        $sanitized = $security->sanitizeFormData($request->all());
        $request->merge($sanitized);

        $tabloProject = $this->getProject($request, withRelations: false);

        if (! $tabloProject) {
            return $this->projectNotFound();
        }

        $result = $action->execute($tabloProject, $request->validated());

        return response()->json(
            array_filter(['success' => $result['success'], 'message' => $result['message'] ?? null]),
            $result['status'] ?? 200
        );
    }

    public function uploadFinalizationFile(
        Request $request,
        UploadFinalizationFileAction $action,
    ): JsonResponse {
        $request->validate([
            'file' => 'required|file|max:65536|mimes:jpg,jpeg,png,gif,webp,pdf,zip',
            'type' => 'required|string|in:background,attachment',
        ]);

        $tabloProject = $this->getProject($request, withRelations: false);

        if (! $tabloProject) {
            return $this->projectNotFound();
        }

        $result = $action->execute(
            $tabloProject,
            $request->file('file'),
            $request->input('type'),
            $request->ip()
        );

        $status = $result['status'] ?? 200;
        unset($result['status']);

        return response()->json($result, $status);
    }

    public function deleteFinalizationFile(Request $request, FinalizationSecurityService $security): JsonResponse
    {
        $validated = $request->validate([
            'fileId' => 'required|string|max:500',
        ]);

        $tabloProject = $this->getProject($request, withRelations: false);

        if (! $tabloProject) {
            return $this->projectNotFound();
        }

        $fileId = $validated['fileId'];
        $projectId = $tabloProject->id;

        if (! $security->validateFileOwnership($fileId, $projectId)) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs jogosultságod ehhez a fájlhoz!',
            ], 403);
        }

        if (! $security->validatePathTraversal($fileId, $projectId)) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen fájl útvonal!',
            ], 400);
        }

        $existingData = $tabloProject->data ?? [];

        if (($existingData['background'] ?? null) === $fileId) {
            Storage::disk('public')->delete($fileId);
            unset($existingData['background']);
        } else {
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

    public function generatePreviewPdf(Request $request): JsonResponse
    {
        $tabloProject = $this->getProject($request);

        if (! $tabloProject) {
            return $this->projectNotFound();
        }

        try {
            $requestData = $request->all();
            $projectData = $tabloProject->data ?? [];
            $contact = $tabloProject->contacts->first();

            $pdfData = [
                'name' => $requestData['name'] ?? $contact?->name ?? $tabloProject->name ?? '',
                'contactEmail' => $requestData['contactEmail'] ?? $contact?->email ?? '',
                'contactPhone' => $requestData['contactPhone'] ?? $contact?->phone ?? '',
                'schoolName' => $requestData['schoolName'] ?? $tabloProject->school?->name ?? $tabloProject->school_name ?? '',
                'schoolCity' => $requestData['schoolCity'] ?? $tabloProject->school?->city ?? $tabloProject->school_city ?? '',
                'className' => $requestData['className'] ?? $tabloProject->class_name ?? '',
                'classYear' => $requestData['classYear'] ?? $tabloProject->class_year ?? '',
                'quote' => $requestData['quote'] ?? $projectData['quote'] ?? '',
                'fontFamily' => $requestData['fontFamily'] ?? $projectData['font_family'] ?? '',
                'color' => $requestData['color'] ?? $projectData['color'] ?? '#000000',
                'description' => $requestData['description'] ?? $projectData['description'] ?? '',
                'sortType' => $requestData['sortType'] ?? $projectData['sort_type'] ?? 'abc',
                'studentDescription' => $requestData['studentDescription'] ?? $projectData['student_description'] ?? '',
                'teacherDescription' => $requestData['teacherDescription'] ?? $projectData['teacher_description'] ?? '',
            ];

            $projectId = $tabloProject->id;
            $pdf = Pdf::loadView('pdf.tablo-order-preview', ['data' => $pdfData])
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('defaultFont', 'DejaVu Sans');

            $filename = sprintf('preview-%d-%s.pdf', $projectId, now()->format('YmdHis'));
            $path = 'tablo-projects/' . $projectId . '/previews/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            $pdfUrl = config('app.url') . '/storage/' . $path;

            return response()->json([
                'success' => true,
                'pdfUrl' => $pdfUrl,
                'message' => 'PDF előnézet sikeresen elkészítve!',
            ]);
        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'project_id' => $tabloProject->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt a PDF generálása során. Kérjük, próbáld újra!',
            ], 500);
        }
    }

    private function getProject(Request $request, bool $withRelations = true): ?TabloProject
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        if ($withRelations) {
            return TabloProject::with(['school', 'contacts'])->find($projectId);
        }

        return TabloProject::find($projectId);
    }

    private function projectNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Projekt nem található',
        ], 404);
    }
}
