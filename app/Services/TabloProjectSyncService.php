<?php

namespace App\Services;

use App\Enums\TabloProjectStatus;
use App\Models\TabloContact;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TabloProjectSyncService
{
    private string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.tablokiraly_api.url', 'https://api.tablokiraly.hu');
    }

    /**
     * Ellenőrzi hány új projekt érhető el az API-ban
     */
    public function checkNewProjectsCount(): int
    {
        $existingExternalIds = TabloProject::whereNotNull('external_id')
            ->pluck('external_id')
            ->toArray();

        $response = Http::timeout(30)->post("{$this->apiUrl}/api/projects/for-photo-stack", [
            'exclude_ids' => $existingExternalIds,
            'has_order' => false,
            'limit' => 500,
        ]);

        if (! $response->successful()) {
            Log::warning('TabloProjectSync: API hiba az ellenőrzésnél', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 0;
        }

        return $response->json('count', 0);
    }

    /**
     * Szinkronizálja az új projekteket az API-ból
     *
     * @return array ['created' => int, 'errors' => array]
     */
    public function syncNewProjects(): array
    {
        $existingExternalIds = TabloProject::whereNotNull('external_id')
            ->pluck('external_id')
            ->toArray();

        $response = Http::timeout(60)->post("{$this->apiUrl}/api/projects/for-photo-stack", [
            'exclude_ids' => $existingExternalIds,
            'has_order' => false,
            'limit' => 500,
        ]);

        if (! $response->successful()) {
            Log::error('TabloProjectSync: API hiba a szinkronizálásnál', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'created' => 0,
                'errors' => ['API hiba: '.$response->status()],
            ];
        }

        $projects = $response->json('data', []);
        $created = 0;
        $errors = [];

        foreach ($projects as $apiProject) {
            try {
                $this->createProjectFromApi($apiProject);
                $created++;
            } catch (\Exception $e) {
                Log::error('TabloProjectSync: Projekt létrehozási hiba', [
                    'external_id' => $apiProject['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "ID {$apiProject['id']}: {$e->getMessage()}";
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
        ];
    }

    /**
     * Létrehoz egy projektet az API adataiból
     */
    private function createProjectFromApi(array $apiProject): TabloProject
    {
        // Iskola keresése vagy létrehozása
        $school = null;
        if (! empty($apiProject['school'])) {
            $school = $this->findOrCreateSchool($apiProject['school']);
        }

        // Projekt létrehozása
        $project = TabloProject::create([
            'external_id' => $apiProject['id'],
            'school_id' => $school?->id,
            'partner_id' => $apiProject['partner_id'] ?? null,
            'name' => $apiProject['full_name'] ?? null,
            'class_name' => $apiProject['class_name'] ?? null,
            'class_year' => $apiProject['class_year'] ?? null,
            'status' => TabloProjectStatus::NotStarted,
            'is_aware' => false,
            'data' => $this->buildDataArray($apiProject),
            'sync_at' => now(),
        ]);

        // Kontakt létrehozása ha van
        if (! empty($apiProject['contact'])) {
            $this->createContact($project, $apiProject['contact']);
        }

        return $project;
    }

    /**
     * Iskola keresése vagy létrehozása
     */
    private function findOrCreateSchool(array $schoolData): TabloSchool
    {
        // Először ID alapján keresünk
        if (! empty($schoolData['id'])) {
            $school = TabloSchool::where('local_id', $schoolData['id'])->first();
            if ($school) {
                return $school;
            }
        }

        // Aztán név alapján
        if (! empty($schoolData['name'])) {
            $school = TabloSchool::where('name', $schoolData['name'])->first();
            if ($school) {
                // Ha megvan név alapján, frissítjük a local_id-t
                if (! empty($schoolData['id']) && empty($school->local_id)) {
                    $school->update(['local_id' => $schoolData['id']]);
                }

                return $school;
            }
        }

        // Létrehozzuk
        return TabloSchool::create([
            'local_id' => $schoolData['id'] ?? null,
            'name' => $schoolData['name'] ?? 'Ismeretlen iskola',
            'city' => $schoolData['city'] ?? null,
        ]);
    }

    /**
     * Kontakt létrehozása a projekthez
     */
    private function createContact(TabloProject $project, array $contactData): TabloContact
    {
        return TabloContact::create([
            'tablo_project_id' => $project->id,
            'name' => $contactData['name'] ?? null,
            'email' => $contactData['email'] ?? null,
            'phone' => $contactData['phone'] ?? null,
        ]);
    }

    /**
     * Extra adatok összeállítása a data mezőbe
     */
    private function buildDataArray(array $apiProject): array
    {
        return [
            'uuid' => $apiProject['uuid'] ?? null,
            'quote' => $apiProject['quote'] ?? null,
            'description' => $apiProject['description'] ?? null,
            'color' => $apiProject['color'] ?? null,
            'font_family' => $apiProject['font_family'] ?? null,
            'sort_type' => $apiProject['sort_type'] ?? null,
            'student_description' => $apiProject['student_description'] ?? null,
            'teacher_description' => $apiProject['teacher_description'] ?? null,
            'original_status_id' => $apiProject['status_id'] ?? null,
            'original_created_at' => $apiProject['created_at'] ?? null,
            'original_updated_at' => $apiProject['updated_at'] ?? null,
            'our_replied_at' => $apiProject['our_replied_at'] ?? null,
            'all_contacts' => $apiProject['all_contacts'] ?? [],
        ];
    }
}
