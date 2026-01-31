<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $session1 = WorkSession::create([
            'name' => '9.A Osztály - Tavaszi Fotózás',
            'description' => 'Tavaszi fotózás a 9.A osztály számára',
            'digit_code_enabled' => true,
            'digit_code' => '123456',
            'digit_code_expires_at' => now()->addDays(30),
            'share_enabled' => false,
            'status' => 'active',
        ]);

        // Attach users
        $user1 = User::where('email', 'szabo.eszter@example.com')->first();
        $user2 = User::where('email', 'test@example.com')->first();
        if ($user1) {
            $session1->users()->attach($user1->id);
        }
        if ($user2) {
            $session1->users()->attach($user2->id);
        }

        $session2 = WorkSession::create([
            'name' => '12.B Osztály - Ballagás',
            'description' => 'Ballagási fotók a 12.B osztály számára',
            'digit_code_enabled' => true,
            'digit_code' => '789012',
            'digit_code_expires_at' => now()->addDays(60),
            'share_enabled' => true,
            'share_token' => Str::random(32),
            'share_expires_at' => now()->addDays(7),
            'status' => 'active',
        ]);

        $session3 = WorkSession::create([
            'name' => 'Egyedi Portré - Nagy Péter',
            'description' => 'Egyedi portré fotózás',
            'digit_code_enabled' => false,
            'share_enabled' => false,
            'status' => 'active',
        ]);

        // Attach user
        $user3 = User::where('email', 'nagy.peter@example.com')->first();
        if ($user3) {
            $session3->users()->attach($user3->id);
        }

        $session4 = WorkSession::create([
            'name' => 'Teszt Munkamenet - Minden Mód',
            'description' => 'Teszt célra minden belépési mód aktiválva',
            'digit_code_enabled' => true,
            'digit_code' => '999888',
            'digit_code_expires_at' => now()->addDays(30),
            'share_enabled' => true,
            'share_token' => Str::random(32),
            'share_expires_at' => now()->addDays(7),
            'status' => 'active',
        ]);

        // Attach multiple users
        if ($user2) {
            $session4->users()->attach($user2->id);
        }
        if ($user3) {
            $session4->users()->attach($user3->id);
        }

        // Example: Attach albums to work sessions
        // Demonstrate many-to-many relationship where one album can belong to multiple sessions
        $albums = Album::take(3)->get();

        if ($albums->count() > 0) {
            // Attach first album to session1
            if (isset($albums[0])) {
                $session1->albums()->attach($albums[0]->id);
            }

            // Attach second album to both session1 and session2 (many-to-many example)
            if (isset($albums[1])) {
                $session1->albums()->attach($albums[1]->id);
                $session2->albums()->attach($albums[1]->id);
            }

            // Attach third album to session3
            if (isset($albums[2])) {
                $session3->albums()->attach($albums[2]->id);
            }
        }
    }
}
