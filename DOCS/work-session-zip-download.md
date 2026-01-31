# Work Session Albums ZIP Download Feature

## Overview

This feature allows administrators to download all photos from a work session's albums as a single ZIP file. The photos are organized by album in separate folders within the ZIP, using the original uploaded files.

## Feature Location

**Filament Admin Panel** → **Munkamenetek** (Work Sessions) → **Műveletek** (Actions) → **Albumok letöltése ZIP-ben**

The download action appears in the "More Actions" dropdown menu for each work session row in the table.

## Usage

1. Navigate to the Work Sessions list in Filament admin panel
2. Click the "Műveletek" (Actions) button for any work session
3. Select "Albumok letöltése ZIP-ben" (Download Albums as ZIP)
4. The ZIP file will be generated and automatically downloaded to your browser

## ZIP Structure

```
123 - Work Session Name.zip
└── 123 - Work Session Name/         ← Root folder inside ZIP
    ├── 45 - Album Name 1/
    │   ├── IMG_1234.jpg
    │   ├── DSC_5678.jpg
    │   └── photo-001.jpg
    ├── 67 - Album Name 2/
    │   ├── IMG_1234 (1).jpg  ← Duplicate name, numbered
    │   └── vacation.jpg
    └── 89 - Another Album/
        └── wedding.jpg
```

### File Naming Conventions

- **ZIP File**: `{work-session-id} - {work-session-name}.zip`
  - Example: `123 - Wedding Photography.zip`
- **Root Folder (inside ZIP)**: `{work-session-id} - {work-session-name}/`
  - Example: `123 - Wedding Photography/`
  - This folder contains all album folders
- **Album Folders**: `{album-id} - {album-name}` (sanitized)
  - Example: `45 - Ceremony Photos/`
- **Photo Files**: Original filenames from upload (stored in media custom properties)
  - Example: `IMG_1234.jpg`, `DSC_5678.jpg`
  - Fallback: `photo-{id}.{extension}` if original filename not available

### Duplicate Filename Handling

When multiple photos have the same original filename within the same album, they are automatically numbered:

- First occurrence: `IMG_1234.jpg`
- Second occurrence: `IMG_1234 (1).jpg`
- Third occurrence: `IMG_1234 (2).jpg`
- And so on...

This ensures no files are overwritten and all photos are preserved.

### Special Cases

