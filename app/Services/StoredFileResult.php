<?php

declare(strict_types=1);

namespace App\Services;

readonly class StoredFileResult
{
    public bool $isImage;

    public function __construct(
        public string $path,
        public string $filename,
        public string $originalName,
        public string $mimeType,
        public int $size,
        public string $disk = 'public',
    ) {
        $this->isImage = str_starts_with($this->mimeType, 'image/');
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'filename' => $this->filename,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'is_image' => $this->isImage,
        ];
    }
}
