<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Photo;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create school classes
        $class9A = SchoolClass::create([
            'school' => 'Petőfi Sándor Gimnázium',
            'grade' => '9',
            'label' => '9.A',
        ]);

        $class12B = SchoolClass::create([
            'school' => 'Ady Endre Középiskola',
            'grade' => '12',
            'label' => '12.B',
        ]);

        // Extract student names from photo filenames
        $photoDir = storage_path('app/public/photos');
        $images = glob($photoDir.'/*.jpg');

        $studentNames = [];
        foreach ($images as $image) {
            $filename = basename($image);
            // Extract name from "Name Surname (number).jpg" format
            if (preg_match('/^(.+?)\s+\(\d+\)\.jpg$/', $filename, $matches)) {
                $name = trim($matches[1]);
                if (! in_array($name, $studentNames)) {
                    $studentNames[] = $name;
                }
            }
        }

        // Create example users (5 specific examples)
        $exampleUsers = [
            [
                'name' => 'Kovács Anna',
                'email' => 'kovacs.anna@example.com',
                'phone' => '+36 20 123 4567',
                'address' => ['country' => 'HU', 'zip' => '1051', 'city' => 'Budapest', 'line1' => 'Váci utca 12.'],
            ],
            [
                'name' => 'Nagy Péter',
                'email' => 'nagy.peter@example.com',
                'phone' => '+36 30 234 5678',
                'address' => ['country' => 'HU', 'zip' => '1117', 'city' => 'Budapest', 'line1' => 'Kassák Lajos utca 45.'],
            ],
            [
                'name' => 'Szabó Eszter',
                'email' => 'szabo.eszter@example.com',
                'phone' => '+36 70 345 6789',
                'address' => ['country' => 'HU', 'zip' => '1132', 'city' => 'Budapest', 'line1' => 'Visegrádi utca 78.'],
            ],
            [
                'name' => 'Tóth Balázs',
                'email' => 'toth.balazs@example.com',
                'phone' => '+36 20 456 7890',
                'address' => ['country' => 'HU', 'zip' => '1095', 'city' => 'Budapest', 'line1' => 'Szent László út 23.'],
            ],
            [
                'name' => 'Varga Zsófia',
                'email' => 'varga.zsofia@example.com',
                'phone' => '+36 30 567 8901',
                'address' => ['country' => 'HU', 'zip' => '1026', 'city' => 'Budapest', 'line1' => 'Margit körút 56.'],
            ],
        ];

        $users = [];
        foreach ($exampleUsers as $index => $userData) {
            $classId = $index % 2 == 0 ? $class9A->id : $class12B->id;

            $users[$userData['name']] = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_CUSTOMER,
                    'class_id' => $classId,
                    'phone' => $userData['phone'],
                    'address' => $userData['address'],
                ]
            );
        }

        // Also create users from photo filenames if they exist
        foreach ($studentNames as $index => $name) {
            // Skip if already created as example user
            if (isset($users[$name])) {
                continue;
            }

            $classId = $index % 2 == 0 ? $class9A->id : $class12B->id;
            $email = Str::slug($name).'@example.com';

            $users[$name] = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_CUSTOMER,
                    'class_id' => $classId,
                ]
            );
        }

        // Create albums for each user
        $albums = [];
        foreach ($users as $userName => $user) {
            $albums[$userName] = Album::create([
                'name' => $userName.' Album',
                'title' => $userName.' Fotóalbum',
                'user_id' => $user->id,
                'date' => now()->subDays(rand(1, 30)),
                'status' => 'active',
                'flags' => Album::getDefaultFlags(),
            ]);
        }

        // Create additional shared albums
        $sharedAlbum1 = Album::create([
            'name' => 'Osztályfotó Album',
            'title' => '9.A Osztályfotó 2025',
            'user_id' => $users['Kovács Anna']->id, // First user owns it
            'class_id' => $class9A->id,
            'date' => now()->subDays(5),
            'status' => 'active',
            'flags' => Album::getDefaultFlags(),
        ]);

        $sharedAlbum2 = Album::create([
            'name' => 'Ballagási Album',
            'title' => '12.B Ballagási Fotók 2025',
            'user_id' => $users['Nagy Péter']->id, // Second user owns it
            'class_id' => $class12B->id,
            'date' => now()->subDays(10),
            'status' => 'active',
            'flags' => Album::getDefaultFlags(),
        ]);

        // Distribute images to albums and assign to users
        foreach ($images as $index => $imagePath) {
            $filename = basename($imagePath);

            // Determine album (distribute evenly between user albums and shared albums)
            $albumId = null;
            $assignedUserId = null;

            if (preg_match('/^(.+?)\s+\(\d+\)\.jpg$/', $filename, $matches)) {
                $studentName = trim($matches[1]);
                if (isset($users[$studentName])) {
                    $assignedUserId = $users[$studentName]->id;

                    // Assign to user's personal album
                    if (isset($albums[$studentName])) {
                        $albumId = $albums[$studentName]->id;
                    }
                }
            }

            // If no personal album found, assign to shared albums
            if (! $albumId) {
                if ($index % 2 == 0) {
                    $albumId = $sharedAlbum1->id;
                } else {
                    $albumId = $sharedAlbum2->id;
                }
            }

            $this->createPhoto($albumId, $filename, $assignedUserId);
        }

        $this->command->info('✓ Created '.SchoolClass::count().' school classes');
        $this->command->info('✓ Created '.User::where('role', User::ROLE_CUSTOMER)->count().' demo users');
        $this->command->info('✓ Created '.Album::count().' albums');
        $this->command->info('✓ Created '.Photo::count().' photos');
        $this->command->info('✓ Assigned '.Photo::whereNotNull('assigned_user_id')->count().' photos to users');
        $this->command->info('✓ Unassigned: '.Photo::whereNull('assigned_user_id')->count().' photos');
    }

    private function createPhoto(int $albumId, string $filename, ?int $assignedUserId = null): void
    {
        // Get image dimensions
        $imagePath = storage_path('app/public/photos/'.$filename);
        $imageInfo = @getimagesize($imagePath);

        Photo::create([
            'album_id' => $albumId,
            'path' => 'photos/'.$filename,
            'original_filename' => $filename,
            'hash' => hash_file('sha256', $imagePath),
            'width' => $imageInfo ? $imageInfo[0] : 800,
            'height' => $imageInfo ? $imageInfo[1] : 1200,
            'assigned_user_id' => $assignedUserId,
        ]);
    }
}
