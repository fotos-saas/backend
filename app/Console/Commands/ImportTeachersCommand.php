<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TeacherArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportTeachersCommand extends Command
{
    protected $signature = 'teachers:import
        {source : JSON fájl elérési út vagy API URL}
        {--partner= : Partner ID (kötelező)}';

    protected $description = 'Tanárok importálása JSON fájlból vagy API URL-ről a teacher_archive táblába';

    private int $created = 0;

    private int $skipped = 0;

    private int $merged = 0;

    public function handle(): int
    {
        $partnerId = (int) $this->option('partner');
        if ($partnerId <= 0) {
            $this->error('A --partner opció kötelező és pozitív egésznek kell lennie.');

            return self::FAILURE;
        }

        $source = $this->argument('source');
        $teachers = $this->loadTeachers($source);
        if ($teachers === null) {
            return self::FAILURE;
        }

        $this->info("Betöltve: {$this->formatNumber(count($teachers))} tanár rekord");

        $grouped = $this->groupByNameAndSchool($teachers);
        $this->info("Egyedi (név, iskola) párok: {$this->formatNumber(count($grouped))}");

        $existingExternalIds = TeacherArchive::forPartner($partnerId)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->flip()
            ->toArray();

        $bar = $this->output->createProgressBar(count($grouped));
        $bar->start();

        foreach ($grouped as $key => $group) {
            $this->processGroup($group, $partnerId, $existingExternalIds);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $schoolCount = collect($grouped)->map(fn ($g) => $g[0]['tablo_school_id'])->unique()->count();

        $this->info('--- Eredmény ---');
        $this->info("Létrehozva: {$this->formatNumber($this->created)}");
        $this->info("Kihagyva (már létezik): {$this->formatNumber($this->skipped)}");
        $this->info("Összevont external_id-k: {$this->formatNumber($this->merged)}");
        $this->info("Iskolák: {$this->formatNumber($schoolCount)}");

        return self::SUCCESS;
    }

    private function loadTeachers(string $source): ?array
    {
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            $this->info("Letöltés: {$source}");
            try {
                $response = Http::timeout(30)->get($source);
                $data = $response->json();
            } catch (\Exception $e) {
                $this->error("API hiba: {$e->getMessage()}");

                return null;
            }
        } else {
            if (! file_exists($source)) {
                $this->error("Fájl nem található: {$source}");

                return null;
            }
            $this->info("Fájl beolvasás: {$source}");
            $json = file_get_contents($source);
            $data = json_decode($json, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Érvénytelen JSON: '.json_last_error_msg());

            return null;
        }

        // A JSON struktúra: { "success": true, "teachers": [...] }
        if (isset($data['teachers']) && is_array($data['teachers'])) {
            return $data['teachers'];
        }

        // Vagy egyszerű tömb
        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        $this->error('Ismeretlen JSON struktúra. Várt: {"teachers": [...]} vagy [...]');

        return null;
    }

    /**
     * Csoportosítás (name.trim(), school_id) páronként.
     * Egy csoporton belül több external_id lehet (ugyanaz a tanár több projektből).
     *
     * @return array<string, array>
     */
    private function groupByNameAndSchool(array $teachers): array
    {
        $grouped = [];
        foreach ($teachers as $teacher) {
            $name = trim($teacher['name'] ?? '');
            $schoolId = $teacher['tablo_school_id'] ?? $teacher['school_id'] ?? null;

            if ($name === '' || $schoolId === null) {
                continue;
            }

            $key = mb_strtolower($name).'|'.$schoolId;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $teacher;
        }

        return $grouped;
    }

    private function processGroup(array $group, int $partnerId, array &$existingExternalIds): void
    {
        $primaryRecord = $group[0];
        $primaryExternalId = (string) $primaryRecord['id'];

        // Ha az elsődleges external_id már létezik → skip
        if (isset($existingExternalIds[$primaryExternalId])) {
            $this->skipped++;

            return;
        }

        // További external_id-k (ugyanaz a tanár más projektekből)
        $additionalExternalIds = [];
        for ($i = 1; $i < count($group); $i++) {
            $extId = (string) $group[$i]['id'];
            if (! isset($existingExternalIds[$extId])) {
                $additionalExternalIds[] = $extId;
            }
        }

        // Titulus kiszedése a névből
        $name = trim($primaryRecord['name'] ?? '');
        $titlePrefix = null;
        if (preg_match('/^(Dr\.?|PhD\.?|Prof\.?)\s+/i', $name, $matches)) {
            $titlePrefix = rtrim($matches[1], '.');
            $name = trim(substr($name, strlen($matches[0])));
        }

        // Position: az első nem-üres position-t használjuk
        $position = null;
        foreach ($group as $record) {
            $pos = trim($record['position'] ?? '');
            if ($pos !== '') {
                $position = $pos;
                break;
            }
        }

        $schoolId = $primaryRecord['tablo_school_id'] ?? $primaryRecord['school_id'];

        // Metadata: további external_id-k és image URL-ek
        $metadata = null;
        if (! empty($additionalExternalIds)) {
            $this->merged += count($additionalExternalIds);
            $metadata = ['additional_external_ids' => $additionalExternalIds];
        }

        // Image URL-ek összegyűjtése
        $imageUrls = [];
        foreach ($group as $record) {
            if (! empty($record['selected_image_url'])) {
                $imageUrls[] = $record['selected_image_url'];
            }
        }
        if (! empty($imageUrls)) {
            $metadata = $metadata ?? [];
            $metadata['image_urls'] = array_values(array_unique($imageUrls));
        }

        TeacherArchive::create([
            'partner_id' => $partnerId,
            'school_id' => $schoolId,
            'canonical_name' => $name,
            'title_prefix' => $titlePrefix,
            'position' => $position,
            'external_id' => $primaryExternalId,
            'notes' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $existingExternalIds[$primaryExternalId] = true;
        $this->created++;
    }

    private function formatNumber(int $n): string
    {
        return number_format($n, 0, ',', ' ');
    }
}
