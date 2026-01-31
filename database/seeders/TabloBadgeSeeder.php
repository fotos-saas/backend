<?php

namespace Database\Seeders;

use App\Models\TabloBadge;
use Illuminate\Database\Seeder;

class TabloBadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // HOZZÁSZÓLÁS BADGE-EK
            [
                'key' => 'first_post',
                'name' => 'Első hozzászólás',
                'description' => 'Gratulálunk az első hozzászólásodhoz!',
                'tier' => 'bronze',
                'icon' => 'heroicon-o-chat-bubble-left',
                'color' => 'amber-600',
                'points' => 10,
                'criteria' => ['posts' => 1],
                'sort_order' => 1,
            ],
            [
                'key' => 'active_participant',
                'name' => 'Aktív résztvevő',
                'description' => '10 hozzászólást írtál a fórumon.',
                'tier' => 'silver',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => 'slate-500',
                'points' => 25,
                'criteria' => ['posts' => 10],
                'sort_order' => 2,
            ],
            [
                'key' => 'forum_guru',
                'name' => 'Fórum guru',
                'description' => '50 hozzászólással támogatod a közösséget!',
                'tier' => 'gold',
                'icon' => 'heroicon-o-fire',
                'color' => 'yellow-500',
                'points' => 50,
                'criteria' => ['posts' => 50],
                'sort_order' => 3,
            ],

            // LIKE BADGE-EK
            [
                'key' => 'helpful',
                'name' => 'Segítőkész',
                'description' => '5 like-ot kaptál a hozzászólásaidra.',
                'tier' => 'bronze',
                'icon' => 'heroicon-o-hand-thumb-up',
                'color' => 'blue-600',
                'points' => 10,
                'criteria' => ['likes_received' => 5],
                'sort_order' => 4,
            ],
            [
                'key' => 'popular',
                'name' => 'Népszerű',
                'description' => '25 like-ot kaptál a közösségtől!',
                'tier' => 'silver',
                'icon' => 'heroicon-o-heart',
                'color' => 'pink-500',
                'points' => 25,
                'criteria' => ['likes_received' => 25],
                'sort_order' => 5,
            ],
            [
                'key' => 'legend',
                'name' => 'Legenda',
                'description' => '100 like! A közösség imádja a hozzászólásaidat!',
                'tier' => 'gold',
                'icon' => 'heroicon-o-trophy',
                'color' => 'yellow-500',
                'points' => 50,
                'criteria' => ['likes_received' => 100],
                'sort_order' => 6,
            ],

            // RANG BADGE-EK
            [
                'key' => 'veteran',
                'name' => 'Veterán',
                'description' => 'Elérted a Veterán rangot!',
                'tier' => 'silver',
                'icon' => 'heroicon-o-shield-check',
                'color' => 'indigo-600',
                'points' => 30,
                'criteria' => ['rank_level' => 4],
                'sort_order' => 7,
            ],
            [
                'key' => 'master',
                'name' => 'Mester',
                'description' => 'Mester rang elérve! Lenyűgöző!',
                'tier' => 'gold',
                'icon' => 'heroicon-o-star',
                'color' => 'purple-600',
                'points' => 50,
                'criteria' => ['rank_level' => 5],
                'sort_order' => 8,
            ],

            // KÜLÖNLEGES BADGE-EK
            [
                'key' => 'early_bird',
                'name' => 'Korai madár',
                'description' => 'Az első 100 felhasználó között regisztráltál!',
                'tier' => 'gold',
                'icon' => 'heroicon-o-sparkles',
                'color' => 'cyan-500',
                'points' => 20,
                'criteria' => [], // Manuálisan ítéljük oda
                'sort_order' => 9,
            ],
        ];

        foreach ($badges as $badge) {
            TabloBadge::updateOrCreate(
                ['key' => $badge['key']],
                $badge
            );
        }
    }
}
