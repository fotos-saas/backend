<?php

declare(strict_types=1);

namespace App\Actions\Webshop;

use App\Models\ShopPaperSize;
use App\Models\ShopPaperType;
use App\Models\ShopSetting;

class InitializeWebshopAction
{
    private const DEFAULT_PAPER_SIZES = [
        ['name' => '10x15', 'width_cm' => 10, 'height_cm' => 15, 'display_order' => 1],
        ['name' => '13x18', 'width_cm' => 13, 'height_cm' => 18, 'display_order' => 2],
        ['name' => '15x20', 'width_cm' => 15, 'height_cm' => 20, 'display_order' => 3],
        ['name' => '20x30', 'width_cm' => 20, 'height_cm' => 30, 'display_order' => 4],
        ['name' => '30x40', 'width_cm' => 30, 'height_cm' => 40, 'display_order' => 5],
    ];

    private const DEFAULT_PAPER_TYPES = [
        ['name' => 'Fényes', 'description' => 'Fényes (glossy) fotópapír', 'display_order' => 1],
        ['name' => 'Matt', 'description' => 'Matt fotópapír', 'display_order' => 2],
        ['name' => 'Selyem', 'description' => 'Selyemfényű (lustre) fotópapír', 'display_order' => 3],
    ];

    public function execute(int $partnerId): ShopSetting
    {
        $settings = ShopSetting::firstOrCreate(
            ['tablo_partner_id' => $partnerId],
            ['is_enabled' => true]
        );

        $hasSizes = ShopPaperSize::byPartner($partnerId)->exists();
        if (!$hasSizes) {
            foreach (self::DEFAULT_PAPER_SIZES as $size) {
                ShopPaperSize::create([
                    'tablo_partner_id' => $partnerId,
                    ...$size,
                ]);
            }
        }

        $hasTypes = ShopPaperType::byPartner($partnerId)->exists();
        if (!$hasTypes) {
            foreach (self::DEFAULT_PAPER_TYPES as $type) {
                ShopPaperType::create([
                    'tablo_partner_id' => $partnerId,
                    ...$type,
                ]);
            }
        }

        return $settings;
    }
}
