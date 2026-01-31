<?php

namespace Database\Seeders;

use App\Models\TabloSampleTemplate;
use App\Models\TabloSampleTemplateCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeder for sample tablo templates.
 * Downloads stock photos from Picsum for testing.
 *
 * Usage: php artisan db:seed --class=TabloSampleTemplateSeeder
 */
class TabloSampleTemplateSeeder extends Seeder
{
    /**
     * Template names for each category.
     */
    private array $klasszikusNames = [
        'Elegáns Kék', 'Arany Csillogás', 'Bordó Klasszikus', 'Ezüst Elegancia',
        'Fekete Gyöngy', 'Bíbor Álom', 'Smaragd Fény', 'Korall Harmónia',
        'Bronz Ragyogás', 'Királykék',
    ];

    private array $modernNames = [
        'Minimál Fehér', 'Neon Gradient', 'Pasztell Álom', 'Urban Style',
        'Geometric Art', 'Soft Pastel', 'Clean Lines', 'Bold Colors',
        'Mono Chic', 'Fresh Start', 'Nordic Light', 'Ocean Blue',
        'Sunset Glow', 'Mint Fresh', 'Rose Gold', 'Sky High',
        'Forest Green', 'Desert Sand', 'Arctic White', 'Coral Reef',
    ];

    private array $mesesNames = [
        'Tündérkert', 'Űrkaland', 'Hercegnő Álom', 'Dzsungel Safari',
        'Kalóz Kincs', 'Varázserdő', 'Robot Világ', 'Dinoszaurusz Park',
        'Unikornis Szivárvány', 'Csillagközi Utazás', 'Mesebeli Kastély', 'Tengeri Kaland',
        'Szuperhős Akadémia', 'Állatkert Móka', 'Léghajó Utazás', 'Cukorka Ország',
        'Jégvarázs', 'Pillangó Rét', 'Sárkány Barlang', 'Manó Falu',
        'Cirkusz Világ', 'Időgép Kaland', 'Ninja Dojo', 'Építőkockák',
        'Mozdony Mese', 'Víz Alatti Világ', 'Felhők Felett', 'Gomba Erdő',
        'Méhecske Birodalom', 'Tűzoltó Hősök',
    ];

    public function run(): void
    {
        // Create categories if they don't exist
        $klasszikus = TabloSampleTemplateCategory::firstOrCreate(
            ['slug' => 'klasszikus'],
            [
                'name' => 'Klasszikus',
                'description' => 'Klasszikus, elegáns tabló minták',
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        $modern = TabloSampleTemplateCategory::firstOrCreate(
            ['slug' => 'modern'],
            [
                'name' => 'Modern',
                'description' => 'Modern, letisztult tabló minták',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $meses = TabloSampleTemplateCategory::firstOrCreate(
            ['slug' => 'meses'],
            [
                'name' => 'Mesés',
                'description' => 'Játékos, mesés tabló minták gyerekeknek',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        // Ensure directory exists
        Storage::disk('public')->makeDirectory('tablo-sample-templates');

        // Create templates: 10 klasszikus, 20 modern, 30 mesés
        $this->createTemplatesForCategory($klasszikus, $this->klasszikusNames, 10, 0);
        $this->createTemplatesForCategory($modern, $this->modernNames, 20, 100);
        $this->createTemplatesForCategory($meses, $this->mesesNames, 30, 200);

        $this->command->info('✅ Created 60 sample templates (10 klasszikus + 20 modern + 30 mesés)');
    }

    /**
     * Create templates for a category with stock photos.
     */
    private function createTemplatesForCategory(
        TabloSampleTemplateCategory $category,
        array $names,
        int $count,
        int $picsumOffset
    ): void {
        $this->command->info("📸 Creating {$count} templates for '{$category->name}'...");

        for ($i = 0; $i < $count; $i++) {
            $name = $names[$i % count($names)];
            // Add suffix if we're repeating names
            if ($i >= count($names)) {
                $name .= ' ' . (intval($i / count($names)) + 1);
            }

            $slug = Str::slug($name) . '-' . uniqid();
            $imagePath = "tablo-sample-templates/{$slug}.jpg";
            $fullPath = Storage::disk('public')->path($imagePath);

            // Download stock photo from Picsum (each ID gives a different image)
            $picsumId = $picsumOffset + $i + 1;
            $downloaded = $this->downloadStockPhoto($fullPath, $picsumId);

            if (! $downloaded) {
                $this->command->warn("  ⚠️  Failed to download image for '{$name}', using placeholder");
                $this->createPlaceholder($fullPath, $name);
            }

            $template = TabloSampleTemplate::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => "Gyönyörű {$category->name} stílusú tabló minta",
                    'image_path' => $imagePath,
                    'sort_order' => $i,
                    'is_active' => true,
                    'is_featured' => $i < 3, // First 3 are featured
                    'tags' => $i < 3 ? ['kiemelt', 'népszerű'] : ['új'],
                ]
            );

            $template->categories()->syncWithoutDetaching([$category->id]);

            $this->command->line("  ✓ {$name}");
        }
    }

    /**
     * Download a stock photo from Picsum.
     */
    private function downloadStockPhoto(string $path, int $picsumId): bool
    {
        try {
            // Picsum provides random photos by ID
            // 1200x900 is our target size
            $url = "https://picsum.photos/id/{$picsumId}/1200/900";

            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                $dir = dirname($path);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($path, $response->body());

                return true;
            }
        } catch (\Exception $e) {
            // Silent fail, will use placeholder
        }

        return false;
    }

    /**
     * Create a simple colored placeholder image.
     */
    private function createPlaceholder(string $path, string $name): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Generate a random pastel color
        $r = rand(150, 255);
        $g = rand(150, 255);
        $b = rand(150, 255);

        $img = imagecreatetruecolor(1200, 900);
        $bgColor = imagecolorallocate($img, $r, $g, $b);
        imagefill($img, 0, 0, $bgColor);

        // Add text if GD supports it
        $textColor = imagecolorallocate($img, 50, 50, 50);
        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($name);
        $x = (1200 - $textWidth) / 2;
        imagestring($img, $fontSize, (int) $x, 430, $name, $textColor);

        imagejpeg($img, $path, 90);
        imagedestroy($img);
    }
}
