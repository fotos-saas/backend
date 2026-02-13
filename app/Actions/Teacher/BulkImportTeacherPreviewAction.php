<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Actions\Concerns\ParsesImportFile;
use App\Services\Teacher\TeacherMatchingService;
use Illuminate\Http\UploadedFile;

class BulkImportTeacherPreviewAction
{
    use ParsesImportFile;

    public function __construct(
        private TeacherMatchingService $matchingService,
    ) {}

    public function execute(int $partnerId, int $schoolId, ?array $names, ?UploadedFile $file): array
    {
        $parsedNames = $names
            ? $this->parseTextNames($names)
            : $this->parseFileNames($file);

        if (empty($parsedNames)) {
            return [];
        }

        return $this->matchingService->matchNames($parsedNames, $partnerId, $schoolId);
    }
}
