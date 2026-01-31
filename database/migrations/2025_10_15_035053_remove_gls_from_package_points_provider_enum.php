<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Get the enum type name used by Laravel
            $result = DB::select("
                SELECT t.typname 
                FROM pg_type t
                JOIN pg_attribute a ON a.atttypid = t.oid
                JOIN pg_class c ON c.oid = a.attrelid
                WHERE c.relname = 'package_points'
                AND a.attname = 'provider'
                AND t.typtype = 'e'
                LIMIT 1
            ");

            if (! empty($result)) {
                $enumTypeName = $result[0]->typname;

                // Check if there are any GLS package points
                $glsCount = DB::table('package_points')
                    ->where('provider', 'gls')
                    ->count();

                if ($glsCount > 0) {
                    // Delete GLS package points first
                    DB::table('package_points')
                        ->where('provider', 'gls')
                        ->delete();
                }

                // Note: PostgreSQL doesn't support removing enum values directly
                // The 'gls' value will remain in the enum but won't be used
                \Log::info('GLS package points removed. Enum value remains for safety.');
            }
        } catch (\Exception $e) {
            \Log::warning('Could not remove GLS from package_points: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We cannot restore deleted GLS package points
        // The enum value 'gls' should still be available
        \Log::info('GLS removal migration rolled back. Manual restoration required.');
    }
};
