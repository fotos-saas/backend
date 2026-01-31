# Face Recognition Feature

## Overview

The Photo Stack application includes face recognition capabilities powered by **CompreFace**, allowing automatic grouping of photos by detected faces. This is particularly useful for photographers who need to organize hundreds of photos from school events where students and teachers need to identify their own photos.

**New in CompreFace:**
- Face detection with high accuracy
- Gender detection (male/female)
- Face pose detection (left/center/right)
- Subject-based grouping for better accuracy
- Faster processing than previous solutions

## Features

- **Automatic Face Detection**: Detects faces in uploaded photos using AI
- **Face Grouping**: Groups photos with similar faces together using subject-based recognition
- **Gender Detection**: Automatically detects and stores gender information
- **Face Direction**: Detects face pose (left, center, right) based on yaw angle
- **Manual Override**: Admin can manually move photos between groups
- **Background Processing**: Face recognition runs in a queue to avoid blocking the UI
- **Progress Tracking**: Real-time status updates in the Filament admin panel

## Architecture

### Database Schema

**face_groups table:**
- `id` - Primary key
- `album_id` - Foreign key to albums
- `name` - Group name (e.g., "Csoport 1", "Csoport 2")
- `representative_photo_id` - Nullable foreign key to photos (used for group thumbnail/comparison)
- `created_at`, `updated_at` - Timestamps

**face_group_photo pivot table:**
- `id` - Primary key
- `face_group_id` - Foreign key to face_groups
- `photo_id` - Foreign key to photos
- `confidence` - Float (0.0-1.0) indicating detection confidence
- `created_at`, `updated_at` - Timestamps

**photos table (added columns):**
- `gender` - Varchar nullable ('male', 'female', 'unknown')
- `face_direction` - Varchar nullable ('left', 'center', 'right', 'unknown')

**albums table (added columns):**
- `face_grouping_status` - Varchar nullable (pending, processing, completed, failed)
- `face_total_photos` - Integer nullable
- `face_processed_photos` - Integer nullable (default: 0)

### Models

**FaceGroup Model** (`App\Models\FaceGroup`)
- Relationships:
  - `album()` - belongsTo Album
  - `photos()` - belongsToMany Photo (with pivot: confidence)
  - `representativePhoto()` - belongsTo Photo

**Photo Model** (updated)
- Added relationship: `faceGroups()` - belongsToMany FaceGroup
- Added fields: `gender`, `face_direction`

**Album Model** (updated)
- Added relationship: `faceGroups()` - hasMany FaceGroup

### Services

**CompreFaceService** (`App\Services\CompreFaceService`)
- AI-powered face recognition using CompreFace REST API
- Face detection with attributes (gender, pose)
- Subject-based grouping (CompreFace subjects)
- Implements `FaceRecognitionServiceInterface`

Main methods:
- `detectAndGroupFaces(Album $album, Collection $photos): void` - Main orchestrator
- `detectFaceWithAttributes(Photo $photo): ?array` - Detects face with gender and pose
- `addFaceToRecognition(Photo $photo, int $albumId): ?string` - Adds face to CompreFace
- `createFaceGroupsFromSubjects(Album $album, Collection $subjectsData): void` - Creates groups

**Service Provider:** `App\Providers\FaceRecognitionServiceProvider`
- Binds `FaceRecognitionServiceInterface` to `CompreFaceService`

### Background Jobs

**ProcessFaceGrouping Job** (`App\Jobs\ProcessFaceGrouping`)

- Runs in Laravel queue
- Timeout: 3600 seconds (1 hour)
- Tries: 1
- Updates album status throughout process
- Sends notifications on completion/failure
- Automatically saves gender and face_direction for each photo

## CompreFace Setup

### 1. Start CompreFace Stack

CompreFace is included in the docker-compose.production.yml file with three services:

```bash
docker compose -f docker-compose.production.yml up -d compreface-postgres-db compreface-core compreface-admin
```

**Services:**
- `compreface-postgres-db`: Database for CompreFace (separate from main app DB)
- `compreface-core`: Main API service (port 8000 internal)
- `compreface-admin`: Web UI for API key management (port 8002 external)

**Note:** First startup may take 2-3 minutes as CompreFace initializes the database and loads models.

### 2. Generate API Key

1. Open CompreFace Admin UI: `http://YOUR_VPS_IP:8002`
2. Register a new account (first user becomes admin)
3. Log in
4. Create a new Application (e.g., "Photo Stack")
5. Add a new Service under the application:
   - Name: "Face Recognition"
   - Type: "Recognition"
6. Copy the API Key from the service details

### 3. Environment Variables

Add to `backend/.env`:

```env
# CompreFace Configuration
COMPREFACE_API_URL=http://compreface-core:8000
COMPREFACE_API_KEY=your-generated-api-key-here
COMPREFACE_TIMEOUT=60
COMPREFACE_SIMILARITY_THRESHOLD=0.7

# CompreFace DB Password (optional, for security)
COMPREFACE_DB_PASSWORD=secure_password_here
```

