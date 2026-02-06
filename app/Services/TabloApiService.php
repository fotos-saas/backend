<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service a live Tabló API kommunikációhoz.
 * Az api.tablokiraly.hu-ból kéri le a projekt adatokat.
 */
class TabloApiService
{
    protected string $baseUrl;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.tablokiraly_api.url', 'https://api.tablokiraly.hu');
        $this->timeout = config('services.tablokiraly_api.timeout', 30);
    }

    /**
     * Projekt teljes részleteinek lekérése.
     *
     * @param  int  $projectId  Live API projekt ID
     * @return array|null Projekt adatok vagy null ha nem található
     */
    public function getProjectDetails(int $projectId): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/projects/{$projectId}/full-details");

            if ($response->successful()) {
                $data = $response->json();
                Log::info('TabloApiService: Projekt lekérve', [
                    'project_id' => $projectId,
                    'students_count' => count($data['students'] ?? []),
                    'teachers_count' => count($data['teachers'] ?? []),
                ]);

                return $data;
            }

            if ($response->status() === 404) {
                Log::warning('TabloApiService: Projekt nem található', ['project_id' => $projectId]);

                return null;
            }

            Log::error('TabloApiService: API hiba', [
                'project_id' => $projectId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('TabloApiService: Kapcsolódási hiba', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Nyers névsor szöveg feldolgozása AI-val.
     *
     * @param  string  $rawText  Nyers szöveges névsor (sorokra tördelve)
     * @param  string  $type  'students' vagy 'teachers'
     * @return array Strukturált névsor tömb
     */
    public function parseNameListWithAI(string $rawText, string $type, ClaudeService $claudeService): array
    {
        if (empty(trim($rawText))) {
            return [];
        }

        $prompt = $type === 'teachers'
            ? $this->getTeacherParsePrompt($rawText)
            : $this->getStudentParsePrompt($rawText);

        $systemPrompt = 'Magyar tabló névsor feldolgozó AI. A nevedből és pozíciókból strukturált JSON-t készítesz. Csak JSON-t válaszolj!';

        try {
            $result = $claudeService->chatJson($prompt, $systemPrompt, [
                'model' => 'claude-sonnet-4-5-20250929',
                'max_tokens' => 2048,
                'temperature' => 0.0,
            ]);

            return $result['names'] ?? $result;
        } catch (\Exception $e) {
            Log::error('TabloApiService: AI névsor feldolgozás hiba', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            // Fallback: egyszerű sorokra bontás
            return $this->fallbackParseNames($rawText, $type);
        }
    }

    /**
     * Diákok feldolgozási prompt.
     */
    protected function getStudentParsePrompt(string $rawText): string
    {
        return <<<PROMPT
Elemezd az alábbi diák névsort és készíts strukturált JSON-t.

NÉVSOR:
{$rawText}

Válaszolj CSAK JSON formátumban:
```json
{
  "names": [
    {"name": "Teljes név", "note": "megjegyzés ha van"}
  ]
}
```

SZABÁLYOK:
- Minden sor egy diák (soronként \r\n vagy \n elválasztva)
- Ha zárójelben vagy megjegyzés van (pl. "(nem jön)", "- külön kép", "hiányzik"), azt a note mezőbe tedd
- Ha nincs megjegyzés, a note legyen üres string ""
- Magyar nevek esetén TARTSD MEG az ékezeteket!
- Üres sorokat hagyd ki
- Ha egy név mellett több info is van (pl. "Kiss János - nem jön, saját kép"), mindent a note-ba tegyél
PROMPT;
    }

    /**
     * Tanárok feldolgozási prompt.
     */
    protected function getTeacherParsePrompt(string $rawText): string
    {
        return <<<PROMPT
Elemezd az alábbi tanár névsort és készíts strukturált JSON-t.

NÉVSOR:
{$rawText}

Válaszolj CSAK JSON formátumban (semmi más szöveg!):
{"names": [{"name": "Teljes név", "title": "tantárgy"}]}

FONTOS SZABÁLYOK:
1. Minden sor EGY tanár
2. A sor formátuma: "Vezetéknév Keresztnév, tantárgy1, tantárgy2" - VESSZŐ választja el a nevet a tantárgytól!
3. A NÉV = az ELSŐ vessző ELŐTTI rész
4. A TITLE = az ELSŐ vessző UTÁNI rész (összes tantárgy/beosztás egyben)

KONKRÉT PÉLDÁK a bemenetre:
- "Schramek Anikó, osztályfőnök, fizika" → {"name": "Schramek Anikó", "title": "osztályfőnök, fizika"}
- "Orosz Gyula, matematika" → {"name": "Orosz Gyula", "title": "matematika"}
- "Dr. Nagy Piroska Mária, fizika" → {"name": "Dr. Nagy Piroska Mária", "title": "fizika"}
- "Kiss János" → {"name": "Kiss János", "title": ""}

TILOS:
- NE tedd az egész sort a name mezőbe!
- NE hagyd ki a vesszőnél való szétválasztást!
PROMPT;
    }

    /**
     * Fallback egyszerű feldolgozás.
     */
    protected function fallbackParseNames(string $rawText, string $type): array
    {
        $lines = preg_split('/[\r\n]+/', $rawText);
        $names = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if ($type === 'teachers') {
                // Tanároknál vessző választja el a nevet a tantárgytól
                $parts = explode(',', $line, 2);
                $name = trim($parts[0]);
                $title = isset($parts[1]) ? trim($parts[1]) : '';
                $names[] = ['name' => $name, 'title' => $title];
            } else {
                $names[] = ['name' => $line, 'note' => ''];
            }
        }

        return $names;
    }

    /**
     * Ellenőrzi, hogy a projektnek van-e már strukturált névsora.
     */
    public function hasStructuredNames(array $projectData): bool
    {
        $hasStudents = !empty($projectData['students']) && is_array($projectData['students']);
        $hasTeachers = !empty($projectData['teachers']) && is_array($projectData['teachers']);

        return $hasStudents || $hasTeachers;
    }

    /**
     * Összefoglaló lekérése a projekt adataiból (design, kontakt, stb).
     */
    public function extractProjectSummary(array $projectData): array
    {
        return [
            'school_name' => $projectData['school']['name'] ?? null,
            'school_city' => $projectData['school']['city'] ?? null,
            'class_name' => $projectData['class_name'] ?? null,
            'class_year' => $projectData['class_year'] ?? null,

            // Kontakt
            'contact_name' => $projectData['contact']['name'] ?? null,
            'contact_email' => $projectData['contact']['email'] ?? null,
            'contact_phone' => $projectData['contact']['phone'] ?? null,

            // Design
            'color' => $projectData['color'] ?? null,
            'font_family' => $projectData['font_family'] ?? null,
            'description' => $projectData['description'] ?? null,
            'quote' => $projectData['quote'] ?? null,

            // Létszámok
            'student_count' => count($projectData['students'] ?? []),
            'teacher_count' => count($projectData['teachers'] ?? []),

            // Fájlok
            'has_order_form' => !empty($projectData['files']['order_form']),
            'has_background' => !empty($projectData['files']['background']),
            'order_form_url' => $projectData['files']['order_form'] ?? null,
        ];
    }
}
