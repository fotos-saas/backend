<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TabloPokePreset;

/**
 * Tablo Poke Preset Seeder
 *
 * Alapértelmezett bökés preset üzenetek feltöltése.
 */
class TabloPokePresetSeeder extends Seeder
{
    /**
     * Preset üzenetek
     */
    private array $presets = [
        // Általános
        [
            'key' => 'general_nudge',
            'emoji' => '👉',
            'text_hu' => 'hé, ne felejts el szavazni!',
            'category' => null,
            'sort_order' => 1,
        ],
        [
            'key' => 'general_hello',
            'emoji' => '👋',
            'text_hu' => 'szia, várunk!',
            'category' => null,
            'sort_order' => 2,
        ],
        [
            'key' => 'general_please',
            'emoji' => '🙏',
            'text_hu' => 'légyszi csináld meg',
            'category' => null,
            'sort_order' => 3,
        ],

        // Szavazás
        [
            'key' => 'voting_reminder',
            'emoji' => '🗳️',
            'text_hu' => 'várunk a szavazatodra!',
            'category' => 'voting',
            'sort_order' => 10,
        ],
        [
            'key' => 'voting_deadline',
            'emoji' => '⏰',
            'text_hu' => 'hamarosan lejár a szavazás!',
            'category' => 'voting',
            'sort_order' => 11,
        ],
        [
            'key' => 'voting_everyone',
            'emoji' => '👥',
            'text_hu' => 'mindenki szavazott már rajtad kívül',
            'category' => 'voting',
            'sort_order' => 12,
        ],

        // Fotózás
        [
            'key' => 'photoshoot_missing',
            'emoji' => '📸',
            'text_hu' => 'hiányzik a fotód!',
            'category' => 'photoshoot',
            'sort_order' => 20,
        ],
        [
            'key' => 'photoshoot_book',
            'emoji' => '📅',
            'text_hu' => 'foglalj időpontot a fotózásra!',
            'category' => 'photoshoot',
            'sort_order' => 21,
        ],
        [
            'key' => 'photoshoot_urgent',
            'emoji' => '🚨',
            'text_hu' => 'sürgős! nincs meg a képed',
            'category' => 'photoshoot',
            'sort_order' => 22,
        ],

        // Képválasztás
        [
            'key' => 'image_select_reminder',
            'emoji' => '🖼️',
            'text_hu' => 'válaszd ki a képedet!',
            'category' => 'image_selection',
            'sort_order' => 30,
        ],
        [
            'key' => 'image_select_waiting',
            'emoji' => '⌛',
            'text_hu' => 'rád várunk a képválasztással',
            'category' => 'image_selection',
            'sort_order' => 31,
        ],
        [
            'key' => 'image_select_almost',
            'emoji' => '🏁',
            'text_hu' => 'már csak te hiányzol a képválasztásból!',
            'category' => 'image_selection',
            'sort_order' => 32,
        ],
    ];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        foreach ($this->presets as $preset) {
            TabloPokePreset::updateOrCreate(
                ['key' => $preset['key']],
                [
                    'emoji' => $preset['emoji'],
                    'text_hu' => $preset['text_hu'],
                    'category' => $preset['category'],
                    'sort_order' => $preset['sort_order'],
                ]
            );
        }

        $this->command->info('Tablo poke presets seeded: ' . count($this->presets) . ' presets');
    }
}
