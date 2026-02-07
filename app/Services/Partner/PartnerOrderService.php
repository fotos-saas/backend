<?php

namespace App\Services\Partner;

use App\Models\TabloProject;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Partner megrendelés logika - rendelés adatok lekérése és PDF generálás.
 */
class PartnerOrderService
{
    /**
     * Megrendelés adatok lekérése egy projekthez.
     */
    public function getOrderData(TabloProject $project): array
    {
        $project->load(['school', 'contacts']);
        $data = $project->data ?? [];

        $hasOrderData = ! empty($data['description'])
            || ! empty($data['student_description'])
            || ! empty($data['teacher_description'])
            || ! empty($data['order_form']);

        if (! $hasOrderData) {
            return ['hasData' => false];
        }

        $pdfUrl = ! empty($data['order_form'])
            ? 'https://api.tablokiraly.hu/storage/' . $data['order_form']
            : null;

        $studentCount = ! empty($data['student_description'])
            ? count(array_filter(preg_split('/[\r\n]+/', $data['student_description'])))
            : null;
        $teacherCount = ! empty($data['teacher_description'])
            ? count(array_filter(preg_split('/[\r\n]+/', $data['teacher_description'])))
            : null;

        $orderAnalysis = $project->latestOrderAnalysis;
        $contact = $project->contacts->first();

        return [
            'hasData' => true,
            'data' => [
                'contactName' => $contact?->name,
                'contactPhone' => $contact?->phone,
                'contactEmail' => $contact?->email,
                'schoolName' => $project->school?->name,
                'className' => $project->class_name,
                'classYear' => $project->class_year,
                'studentCount' => $studentCount,
                'teacherCount' => $teacherCount,
                'color' => $data['color'] ?? null,
                'fontFamily' => $data['font_family'] ?? null,
                'sortType' => $data['sort_type'] ?? null,
                'description' => $data['description'] ?? null,
                'studentDescription' => $data['student_description'] ?? null,
                'teacherDescription' => $data['teacher_description'] ?? null,
                'quote' => $data['quote'] ?? null,
                'aiSummary' => $orderAnalysis?->ai_summary,
                'tags' => $orderAnalysis?->tags ?? [],
                'pdfUrl' => $pdfUrl,
                'orderDate' => $data['original_created_at'] ?? $project->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Megrendelőlap PDF generálása.
     *
     * @return array{success: bool, pdfUrl?: string, message: string}
     */
    public function generateOrderPdf(TabloProject $project): array
    {
        $project->load(['school', 'contacts']);

        if (! $project->hasOrderData()) {
            return [
                'success' => false,
                'message' => 'Nincs leadott megrendelés ehhez a projekthez',
            ];
        }

        try {
            $pdfData = $this->buildPdfData($project);

            $pdf = Pdf::loadView('pdf.tablo-order-preview', ['data' => $pdfData])
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('defaultFont', 'DejaVu Sans');

            $filename = sprintf('order-view-%d-%s.pdf', $project->id, now()->format('YmdHis'));
            $path = 'tablo-projects/' . $project->id . '/views/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            return [
                'success' => true,
                'pdfUrl' => config('app.url') . '/storage/' . $path,
                'message' => 'Megrendelőlap sikeresen elkészítve!',
            ];
        } catch (\Exception $e) {
            Log::error('Partner order PDF view generation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Hiba történt az előnézet generálásakor',
            ];
        }
    }

    /**
     * PDF adatok összeállítása a megrendelőlaphoz.
     */
    private function buildPdfData(TabloProject $project): array
    {
        $projectData = $project->data ?? [];
        $contact = $project->contacts->first();

        return [
            'name' => $contact?->name ?? $project->name ?? '',
            'contactEmail' => $contact?->email ?? '',
            'contactPhone' => $contact?->phone ?? '',
            'schoolName' => $project->school?->name ?? $project->school_name ?? '',
            'schoolCity' => $project->school?->city ?? $project->school_city ?? '',
            'className' => $project->class_name ?? '',
            'classYear' => $project->class_year ?? '',
            'quote' => $projectData['quote'] ?? '',
            'fontFamily' => $projectData['font_family'] ?? '',
            'color' => $projectData['color'] ?? '#000000',
            'description' => $projectData['description'] ?? '',
            'sortType' => $projectData['sort_type'] ?? 'abc',
            'studentDescription' => $projectData['student_description'] ?? '',
            'teacherDescription' => $projectData['teacher_description'] ?? '',
        ];
    }
}
