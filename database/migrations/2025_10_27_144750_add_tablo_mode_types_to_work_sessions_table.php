<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablómód típusok hozzáadása a work_sessions táblához.
     *
     * Új oszlopok:
     * - tablo_mode_type: Tablómód típusa (fixed/flexible/packages)
     * - extra_photo_price_list_id: Árlisták FK extra képekhez
     * - extra_photo_print_size_id: Nyomtatási méretek FK extra képekhez
     * - extra_pricing_snapshot: Historikus árak JSON-ben
     * - allowed_package_ids: Engedélyezett csomagok JSON-ben
     */
    public function up(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            // Tablómód típusa (fixed: fix ár, flexible: árlisták, packages: csomagok)
            $table->string('tablo_mode_type')->nullable()->after('is_tablo_mode');

            // FK árlistához (extra képek árazásához flexible módban)
            $table->foreignId('extra_photo_price_list_id')
                ->nullable()
                ->after('tablo_mode_type')
                ->constrained('price_lists')
                ->nullOnDelete();

            // FK nyomtatási mérethez (extra képek méretéhez flexible módban)
            $table->foreignId('extra_photo_print_size_id')
                ->nullable()
                ->after('extra_photo_price_list_id')
                ->constrained('print_sizes')
                ->nullOnDelete();

            // Historikus árak mentése (kritikus: price_list/print_size törlésekor is megmarad az ár)
            $table->json('extra_pricing_snapshot')->nullable()->after('extra_photo_print_size_id')
                ->comment('Historikus árak: {price_list_id, print_size_id, price, currency, valid_at}');

            // Engedélyezett csomagok ID-i (packages módban)
            $table->json('allowed_package_ids')->nullable()->after('extra_pricing_snapshot')
                ->comment('Csomag ID-k tömbje: [1, 3, 5] vagy metadata-val: [{id: 1, order: 1}]');

            // Index-ek a teljesítmény érdekében
            $table->index('extra_photo_price_list_id');
            $table->index('extra_photo_print_size_id');
        });

        // PostgreSQL CHECK constraint a tablo_mode_type értékeire
        DB::statement("
            ALTER TABLE work_sessions
            ADD CONSTRAINT work_sessions_tablo_mode_type_check
            CHECK (tablo_mode_type IN ('fixed', 'flexible', 'packages') OR tablo_mode_type IS NULL)
        ");
    }

    /**
     * Migráció visszavonása.
     */
    public function down(): void
    {
        // PostgreSQL CHECK constraint törlése
        DB::statement("ALTER TABLE work_sessions DROP CONSTRAINT IF EXISTS work_sessions_tablo_mode_type_check");

        Schema::table('work_sessions', function (Blueprint $table) {
            // Foreign key-k törlése
            $table->dropForeign(['extra_photo_price_list_id']);
            $table->dropForeign(['extra_photo_print_size_id']);

            // Oszlopok törlése
            $table->dropColumn([
                'tablo_mode_type',
                'extra_photo_price_list_id',
                'extra_photo_print_size_id',
                'extra_pricing_snapshot',
                'allowed_package_ids',
            ]);
        });
    }
};
