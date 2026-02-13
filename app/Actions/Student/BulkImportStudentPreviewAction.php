<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Concerns\ParsesImportFile;
use App\Models\StudentArchive;
use Illuminate\Http\UploadedFile;

class BulkImportStudentPreviewAction
{
    use ParsesImportFile;

    public function execute(int $partnerId, int $schoolId, ?array $names, ?UploadedFile $file): array
    {
        $parsedNames = $names
            ? $this->parseTextNames($names)
            : $this->parseFileNames($file);

        if (empty($parsedNames)) {
            return [];
        }

        return $this->matchNames($parsedNames, $partnerId, $schoolId);
    }

    private function matchNames(array $names, int $partnerId, int $schoolId): array
    {
        $students = StudentArchive::forPartner($partnerId)
            ->forSchool($schoolId)
            ->active()
            ->with('aliases')
            ->get();

        $results = [];

        foreach ($names as $inputName) {
            $inputName = trim($inputName);
            if ($inputName === '') continue;

            $normalized = mb_strtolower(trim($inputName));

            // Exact match: canonical_name vagy alias
            $exactMatch = null;
            foreach ($students as $student) {
                if (mb_strtolower($student->canonical_name) === $normalized) {
                    $exactMatch = $student;
                    break;
                }
                foreach ($student->aliases as $alias) {
                    if (mb_strtolower($alias->alias_name) === $normalized) {
                        $exactMatch = $student;
                        break 2;
                    }
                }
            }

            if ($exactMatch) {
                $results[] = [
                    'inputName' => $inputName,
                    'matchType' => 'exact',
                    'studentId' => $exactMatch->id,
                    'studentName' => $exactMatch->canonical_name,
                    'className' => $exactMatch->class_name,
                    'photoUrl' => $exactMatch->photo_thumb_url,
                ];
            } else {
                $results[] = [
                    'inputName' => $inputName,
                    'matchType' => 'no_match',
                    'studentId' => null,
                    'studentName' => null,
                    'className' => null,
                    'photoUrl' => null,
                ];
            }
        }

        return $results;
    }
}