- **Empty Albums**: Skipped entirely (not included in ZIP)
- **Invalid Characters**: Replaced with `-` in folder/file names (`/`, `\`, `:`, `*`, `?`, `"`, `<`, `>`, `|`)
- **Missing Original Filename**: Falls back to `photo-{id}.{extension}` format

## API Endpoint

### Download Albums ZIP

```
GET /api/work-sessions/{workSession}/download-albums-zip
```

**Route Location**: `routes/web.php` (web route with session authentication)

**Authentication**: Required (web session via `auth` middleware - for Filament admin panel)

**Parameters**:
- `{workSession}` - Work Session ID (route parameter)

**Response**:
- **Success**: ZIP file download (Content-Type: application/zip)
- **Error**: JSON with error message (HTTP 500)

**Note**: This endpoint is in `web.php` (not `api.php`) because:
- Filament admin panel uses **session-based authentication**
- API routes don't load session middleware by default
- Web routes automatically handle session cookies
- No need for Sanctum token when accessed from Filament admin panel

**Example cURL** (requires active session):
```bash
# From browser after logging into Filament admin
curl -X GET \
  'http://localhost:8000/api/work-sessions/1/download-albums-zip' \
  --cookie "photo_stack_session=YOUR_SESSION_COOKIE" \
  --output albums.zip
```

## Backend Architecture

### Service Class

**File**: `app/Services/WorkSessionZipService.php`

**Main Method**:
```php
public function generateAlbumsZip(WorkSession $workSession): string
```

**Responsibilities**:
- Create temporary ZIP file in system temp directory (`sys_get_temp_dir()`)
- Iterate through all albums in the work session
- For each album, create a folder in the ZIP
- Add original photo files (via Spatie Media Library)
- Return path to generated ZIP file

**Helper Methods**:
- `generateZipFileName()` - Create ZIP filename with work session ID and name
- `sanitizeFolderName()` - Remove invalid characters from folder/file names
- `resolveUniqueFilename()` - Handle duplicate filenames with automatic numbering
- `cleanup()` - Delete temporary ZIP file

### Controller

**File**: `app/Http/Controllers/Api/WorkSessionController.php`

**Method**:
```php
public function downloadAlbumsZip(WorkSession $workSession): Response
```

**Responsibilities**:
- Call `WorkSessionZipService::generateAlbumsZip()`
- Return download response with `deleteFileAfterSend(true)`
- Handle exceptions and return JSON error response
- Log errors for debugging

### Filament Action

**File**: `app/Filament/Resources/WorkSessions/Tables/WorkSessionsTable.php`

**Implementation**:
```php
Action::make('download_albums_zip')
    ->label('Albumok letöltése ZIP-ben')
    ->icon('heroicon-o-archive-box-arrow-down')
    ->color('info')
    ->visible(fn (WorkSession $record) => $record->albums()->count() > 0)
    ->url(fn (WorkSession $record) => route('api.work-sessions.download-albums-zip', $record))
    ->openUrlInNewTab()
```

**Features**:
- Only visible if work session has at least one album
- Opens in new tab to avoid interrupting admin panel
- Uses Heroicon archive icon
- Info color (blue)

## Error Handling

### Common Errors

1. **No albums found**
   - Exception: "No albums found for this work session"
   - Cause: Work session has no associated albums
   - Prevention: Action is hidden if `albums_count == 0`

2. **No photos found**
   - Exception: "No photos found in albums"
   - Cause: All albums are empty (no photos)
   - Result: ZIP file is deleted, error returned

3. **Cannot create ZIP file**
   - Exception: "Cannot create ZIP file"
   - Cause: Permission issues or disk space
   - Note: Uses system temp directory (`sys_get_temp_dir()`), which is typically `/tmp` on Linux/Mac

4. **File not found**
   - Cause: Original photo file missing from disk
   - Handling: Photo is skipped, other photos continue processing

### Debugging

Check Laravel logs for detailed error messages:
```bash
tail -f storage/logs/laravel.log
```

Error log format:
```
Failed to generate work session albums ZIP
- work_session_id: 123
- error: [Exception message]
```

## Performance Considerations

### Memory Usage

- **Small work sessions** (< 100 photos, < 100MB): No issues
- **Medium work sessions** (100-500 photos, 100-500MB): May take 10-30 seconds
- **Large work sessions** (> 500 photos, > 500MB): Risk of timeout or memory limit

### Recommendations

- Set appropriate PHP `memory_limit` (at least 256MB, recommended 512MB)
- Set `max_execution_time` to at least 120 seconds for large work sessions
- Consider background job queue for very large work sessions (future enhancement)

### Disk Space

- Temporary ZIP files are stored in system temp directory (typically `/tmp`)
- Files are automatically deleted after download (`deleteFileAfterSend(true)`)
- System temp directory is usually cleaned by OS automatically
- Monitor disk space if multiple concurrent downloads occur

## Security

### Authorization

- Endpoint requires Sanctum authentication (`auth:sanctum`)
- Only authenticated admin users can access Filament panel
- No additional permission checks (all admins can download)

### File Access

- Uses Spatie Media Library's secure file access
- Only accesses photos associated with the work session
- No arbitrary file access possible

## Testing Scenarios

### Manual Testing

1. **Normal case**: Work session with 2-3 albums, 5-10 photos each
2. **Empty albums**: Work session with some empty albums
3. **Duplicate names**: Multiple albums with same name
4. **Special characters**: Album names with special characters (`/`, `:`, etc.)
5. **Large work session**: 100+ photos to test performance
6. **No albums**: Work session without albums (action should be hidden)

### Expected Results

- ZIP file downloads successfully
- File structure matches expected format
- Original photos are intact and not corrupted
- Temporary files are cleaned up after download

## Future Enhancements

Potential improvements for future versions:

1. **Background Processing**: Queue job for large work sessions
2. **Progress Indicator**: Real-time progress bar during ZIP generation
3. **Selective Download**: Choose specific albums to include
4. **Compression Options**: Different compression levels
5. **Email Delivery**: Send download link via email for very large ZIPs
6. **Caching**: Cache generated ZIPs for X hours to avoid regeneration

## Related Files

- `app/Services/WorkSessionZipService.php` - Core ZIP generation logic
- `app/Http/Controllers/Api/WorkSessionController.php` - Controller endpoint
- `app/Filament/Resources/WorkSessions/Tables/WorkSessionsTable.php` - Filament UI action
- `routes/web.php` - Route definition (session-based auth)
- `app/Models/WorkSession.php` - Work session model with relationships
- `app/Models/Album.php` - Album model with photos relationship
- `app/Models/Photo.php` - Photo model with Spatie Media Library

## Changelog

### 2025-10-16 (v1.4)
- **Enhanced**: Added root folder inside ZIP with work session name
- **Structure**: `{ID} - {name}.zip` → `{ID} - {name}/` → `{album-id} - {album}/` → photos
- Provides better organization when extracting multiple ZIP files
- All album folders are now contained within a single root folder

### 2025-10-16 (v1.3)
- **Enhanced**: Use original filenames for photos in ZIP (from media custom properties)
- **Enhanced**: Added automatic duplicate filename handling with numbering (e.g., "IMG_1234 (1).jpg")
- **Enhanced**: ZIP filename now includes work session ID: `{ID} - {name}.zip`
- **Enhanced**: Album folders now include album ID: `{album-id} - {album-name}/`
- **Improved**: Simplified service methods, removed redundant code
- **Added**: New `resolveUniqueFilename()` method for duplicate detection
- Ensures all photos retain their original names and no files are overwritten

### 2025-10-16 (v1.2)
- **Fixed**: Changed temp directory from `storage/app/temp/` to `sys_get_temp_dir()`
- Reason: Permission issues - system temp directory is guaranteed writable
- Uses `/tmp` on Linux/Mac, automatically cleaned by OS

### 2025-10-16 (v1.1)
- **Fixed**: Moved route from `api.php` to `web.php` to support session-based authentication
- Reason: Filament admin panel uses session auth, not Sanctum tokens
- Updated middleware from `auth:sanctum,web` to `auth` (web session only)

### 2025-10-16 (v1.0)
- Initial implementation
- Basic ZIP generation with album folders
- API endpoint and Filament action
- Error handling and logging