**Important:** Use `http://compreface-core:8000` inside Docker network, not `localhost:8000`

### 4. Run Migrations

```bash
docker compose exec php-fpm php artisan migrate
```

This will add the `gender` and `face_direction` columns to the `photos` table.

### 5. Clear Old Face Groups (if migrating)

If you have old face groups from a previous system:

```bash
docker compose exec php-fpm php artisan tinker
>>> DB::table('face_group_photo')->truncate();
>>> DB::table('face_groups')->truncate();
>>> DB::table('photos')->update(['gender' => null, 'face_direction' => null]);
```

### 6. Restart Services

```bash
docker compose restart php-fpm queue-worker
```

### 7. Test the Setup

In Filament admin:
1. Go to an Album with photos
2. Click "Arcok csoportosítása (összes)"
3. Confirm the action
4. Watch the status column update in real-time

## How Face Recognition Works

### 1. Face Detection with Attributes

- Photo sent to CompreFace API (`POST /api/v1/detection/detect`)
- API detects face and returns:
  - Bounding box coordinates
  - Confidence score
  - Gender (male/female) with probability
  - Pose angles (yaw, pitch, roll)

### 2. Gender Extraction

- Gender probability > 0.6 → stored as 'male' or 'female'
- Otherwise → stored as 'unknown'

### 3. Face Direction from Pose

Yaw angle interpretation:
- `< -30°` → 'left' (face turned left)
- `-30° to 30°` → 'center' (looking at camera)
- `> 30°` → 'right' (face turned right)

### 4. Subject-Based Grouping

- First, try to recognize face against existing subjects
- If similarity ≥ 0.7 → add to existing subject
- If no match → create new subject with name `album_{id}_photo_{id}`
- CompreFace handles embedding comparison internally

### 5. FaceGroup Creation

- Group photos by CompreFace subject
- Create FaceGroup record for each subject
- Attach photos to groups with confidence scores
- Representative photo = highest confidence in group

### 6. Progress Tracking

- Album status updated after each photo
- Real-time counter: processed/total
- Notification sent on completion

## Configuration Options

**Config file:** `backend/config/face-recognition.php`

```php
'compreface' => [
    'url' => env('COMPREFACE_API_URL', 'http://compreface-core:8000'),
    'api_key' => env('COMPREFACE_API_KEY', ''),
    'timeout' => env('COMPREFACE_TIMEOUT', 60),
],
'grouping' => [
    'similarity_threshold' => 0.7,
    'auto_name_prefix' => 'Csoport'
],
```

## Troubleshooting

### CompreFace API not responding

**Check if CompreFace services are running:**
```bash
docker compose -f docker-compose.production.yml ps
```

**View logs:**
```bash
docker compose -f docker-compose.production.yml logs compreface-core
docker compose -f docker-compose.production.yml logs compreface-admin
```

**Restart CompreFace:**
```bash
docker compose -f docker-compose.production.yml restart compreface-core compreface-admin
```

### Invalid API Key error

**Cause:** API key not set or incorrect in `.env`

**Fix:**
1. Check `.env` file: `COMPREFACE_API_KEY=...`
2. Regenerate key in CompreFace Admin UI if needed
3. Restart PHP container: `docker compose restart php-fpm`

### Face grouping stuck in "processing"

**Check queue worker:**
```bash
docker compose logs queue-worker -f
```

**Manually reset album status:**
```php
php artisan tinker
>>> $album = App\Models\Album::find(1);
>>> $album->update(['face_grouping_status' => null]);
```

### Gender or face_direction not detected

**Cause:** Photo quality too low or face not clearly visible

**Check:**
- Face should be > 50x50 pixels
- Face should be well-lit
- Face should be mostly facing camera (yaw < 90°)

### Too many groups created

**Increase similarity threshold:**
```php
// config/face-recognition.php
'grouping' => [
    'similarity_threshold' => 0.8, // was 0.7
],
```

### Database connection error for CompreFace

**Check CompreFace DB password:**
```bash
# In .env
COMPREFACE_DB_PASSWORD=your_password
```

Must match in docker-compose.production.yml

## Performance Considerations

### Resource Usage

**CompreFace Stack:**
- compreface-core: 2GB RAM
- compreface-admin: 1GB RAM
- compreface-postgres-db: 512MB RAM
- Total: ~3.5GB RAM

**Processing Time:**
- ~2-4 seconds per photo (faster than DeepFace)
- 100 photos ≈ 4-7 minutes
- 600 photos ≈ 25-45 minutes

### Optimization Tips

1. **Use smaller images:**
   - CompreFace works well with preview-sized images (1200x1200)
   - Service automatically uses preview conversions

