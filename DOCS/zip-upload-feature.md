# ZIP Upload Feature for Albums

## Overview

This feature allows administrators to upload ZIP files containing multiple photos to albums. The ZIP is processed in the background using Laravel's queue system, supporting both local file uploads and Google Drive shared links.

## Features

- Upload ZIP files directly or via Google Drive link
- Automatic background processing via queue
- Recursive extraction of images from all subdirectories
- Real-time progress tracking in album list
- Support for JPG, JPEG, PNG formats
- Automatic photo conversions (thumb, preview, watermarked)
- Optional user assignment for all photos in ZIP

## Database Changes

### New Fields in `albums` Table

- `zip_processing_status` (enum): 'none', 'pending', 'processing', 'completed', 'failed'
- `zip_total_images` (integer, nullable): Total number of images found in ZIP
- `zip_processed_images` (integer, nullable): Number of images processed so far

## How to Use

### 1. Via Local File Upload

1. Navigate to an Album's edit page
2. Go to the "Photos" tab
3. Click "ZIP Feltöltése" button
4. Select "Fájl feltöltése" option
5. Upload your ZIP file (max 500MB)
6. Optionally assign all photos to a user
7. Click submit

### 2. Via Google Drive Link

1. Share your ZIP file on Google Drive with "Anyone with the link" permissions
2. Copy the share link
3. Navigate to an Album's edit page
4. Go to the "Photos" tab
5. Click "ZIP Feltöltése" button
6. Select "Google Drive link" option
7. Paste the Google Drive URL
8. Optionally assign all photos to a user
9. Click submit

### 3. Monitor Progress

- Go to the Albums list page
- The "ZIP Feldolgozás" column shows:
  - **Várakozás**: ZIP is queued for processing
  - **Folyamatban (X/Y)**: Currently processing X out of Y photos
  - **Kész**: Processing completed successfully
  - **Hiba**: An error occurred during processing

## Technical Details

### Job: ProcessZipUpload

**Location:** `app/Jobs/ProcessZipUpload.php`

**Process:**
1. Downloads ZIP (from storage or Google Drive)
2. Extracts ZIP to temporary directory
3. Recursively finds all image files (jpg, jpeg, png)
4. Updates `zip_total_images` count
5. Processes each image using `PhotoUploadService`
6. Updates progress after each photo
7. Cleans up temporary files
8. Marks album as 'completed' or 'failed'
9. Sends admin notification

**Timeout:** 1 hour (3600 seconds)
**Tries:** 1 attempt
**Queue:** database

### Watermarking (Event-driven)

**Location:** `app/Listeners/ApplyWatermarkToPreview.php`

Watermarks are applied automatically via event listener instead of in the upload service:

1. Media Library completes preview conversion (sync or queued)
2. Fires `Spatie\MediaLibrary\MediaCollections\Events\ConversionHasBeenCompleted` event
3. `ApplyWatermarkToPreview` listener catches the event
4. Checks if conversion name is 'preview'
5. Checks global watermark settings (`watermark_enabled`, `watermark_text`)
6. Applies watermark using `WatermarkService::addCircularWatermark()`

**Benefits:**
- Works for both queued and sync conversions (solves ZIP upload issue!)
- Single source of truth - no code duplication
- Automatic for ZIP uploads, normal uploads, and version restores
- Event-driven - clean separation of concerns
- Non-blocking - doesn't delay upload response

### Google Drive Integration

The job extracts the file ID from various Google Drive URL formats:
- `https://drive.google.com/file/d/{FILE_ID}/view`
- `https://drive.google.com/open?id={FILE_ID}`
- `https://drive.google.com/uc?id={FILE_ID}`

Downloads via: `https://drive.google.com/uc?export=download&id={FILE_ID}`

**Note:** The file MUST be shared with "Anyone with the link" permissions.

### Queue Configuration

- **Connection:** database (stored in `jobs` table)
- **Worker:** Runs in separate Docker container (`queue-worker`)
- **Command:** `php artisan queue:work database --sleep=3 --tries=3 --max-time=3600`

## Error Handling

### Failed Jobs

If a job fails:
- Album status set to 'failed'
- Admin notification sent with error message
- Error logged to `storage/logs/laravel.log`

### Individual Photo Errors

If a single photo fails to upload:
- Error is logged
- Processing continues with next photo
- Final notification shows successful count

## Testing

### Manual Test Steps

1. Create a test ZIP with images in subdirectories:
   ```
   test.zip
   ├── photo1.jpg
   ├── folder1/
   │   ├── photo2.jpg
   │   └── photo3.png
   └── folder2/
       └── photo4.jpg
   ```

2. Upload via Filament admin panel
3. Verify album status changes: none → pending → processing → completed
4. Check Photos tab for uploaded images
5. Verify all conversions created (thumb, preview, watermarked)

### Google Drive Test

1. Upload test ZIP to Google Drive
2. Share with "Anyone with the link"
3. Copy share URL
4. Upload via Filament using the link
5. Verify same behavior as local upload

### Queue Monitoring

```bash
# Check queue status
docker compose exec php-fpm php backend/artisan queue:monitor

# View failed jobs
docker compose exec php-fpm php backend/artisan queue:failed

# Retry failed job
docker compose exec php-fpm php backend/artisan queue:retry {job-id}

# View queue worker logs
docker compose logs -f queue-worker
```

## Troubleshooting

### Queue Worker Not Running

```bash
docker compose restart queue-worker
docker compose logs queue-worker
```

### Job Stuck in "pending"

Check if queue worker is processing jobs:
```bash
docker compose exec php-fpm php backend/artisan queue:listen --once
```

### ZIP Not Extracting

- Verify ZIP file is valid (not corrupted)
- Check available disk space
- Verify ZipArchive PHP extension is installed

### Google Drive Download Fails

- Ensure file has "Anyone with the link" sharing enabled
- Check file is not too large (>500MB)
- Verify network connectivity from container

## Related Files

- **Migration:** `database/migrations/2025_10_15_170250_add_zip_processing_to_albums.php`
- **Model:** `app/Models/Album.php`
- **Job:** `app/Jobs/ProcessZipUpload.php`
- **Resource:** `app/Filament/Resources/AlbumResource.php`
- **Relation Manager:** `app/Filament/Resources/AlbumResource/RelationManagers/PhotosRelationManager.php`
- **Service:** `app/Services/PhotoUploadService.php`
- **Listener:** `app/Listeners/ApplyWatermarkToPreview.php` (watermarking)
- **Event Provider:** `app/Providers/EventServiceProvider.php`

## Future Improvements

- [ ] Add support for RAW image formats
- [ ] Implement chunked Google Drive downloads for large files
- [ ] Add progress bar in Filament UI
- [ ] Support for other cloud storage providers (Dropbox, OneDrive)
- [ ] Batch notifications instead of one per completion
- [ ] Add retry mechanism for failed individual photos

