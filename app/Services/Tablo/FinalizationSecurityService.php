<?php

namespace App\Services\Tablo;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Finalization Security Service
 *
 * Biztonsági validációk a megrendelés véglegesítéshez:
 * - Input sanitization (XSS védelem)
 * - Path traversal védelem
 * - IDOR védelem
 */
class FinalizationSecurityService
{

    /**
     * Sanitize string input - XSS védelem
     */
    public function sanitizeInput(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Strip HTML tags
        $value = strip_tags($value);

        // Remove potential JS event handlers
        $value = preg_replace('/on\w+\s*=/i', '', $value);

        // Normalize whitespace (de megtartja a sortöréseket)
        $value = preg_replace('/[^\S\r\n]+/', ' ', $value);

        return trim($value);
    }

    /**
     * Sanitize phone number - csak számok és formázó karakterek
     */
    public function sanitizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        return preg_replace('/[^\d\s\+\-\(\)]/', '', $phone);
    }

    /**
     * Sanitize color - csak valid hex szín
     */
    public function sanitizeColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        if (preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
            return $color;
        }

        return '#000000'; // Default fekete
    }

    /**
     * Sanitize minden form input
     */
    public function sanitizeFormData(array $data): array
    {
        return [
            'name' => $this->sanitizeInput($data['name'] ?? null),
            'contactEmail' => $this->sanitizeInput($data['contactEmail'] ?? null),
            'contactPhone' => $this->sanitizePhone($data['contactPhone'] ?? null),
            'schoolName' => $this->sanitizeInput($data['schoolName'] ?? null),
            'schoolCity' => $this->sanitizeInput($data['schoolCity'] ?? null),
            'className' => $this->sanitizeInput($data['className'] ?? null),
            'classYear' => $this->sanitizeInput($data['classYear'] ?? null),
            'quote' => $this->sanitizeInput($data['quote'] ?? null),
            'fontFamily' => $this->sanitizeInput($data['fontFamily'] ?? null),
            'color' => $this->sanitizeColor($data['color'] ?? null),
            'description' => $this->sanitizeInput($data['description'] ?? null),
            'sortType' => $data['sortType'] ?? 'abc',
            'studentDescription' => $this->sanitizeInput($data['studentDescription'] ?? null),
            'teacherDescription' => $this->sanitizeInput($data['teacherDescription'] ?? null),
            'acceptTerms' => $data['acceptTerms'] ?? false,
        ];
    }

    /**
     * Validate file ownership (IDOR védelem)
     */
    public function validateFileOwnership(string $fileId, int $projectId): bool
    {
        $expectedPrefix = "tablo-projects/{$projectId}/";

        if (! str_starts_with($fileId, $expectedPrefix)) {
            Log::warning('IDOR attempt detected in file operation', [
                'project_id' => $projectId,
                'attempted_file_id' => $fileId,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Validate path traversal (directory escape védelem)
     */
    public function validatePathTraversal(string $fileId, int $projectId): bool
    {
        $expectedPrefix = "tablo-projects/{$projectId}/";
        $basePath = Storage::disk('public')->path($expectedPrefix);
        $realPath = Storage::disk('public')->path($fileId);

        // Normalize paths
        $normalizedBase = realpath($basePath) ?: $basePath;
        $normalizedReal = realpath($realPath) ?: dirname($realPath);

        if (! str_starts_with($normalizedReal, $normalizedBase)) {
            Log::warning('Path traversal attempt detected', [
                'project_id' => $projectId,
                'attempted_file_id' => $fileId,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $action, int $projectId, array $context = []): void
    {
        Log::info('Finalization security event', array_merge([
            'action' => $action,
            'project_id' => $projectId,
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Get allowed image extensions
     */
    public function getAllowedImageExtensions(): array
    {
        return ['jpg', 'jpeg', 'bmp'];
    }

    /**
     * Get allowed archive extensions
     */
    public function getAllowedArchiveExtensions(): array
    {
        return ['zip', 'rar', '7z'];
    }
}
