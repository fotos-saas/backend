<?php

namespace App\Jobs;

use App\Models\Album;
use App\Services\PhotoUploadService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

class ProcessZipUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $albumId,
        public string $sourcePath,
        public ?int $assignedUserId = null,
        public string $sourceType = 'local'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PhotoUploadService $uploadService): void
    {
        $album = Album::findOrFail($this->albumId);

        try {
            // Update status to processing
            $album->update(['zip_processing_status' => 'processing']);

            // Determine ZIP file path
            if ($this->sourceType === 'google_drive') {
                $zipPath = $this->downloadFromGoogleDrive($this->sourcePath);
            } else {
                $zipPath = Storage::path($this->sourcePath);
            }

            // Extract ZIP to temporary directory
            $tempDir = storage_path('app/temp-zip-'.uniqid());
            File::ensureDirectoryExists($tempDir);

            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \Exception('Failed to open ZIP file');
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Find all image files recursively
            $imageFiles = $this->findImageFiles($tempDir);

            // Update total images count
            $album->update([
                'zip_total_images' => count($imageFiles),
                'zip_processed_images' => 0,
            ]);

            // Process each image
            foreach ($imageFiles as $index => $imagePath) {
                try {
                    $originalFilename = basename($imagePath);

                    $uploadService->uploadPhoto(
                        file: $imagePath,
                        albumId: $this->albumId,
                        assignedUserId: $this->assignedUserId,
                        originalFilename: $originalFilename
                    );

                    // Update progress
                    $album->increment('zip_processed_images');
                } catch (\Exception $e) {
                    Log::error('Failed to upload image from ZIP', [
                        'album_id' => $this->albumId,
                        'image' => $imagePath,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with next image
                }
            }

            // Cleanup
            File::deleteDirectory($tempDir);
            if ($this->sourceType === 'local') {
                Storage::delete($this->sourcePath);
            } elseif ($this->sourceType === 'google_drive') {
                File::delete($zipPath);
            }

            // Mark as completed
            $album->update(['zip_processing_status' => 'completed']);

            // Dispatch watermarking job (runs synchronously due to queue worker issues)
            ApplyWatermarkToAlbumPhotos::dispatchSync($this->albumId);

            // Send notification
            Notification::make()
                ->title('ZIP feldolgozás befejezve')
                ->body("Sikeresen feldolgozva {$album->zip_processed_images} kép az albumból: {$album->title}")
                ->success()
                ->sendToDatabase(\App\Models\User::where('role', 'admin')->get());
        } catch (Throwable $e) {
            Log::error('ZIP processing failed', [
                'album_id' => $this->albumId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $album->update(['zip_processing_status' => 'failed']);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $album = Album::find($this->albumId);
        if ($album) {
            $album->update(['zip_processing_status' => 'failed']);

            Notification::make()
                ->title('ZIP feldolgozás sikertelen')
                ->body("Hiba történt a ZIP feldolgozása során: {$exception->getMessage()}")
                ->danger()
                ->sendToDatabase(\App\Models\User::where('role', 'admin')->get());
        }
    }

    /**
     * Download file from Google Drive
     */
    protected function downloadFromGoogleDrive(string $url): string
    {
        // Extract file ID from Google Drive URL
        $fileId = $this->extractGoogleDriveFileId($url);

        if (! $fileId) {
            throw new \Exception('Invalid Google Drive URL');
        }

        // Google Drive direct download URL
        $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";

        $tempZipPath = storage_path('app/temp-download-'.uniqid().'.zip');

        // Download file
        $response = Http::timeout(600)->get($downloadUrl);

        if (! $response->successful()) {
            throw new \Exception('Failed to download file from Google Drive');
        }

        File::put($tempZipPath, $response->body());

        return $tempZipPath;
    }

    /**
     * Extract Google Drive file ID from URL
     */
    protected function extractGoogleDriveFileId(string $url): ?string
    {
        // Handle various Google Drive URL formats
        $patterns = [
            '/\/file\/d\/([a-zA-Z0-9_-]+)/',
            '/id=([a-zA-Z0-9_-]+)/',
            '/\/open\?id=([a-zA-Z0-9_-]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Find all image files in directory recursively
     */
    protected function findImageFiles(string $directory): array
    {
        $imageFiles = [];
        $allowedExtensions = ['jpg', 'jpeg', 'png'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filename = $file->getFilename();
                $extension = strtolower($file->getExtension());

                // Skip hidden files (starting with .)
                // Skip macOS resource fork files (starting with ._ or __MACOSX)
                if (str_starts_with($filename, '.') ||
                    str_starts_with($filename, '__MACOSX') ||
                    str_contains($file->getPath(), '__MACOSX')) {
                    continue;
                }

                if (in_array($extension, $allowedExtensions)) {
                    $imageFiles[] = $file->getRealPath();
                }
            }
        }

        return $imageFiles;
    }
}
