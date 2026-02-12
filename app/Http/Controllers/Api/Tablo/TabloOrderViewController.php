<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloProject;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TabloOrderViewController extends Controller
{
    /**
     * Get order data (megrendelési adatok) for a tablo project.
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

        $pdfUrl = null;
        if (! empty($data['order_form'])) {
            $pdfUrl = 'https://api.tablokiraly.hu/storage/'.$data['order_form'];
        }

        $studentCount = ! empty($data['student_description'])
            ? count(array_filter(preg_split('/[\r\n]+/', $data['student_description'])))
            : null;
        $teacherCount = ! empty($data['teacher_description'])
            ? count(array_filter(preg_split('/[\r\n]+/', $data['teacher_description'])))
            : null;

        $orderAnalysis = $tabloProject->latestOrderAnalysis;
        $aiSummary = $orderAnalysis?->ai_summary;
        $tags = $orderAnalysis?->tags ?? [];

        $contact = $tabloProject->contacts->first();

        return response()->json([
            'success' => true,
            'data' => [
                'contactName' => $contact?->name,
                'contactPhone' => $contact?->phone,
                'contactEmail' => $contact?->email,
                'schoolName' => $tabloProject->school?->name,
                'className' => $tabloProject->class_name,
                'classYear' => $tabloProject->class_year,
                'studentCount' => $studentCount,
                'teacherCount' => $teacherCount,
                'color' => $data['color'] ?? null,
                'fontFamily' => $data['font_family'] ?? null,
                'sortType' => $data['sort_type'] ?? null,
                'description' => $data['description'] ?? null,
                'studentDescription' => $data['student_description'] ?? null,
                'teacherDescription' => $data['teacher_description'] ?? null,
                'quote' => $data['quote'] ?? null,
                'aiSummary' => $aiSummary,
                'tags' => $tags,
                'pdfUrl' => $pdfUrl,
                'orderDate' => $data['original_created_at'] ?? $tabloProject->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * View order PDF (read-only - available for all authenticated users including preview/share)
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

        if (! $tabloProject->hasOrderData()) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs leadott megrendelés ehhez a projekthez',
            ], 404);
        }

        try {
            $projectData = $tabloProject->data ?? [];
            $contact = $tabloProject->contacts->first();

            $pdfData = [
                'name' => $contact?->name ?? $tabloProject->name ?? '',
                'contactEmail' => $contact?->email ?? '',
                'contactPhone' => $contact?->phone ?? '',
                'schoolName' => $tabloProject->school?->name ?? $tabloProject->school_name ?? '',
                'schoolCity' => $tabloProject->school?->city ?? $tabloProject->school_city ?? '',
                'className' => $tabloProject->class_name ?? '',
                'classYear' => $tabloProject->class_year ?? '',
                'quote' => $projectData['quote'] ?? '',
                'fontFamily' => $projectData['font_family'] ?? '',
                'color' => $projectData['color'] ?? '#000000',
                'description' => $projectData['description'] ?? '',
                'sortType' => $projectData['sort_type'] ?? 'abc',
                'studentDescription' => $projectData['student_description'] ?? '',
                'teacherDescription' => $projectData['teacher_description'] ?? '',
            ];

            $pdf = Pdf::loadView('pdf.tablo-order-preview', ['data' => $pdfData])
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('defaultFont', 'DejaVu Sans');

            $filename = sprintf('order-view-%d-%s.pdf', $projectId, now()->format('YmdHis'));
            $path = 'tablo-projects/' . $projectId . '/views/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            $pdfUrl = config('app.url') . '/storage/' . $path;

            return response()->json([
                'success' => true,
                'pdfUrl' => $pdfUrl,
                'message' => 'Megrendelőlap sikeresen elkészítve!',
            ]);
        } catch (\Exception $e) {
            Log::error('Order PDF view generation failed', [
                'project_id' => $projectId,
                'error' => 'Hiba történt a művelet során.',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hiba történt az előnézet generálásakor',
            ], 500);
        }
    }
}