2. **Batch processing:**
   - Process photos during off-peak hours
   - Queue worker handles multiple jobs efficiently

3. **Database indexing:**
   - Indexes already applied on foreign keys
   - `gender` and `face_direction` indexed for fast filtering

## Usage in Filament Admin

### Album List View

**Column: "Arcfelismerés"**
- Shows current face grouping status
- Badge colors:
  - Gray: Not started
  - Warning (yellow): Pending
  - Info (blue): Processing (X/Y)
  - Success (green): Completed
  - Danger (red): Failed

**Actions (per album):**

1. **"Arcok csoportosítása (összes)"** - Group all faces
   - Icon: user-group
   - Color: Primary (blue)
   - Requires confirmation
   - Deletes existing groups and re-processes all photos

2. **"Csoportosítatlanok feldolgozása"** - Process ungrouped only
   - Icon: user-plus
   - Color: Success (green)
   - Processes only photos without face groups
   - Useful for newly uploaded photos

### Photos Relation Manager

**Columns:**

1. **"Arccsoport"** - Sortable!
   - Shows which face group(s) the photo belongs to
   - Badge: Green if grouped, gray if not
   - **Click to sort by face group**

2. **"Nem"** (Gender) - Toggleable (hidden by default)
   - Blue badge: Fiú (male)
   - Pink badge: Lány (female)
   - Gray badge: Ismeretlen (unknown)

3. **"Tekintet"** (Face Direction) - Toggleable (hidden by default)
   - Warning badge: Balra (left)
   - Success badge: Középre (center)
   - Info badge: Jobbra (right)
   - Gray badge: Ismeretlen (unknown)

**Filters:**

- **Arccsoport** - Filter by face group (multiple selection)
- **Nem** - Filter by gender (Fiú/Lány/Ismeretlen)
- **Tekintet** - Filter by face direction (Balra/Középre/Jobbra)

**Bulk Action: "Áthelyezés csoportba"**
- Select multiple photos
- Choose target face group
- Manually override AI grouping
- Confidence set to 1.0 (manual = 100%)

## API Reference

### CompreFace API Endpoints

**POST** `/api/v1/detection/detect`

Detect face with attributes (gender, age, pose).

**Request:**
- Headers: `x-api-key: YOUR_API_KEY`
- Body: multipart/form-data with `file`
- Query params: `age=false&gender=true&pose=true`

**Response:**
```json
{
  "result": [
    {
      "box": {
        "probability": 0.99,
        "x_max": 537,
        "y_max": 706,
        "x_min": 365,
        "y_min": 479
      },
      "gender": {
        "value": "male",
        "probability": 0.87
      },
      "pose": {
        "pitch": 5.2,
        "roll": -2.1,
        "yaw": -15.3
      }
    }
  ]
}
```

---

**POST** `/api/v1/recognition/recognize`

Recognize face against existing subjects.

**Request:**
- Headers: `x-api-key: YOUR_API_KEY`
- Body: multipart/form-data with `file`
- Query params: `limit=1&prediction_count=1`

**Response:**
```json
{
  "result": [
    {
      "subjects": [
        {
          "subject": "album_1_photo_42",
          "similarity": 0.89
        }
      ]
    }
  ]
}
```

---

**POST** `/api/v1/recognition/faces?subject={subject_name}`

Add face to a subject (create new or add to existing).

**Request:**
- Headers: `x-api-key: YOUR_API_KEY`
- Body: multipart/form-data with `file`
- Query params: `subject=album_1_photo_42`

**Response:**
```json
{
  "image_id": "uuid-here",
  "subject": "album_1_photo_42"
}
```

## Comparison: CompreFace vs DeepFace

| Feature | CompreFace | DeepFace |
|---------|-----------|----------|
| Speed | 2-4s per photo | 4-8s per photo |
| Gender Detection | ✅ Yes | ❌ No |
| Face Pose | ✅ Yes | ❌ No |
| Subject Management | ✅ Built-in | ❌ Manual |
| Web UI | ✅ Yes | ❌ No |
| Memory Usage | ~3.5GB | ~2GB |
| API Key Security | ✅ Yes | ❌ No auth |
| ARM64 Support | ✅ Yes | ✅ Yes |

## Future Improvements

- [ ] Age detection integration
- [ ] Face group naming by admin
- [ ] Merge/split face groups
- [ ] Export face groups as separate folders
- [ ] Email notification to users when their group is ready
- [ ] Face group thumbnails in album overview
- [ ] Search photos by face group
- [ ] Statistics: most common face groups, detection accuracy
- [ ] Support for multiple faces per photo
- [ ] Face quality score

## Support

For issues or questions:
1. Check logs: `docker compose logs compreface-core queue-worker`
2. Review this documentation
3. Check CompreFace docs: https://github.com/exadel-inc/CompreFace
4. Contact system administrator
