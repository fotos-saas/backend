<?php

namespace App\Services\Contracts;

use App\Models\Album;
use App\Models\Photo;
use Illuminate\Support\Collection;

interface FaceRecognitionServiceInterface
{
    /**
     * Detect and group faces in photos
     */
    public function detectAndGroupFaces(Album $album, Collection $photos): void;

    /**
     * Detect face in a single photo
     */
    public function detectFace(Photo $photo): ?array;

    /**
     * Calculate similarity between two face embeddings
     */
    public function calculateSimilarity(array $embedding1, array $embedding2): float;
}
