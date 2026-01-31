<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\FaceGroup;
use App\Models\Photo;
use App\Services\CompreFaceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command to group photos by detected faces.
 * Creates FaceGroup records by analyzing face similarity.
 */
class GroupPhotosByFaces extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'photos:group-by-faces
                            {album? : Album ID to process. If not provided, processes all albums}
                            {--recreate : Delete existing groups and recreate them}';

    /**
     * The console command description.
     */
    protected $description = 'Group photos by detected faces within albums';

    /**
     * Execute the console command.
     */
    public function handle(CompreFaceService $faceService): int
    {
        $this->info('Starting face grouping...');

        // Get albums to process
        $albumId = $this->argument('album');
        $albums = $albumId
            ? Album::where('id', $albumId)->get()
            : Album::all();

        if ($albums->isEmpty()) {
            $this->warn('No albums found to process.');

            return Command::SUCCESS;
        }

        $this->info("Processing {$albums->count()} album(s)");

        foreach ($albums as $album) {
            $this->processAlbum($album, $faceService);
        }

        $this->newLine();
        $this->info('✅ Face grouping completed!');

        return Command::SUCCESS;
    }

    /**
     * Process face grouping for a single album.
     */
    protected function processAlbum(Album $album, CompreFaceService $faceService): void
    {
        $this->line("Processing Album #{$album->id}: {$album->name}");

        // Get photos with detected faces
        $photos = $album->photos()
            ->where('face_detected', true)
            ->get();

        if ($photos->isEmpty()) {
            $this->warn('  No photos with detected faces found.');

            return;
        }

        $this->info("  Found {$photos->count()} photos with faces");

        // Delete existing groups if recreate flag is set
        if ($this->option('recreate')) {
            $deletedCount = $album->faceGroups()->count();
            $album->faceGroups()->delete();
            $this->comment("  Deleted {$deletedCount} existing groups");
        }

        try {
            // Group photos by analyzing face similarities
            $groups = $this->groupPhotosByFaceSimilarity($photos, $album);

            if ($groups->isEmpty()) {
                $this->warn('  No face groups could be created.');

                return;
            }

            // Create FaceGroup records
            $groupNumber = 1;
            foreach ($groups as $groupPhotos) {
                // Pick representative photo (first one or with highest confidence)
                $representative = $groupPhotos->first();

                $faceGroup = FaceGroup::create([
                    'album_id' => $album->id,
                    'name' => config('face-recognition.grouping.auto_name_prefix', 'Csoport').' '.$groupNumber,
                    'representative_photo_id' => $representative->id,
                ]);

                // Attach photos to the group
                foreach ($groupPhotos as $photo) {
                    DB::table('face_group_photo')->insert([
                        'face_group_id' => $faceGroup->id,
                        'photo_id' => $photo->id,
                        'confidence' => 0.85, // Default confidence
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->comment("  ✓ Group {$groupNumber}: {$groupPhotos->count()} photos");
                $groupNumber++;
            }

            // Update album progress
            $album->update([
                'face_processed_photos' => $photos->count(),
            ]);

            $this->info("  ✅ Created {$groups->count()} face group(s)");
        } catch (\Exception $e) {
            Log::error('Face grouping failed for album', [
                'album_id' => $album->id,
                'error' => $e->getMessage(),
            ]);

            $this->error("  ❌ Error: {$e->getMessage()}");
        }
    }

    /**
     * Group photos by face_subject from CompreFace Recognition Service.
     *
     * @param  \Illuminate\Support\Collection<Photo>  $photos
     * @return \Illuminate\Support\Collection
     */
    protected function groupPhotosByFaceSimilarity($photos, Album $album)
    {
        // Group photos by face_subject (from CompreFace Recognition API)
        $groupedBySubject = $photos
            ->whereNotNull('face_subject')
            ->groupBy('face_subject');

        $groups = collect();

        // Create a group for each unique subject
        foreach ($groupedBySubject as $subject => $subjectPhotos) {
            if ($subjectPhotos->count() > 0) {
                $groups->push($subjectPhotos);
                $this->line("    Subject '{$subject}': {$subjectPhotos->count()} photo(s)");
            }
        }

        // Handle photos without face_subject (fallback to gender/age grouping)
        $photosWithoutSubject = $photos->whereNull('face_subject');

        if ($photosWithoutSubject->isNotEmpty()) {
            $this->comment("    {$photosWithoutSubject->count()} photo(s) without face_subject (using fallback grouping)");

            // Fallback: Group by gender and age
            $byGender = $photosWithoutSubject->groupBy('gender');

            foreach ($byGender as $gender => $genderPhotos) {
                if ($gender === 'unknown' || ! $gender) {
                    // Unknown gender - each photo is its own group
                    foreach ($genderPhotos as $photo) {
                        $groups->push(collect([$photo]));
                    }

                    continue;
                }

                // Further group by age ranges (within same gender)
                $byAge = $genderPhotos->groupBy(function ($photo) {
                    if (! $photo->age) {
                        return 'unknown';
                    }

                    // Group by 10-year ranges
                    return floor($photo->age / 10) * 10;
                });

                foreach ($byAge as $ageRange => $agePhotos) {
                    $groups->push($agePhotos);
                }
            }
        }

        return $groups->filter(fn ($group) => $group->isNotEmpty());
    }
}
