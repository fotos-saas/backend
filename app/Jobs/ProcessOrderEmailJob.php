<?php

namespace App\Jobs;

use App\Helpers\QueryHelper;
use App\Models\ProjectEmail;
use App\Models\TabloContact;
use App\Models\TabloOrderAnalysis;
use App\Models\TabloProject;
use App\Services\ClaudeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;

class ProcessOrderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        protected int $projectEmailId
    ) {
        $this->onQueue('emails');
    }

    public function handle(ClaudeService $claudeService): void
    {
        $email = ProjectEmail::find($this->projectEmailId);

        if (!$email) {
            Log::warning('ProcessOrderEmailJob: Email not found', ['id' => $this->projectEmailId]);
            return;
        }

        // Már létezik KÉSZ elemzés ehhez az emailhez?
        $existingAnalysis = TabloOrderAnalysis::where('project_email_id', $email->id)->first();
        if ($existingAnalysis && $existingAnalysis->status === 'completed') {
            Log::info('ProcessOrderEmailJob: Analysis already completed', ['email_id' => $email->id]);
            return;
        }

        Log::info('ProcessOrderEmailJob: Starting', [
            'email_id' => $email->id,
            'subject' => $email->subject,
        ]);

        // Elemzés rekord létrehozása vagy meglévő pending/failed használata
        $analysis = $existingAnalysis ?? TabloOrderAnalysis::create([
            'project_email_id' => $email->id,
            'tablo_project_id' => $email->tablo_project_id,
            'status' => 'processing',
        ]);

        // Státusz frissítése processing-re
        $analysis->update(['status' => 'processing', 'error_message' => null]);

        try {
            // PDF csatolmány keresése és letöltése
            $pdfPath = $this->downloadPdfAttachment($email);

            if (!$pdfPath) {
                $analysis->update([
                    'status' => 'failed',
                    'error_message' => 'Nem található PDF csatolmány',
                ]);
                return;
            }

            $analysis->update([
                'pdf_path' => $pdfPath,
                'pdf_filename' => basename($pdfPath),
            ]);

            // AI elemzés
            $analysisData = $this->analyzeWithClaude($claudeService, Storage::disk('local')->path($pdfPath));

            // Adatok mentése
            $this->saveAnalysisData($analysis, $analysisData);

            // Projekt összekapcsolás (ha még nincs)
            if (!$analysis->tablo_project_id) {
                $this->linkToProject($analysis, $analysisData);
            }

            Log::info('ProcessOrderEmailJob: Completed', [
                'analysis_id' => $analysis->id,
                'project_id' => $analysis->tablo_project_id,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessOrderEmailJob: Error', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            $analysis->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * PDF csatolmány letöltése IMAP-ról.
     */
    protected function downloadPdfAttachment(ProjectEmail $email): ?string
    {
        if (empty($email->attachments)) {
            return null;
        }

        // Van PDF a csatolmányok között?
        $pdfAttachment = null;
        foreach ($email->attachments as $attachment) {
            if (str_contains(strtolower($attachment['mime_type'] ?? ''), 'pdf') ||
                str_ends_with(strtolower($attachment['name'] ?? ''), '.pdf')) {
                $pdfAttachment = $attachment;
                break;
            }
        }

        if (!$pdfAttachment) {
            return null;
        }

        try {
            $client = Client::account('default');
            $client->connect();

            // Mappa keresése - több variációt próbálunk (UTF-7 kódolás miatt)
            $folder = null;
            $folderVariations = [
                $email->imap_folder,
                '!!!Tabló megrendelés',
                'INBOX.!!!Tabló megrendelés',
                'INBOX/!!!Tabló megrendelés',
            ];

            foreach ($folderVariations as $folderName) {
                try {
                    $folder = $client->getFolder($folderName);
                    if ($folder) {
                        Log::info('ProcessOrderEmailJob: Folder found', ['folder' => $folderName]);
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$folder) {
                throw new \Exception("IMAP folder not found. Tried: " . implode(', ', $folderVariations));
            }

            $message = $folder->query()->getMessageByUid($email->imap_uid);

            if (!$message) {
                throw new \Exception("Message not found: UID {$email->imap_uid}");
            }

            $attachments = $message->getAttachments();

            foreach ($attachments as $attachment) {
                $name = $attachment->getName();
                if (str_ends_with(strtolower($name), '.pdf')) {
                    // PDF mentése
                    $filename = 'order-pdfs/' . date('Y/m/') . $email->id . '-' . $name;
                    Storage::disk('local')->put($filename, $attachment->getContent());

                    $client->disconnect();
                    return $filename;
                }
            }

            $client->disconnect();
            return null;

        } catch (\Exception $e) {
            Log::error('ProcessOrderEmailJob: PDF download error', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * PDF elemzése Claude AI-val.
     */
    protected function analyzeWithClaude(ClaudeService $claudeService, string $pdfPath): array
    {
        $prompt = <<<'PROMPT'
Elemezd ezt a tabló megrendelőlapot és nyerd ki az összes információt JSON formátumban.

A válaszod CSAK egy JSON objektum legyen, a következő struktúrával:

```json
{
  "contact": {
    "name": "Kapcsolattartó teljes neve",
    "phone": "Telefonszám",
    "email": "Email cím"
  },
  "school": {
    "name": "Iskola neve",
    "city": "Város",
    "address": "Cím (ha van)"
  },
  "class": {
    "name": "Osztály (pl. 12.B)",
    "year": "Végzés éve"
  },
  "design": {
    "size": "Tabló méret (pl. 50x70)",
    "font": "Betűtípus preferencia",
    "color": "Szín preferencia",
    "background": "Háttér stílus",
    "notes": "Egyéb megjegyzések a dizájnhoz"
  },
  "students": [
    {"name": "Diák neve", "note": "megjegyzés ha van"}
  ],
  "teachers": [
    {"name": "Tanár neve", "role": "Beosztás (pl. osztályfőnök, igazgató)"}
  ],
  "tags": ["címke1", "címke2"],
  "warnings": ["figyelmeztetés ha valami hiányzik vagy nem egyértelmű"],
  "ai_summary": "Rövid, 1-2 mondatos összefoglaló a megrendelésről magyarul"
}
```

A "tags" mezőbe 2-3 stílus címkét adj, ami jellemzi a tablót. Példák:
- "klasszikus" - hagyományos, elegáns
- "modern" - letisztult, minimalista
- "mesés" - mesevilág, fantasy elemek
- "karakteres" - egyedi karakterekkel
- "spotify" - Spotify stílusú
- "retro" - vintage, régi stílus
- "színes" - élénk színek
- "pasztell" - lágy, pasztell színek

A "warnings" mezőbe CSAK VALÓDI problémákat írj:
- Hiányzik KÖTELEZŐ adat (pl. nincs telefonszám VAGY email - legalább egy kell)
- Olvashatatlan vagy elmosódott szöveg
- Ellentmondás az adatokban (pl. létszám nem egyezik a névsorral)
- Diák/tanár nevek hiányoznak de meg vannak jelölve

NE írj warningot ha:
- Nincs idézet mező (ez opcionális)
- Nincs csatolmány leírás (ez opcionális)
- Nincs háttérkép megadva (ez opcionális)
- Üres opcionális mezők (design preferenciák, megjegyzések, betűtípus, színek)

Ha minden rendben van, a warnings tömb legyen ÜRES: []

Az "ai_summary" mezőbe írj egy rövid (1-2 mondatos) összefoglalót a megrendelésről, ami gyorsan áttekinthető.
Példa: "Újpesti Bródy Imre Gimnázium 12.DN osztálya, 19 diák és 24 tanár. Modern, letisztult stílust szeretnének."

FONTOS: Csak a JSON-t add vissza, semmi mást!
PROMPT;

        $systemPrompt = 'Te egy magyar tabló megrendelőlap elemző AI vagy. A feladatod a megrendelőlapok feldolgozása és az adatok strukturált kinyerése. Válaszolj CSAK JSON formátumban, magyarul.';

        return $claudeService->analyzePdf($pdfPath, $prompt, $systemPrompt, [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 8192,
        ]);
    }

    /**
     * Elemzési adatok mentése.
     */
    protected function saveAnalysisData(TabloOrderAnalysis $analysis, array $data): void
    {
        $analysis->update([
            'status' => 'completed',
            'analysis_data' => $data,
            'analyzed_at' => now(),

            // Kinyert adatok
            'contact_name' => $data['contact']['name'] ?? null,
            'contact_phone' => $data['contact']['phone'] ?? null,
            'contact_email' => $data['contact']['email'] ?? null,

            'school_name' => $data['school']['name'] ?? null,
            'class_name' => $data['class']['name'] ?? null,

            'student_count' => count($data['students'] ?? []),
            'teacher_count' => count($data['teachers'] ?? []),

            // Design
            'tablo_size' => $data['design']['size'] ?? null,
            'font_style' => $data['design']['font'] ?? null,
            'color_scheme' => $data['design']['color'] ?? null,
            'background_style' => $data['design']['background'] ?? null,
            'special_notes' => $data['design']['notes'] ?? null,

            // AI összefoglaló
            'ai_summary' => $data['ai_summary'] ?? null,

            // Címkék és figyelmeztetések
            'tags' => $data['tags'] ?? [],
            'warnings' => $data['warnings'] ?? [],
        ]);
    }

    /**
     * Projekt összekapcsolás az elemzés alapján.
     */
    protected function linkToProject(TabloOrderAnalysis $analysis, array $data): void
    {
        $projectId = null;

        // Keresés email alapján
        if (!empty($data['contact']['email'])) {
            $contact = TabloContact::where('email', $data['contact']['email'])->first();
            if ($contact) {
                $projectId = $contact->tablo_project_id;
            }
        }

        // Keresés telefon alapján
        if (!$projectId && !empty($data['contact']['phone'])) {
            $phone = preg_replace('/[^0-9+]/', '', $data['contact']['phone']);
            $contact = TabloContact::where('phone', 'LIKE', "%{$phone}%")->first();
            if ($contact) {
                $projectId = $contact->tablo_project_id;
            }
        }

        // Keresés iskola + osztály alapján
        if (!$projectId && !empty($data['school']['name']) && !empty($data['class']['name'])) {
            $project = TabloProject::whereHas('school', function ($q) use ($data) {
                $q->where('name', 'ILIKE', QueryHelper::safeLikePattern($data['school']['name']));
            })
                ->where('class_name', 'ILIKE', QueryHelper::safeLikePattern($data['class']['name']))
                ->first();

            if ($project) {
                $projectId = $project->id;
            }
        }

        if ($projectId) {
            $analysis->update(['tablo_project_id' => $projectId]);

            // Email is linkeljük a projekthez
            if ($analysis->projectEmail && !$analysis->projectEmail->tablo_project_id) {
                $analysis->projectEmail->update(['tablo_project_id' => $projectId]);
            }

            Log::info('ProcessOrderEmailJob: Linked to project', [
                'analysis_id' => $analysis->id,
                'project_id' => $projectId,
            ]);
        }
    }
}
