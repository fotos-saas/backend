<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabloOrderAnalysis extends Model
{
    protected $fillable = [
        'tablo_project_id',
        'project_email_id',
        'pdf_path',
        'pdf_filename',
        'status',
        'error_message',
        'analysis_data',
        'contact_name',
        'contact_phone',
        'contact_email',
        'school_name',
        'class_name',
        'student_count',
        'teacher_count',
        'tablo_size',
        'font_style',
        'color_scheme',
        'background_style',
        'special_notes',
        'ai_summary',
        'tags',
        'warnings',
        'analyzed_at',
    ];

    protected $casts = [
        'analysis_data' => 'array',
        'tags' => 'array',
        'warnings' => 'array',
        'analyzed_at' => 'datetime',
        'student_count' => 'integer',
        'teacher_count' => 'integer',
    ];

    /**
     * Kapcsolódó tabló projekt.
     */
    public function tabloProject(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Kapcsolódó email.
     */
    public function projectEmail(): BelongsTo
    {
        return $this->belongsTo(ProjectEmail::class, 'project_email_id');
    }

    /**
     * Státusz ellenőrzések.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Címkék szöveges formában.
     */
    public function getTagsStringAttribute(): string
    {
        return $this->tags ? implode(', ', $this->tags) : '';
    }

    /**
     * Van-e figyelmeztetés?
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Diák lista az elemzésből.
     */
    public function getStudentListAttribute(): array
    {
        return $this->analysis_data['students'] ?? [];
    }

    /**
     * Tanár lista az elemzésből.
     */
    public function getTeacherListAttribute(): array
    {
        return $this->analysis_data['teachers'] ?? [];
    }

    /**
     * Automatikusan megkeresi és összekapcsolja a megfelelő TabloProject-tel.
     * Keresési sorrend: 1) Kapcsolattartó email, 2) Iskola + osztály
     */
    public function autoLinkToProject(): ?TabloProject
    {
        if ($this->tablo_project_id) {
            return null;
        }

        // 1. Keresés kapcsolattartó email alapján (legmegbízhatóbb)
        if ($this->contact_email) {
            $project = $this->findProjectByContactEmail();
            if ($project) {
                $this->update(['tablo_project_id' => $project->id]);
                return $project;
            }
        }

        // 2. Keresés kapcsolattartó telefon alapján
        if ($this->contact_phone) {
            $project = $this->findProjectByContactPhone();
            if ($project) {
                $this->update(['tablo_project_id' => $project->id]);
                return $project;
            }
        }

        // 3. Keresés iskola + osztály alapján (fuzzy matching)
        if ($this->school_name) {
            $project = $this->findProjectBySchoolAndClass();
            if ($project) {
                $this->update(['tablo_project_id' => $project->id]);
                return $project;
            }
        }

        return null;
    }

    /**
     * Projekt keresése kapcsolattartó email alapján.
     */
    protected function findProjectByContactEmail(): ?TabloProject
    {
        $email = strtolower(trim($this->contact_email));

        // Keresés a ProjectEmail táblában (from_email)
        $projectEmail = ProjectEmail::whereRaw('LOWER(from_email) = ?', [$email])
            ->whereNotNull('tablo_project_id')
            ->first();

        if ($projectEmail) {
            return TabloProject::find($projectEmail->tablo_project_id);
        }

        return null;
    }

    /**
     * Projekt keresése kapcsolattartó telefon alapján.
     */
    protected function findProjectByContactPhone(): ?TabloProject
    {
        $phone = self::normalizePhone($this->contact_phone);
        if (strlen($phone) < 9) {
            return null;
        }

        // Keresés a ProjectEmail táblában - body_text-ben telefonszám
        $projectEmail = ProjectEmail::whereNotNull('tablo_project_id')
            ->where(function ($query) use ($phone) {
                $query->whereRaw("REPLACE(REPLACE(REPLACE(body_text, ' ', ''), '-', ''), '/', '') LIKE ?", ['%' . $phone . '%']);
            })
            ->first();

        if ($projectEmail) {
            return TabloProject::find($projectEmail->tablo_project_id);
        }

        return null;
    }

    /**
     * Projekt keresése iskola és osztály alapján.
     */
    protected function findProjectBySchoolAndClass(): ?TabloProject
    {
        $normalizedSchool = self::normalizeString($this->school_name);
        $normalizedClass = self::normalizeClassName($this->class_name);

        return TabloProject::with('school')
            ->whereHas('school', function ($query) use ($normalizedSchool) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($normalizedSchool) . '%']);
            })
            ->get()
            ->first(function ($project) use ($normalizedSchool, $normalizedClass) {
                $projectSchool = self::normalizeString($project->school->name ?? '');
                $projectClass = self::normalizeClassName($project->class_name);

                similar_text($normalizedSchool, $projectSchool, $schoolPercent);
                $classMatch = $normalizedClass === $projectClass;

                return $schoolPercent >= 70 && $classMatch;
            });
    }

    /**
     * Telefonszám normalizálása (csak számjegyek).
     */
    public static function normalizePhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Normalizálja a stringet összehasonlításhoz.
     */
    public static function normalizeString(string $str): string
    {
        $str = mb_strtolower($str);
        $str = preg_replace('/[^\p{L}\p{N}\s]/u', '', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    /**
     * Normalizálja az osztály nevet (pl. "12. A" -> "12a", "12.a" -> "12a").
     */
    public static function normalizeClassName(?string $className): string
    {
        if (!$className) {
            return '';
        }
        $className = mb_strtolower($className);
        $className = preg_replace('/[^a-z0-9]/u', '', $className);
        return $className;
    }

    /**
     * Összes nem kapcsolt elemzés automatikus összekapcsolása.
     */
    public static function autoLinkAllUnlinked(): array
    {
        $results = ['linked' => 0, 'failed' => 0, 'by_email' => 0, 'by_phone' => 0, 'by_school' => 0];

        // Keresünk minden nem kapcsolt elemzést aminek van contact_email, contact_phone vagy school_name
        $analyses = self::whereNull('tablo_project_id')
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNotNull('contact_email')
                    ->orWhereNotNull('contact_phone')
                    ->orWhereNotNull('school_name');
            })
            ->get();

        foreach ($analyses as $analysis) {
            $hadEmail = $analysis->contact_email;
            $hadPhone = $analysis->contact_phone;

            $project = $analysis->autoLinkToProject();

            if ($project) {
                $results['linked']++;
                // Meghatározzuk melyik módszerrel sikerült
                if ($hadEmail && $analysis->findProjectByContactEmail()?->id === $project->id) {
                    $results['by_email']++;
                } elseif ($hadPhone && $analysis->findProjectByContactPhone()?->id === $project->id) {
                    $results['by_phone']++;
                } else {
                    $results['by_school']++;
                }
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
