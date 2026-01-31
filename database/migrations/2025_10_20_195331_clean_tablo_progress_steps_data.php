<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\TabloUserProgress;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean up nested structures in steps_data
        $progressRecords = TabloUserProgress::all();

        foreach ($progressRecords as $progress) {
            $stepsData = $progress->steps_data ?? [];
            $cleanData = [];

            // Extract data from nested structures
            foreach ($stepsData as $key => $value) {
                if ($key === 'retouch' && is_array($value)) {
                    // Extract from nested retouch object
                    if (isset($value['claimed_photo_ids']) && !isset($cleanData['claimed_photo_ids'])) {
                        $cleanData['claimed_photo_ids'] = $value['claimed_photo_ids'];
                    }
                    if (isset($value['claimed_count']) && !isset($cleanData['claimed_count'])) {
                        $cleanData['claimed_count'] = $value['claimed_count'];
                    }
                    if (isset($value['retouch_photo_ids'])) {
                        $cleanData['retouch_photo_ids'] = $value['retouch_photo_ids'];
                    }
                    if (isset($value['retouch_count'])) {
                        $cleanData['retouch_count'] = $value['retouch_count'];
                    }
                } elseif ($key === 'tablo' && is_array($value)) {
                    // Extract from nested tablo object
                    // The 'tablo' object incorrectly contains retouch data, we extract it properly
                    if (isset($value['retouch_photo_ids']) && !isset($cleanData['retouch_photo_ids'])) {
                        $cleanData['retouch_photo_ids'] = $value['retouch_photo_ids'];
                    }
                    if (isset($value['retouch_count']) && !isset($cleanData['retouch_count'])) {
                        $cleanData['retouch_count'] = $value['retouch_count'];
                    }
                    if (isset($value['tablo_photo_id'])) {
                        $cleanData['tablo_photo_id'] = $value['tablo_photo_id'];
                    }
                } elseif (!is_array($value)) {
                    // Keep flat structure data
                    $cleanData[$key] = $value;
                }
            }

            // Add any top-level data that wasn't nested
            foreach ($stepsData as $key => $value) {
                if (!in_array($key, ['retouch', 'tablo']) && !isset($cleanData[$key])) {
                    $cleanData[$key] = $value;
                }
            }

            // Update with clean data
            $progress->update(['steps_data' => $cleanData]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as it cleans up bad data
        // The original nested structure was incorrect
    }
};