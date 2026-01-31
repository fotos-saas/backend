<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_new', 50)
                ->default('customer')
                ->after('password');
        });

        DB::table('users')->select('id', 'role')->orderBy('id')->chunk(100, function ($users): void {
            foreach ($users as $user) {
                $mappedRole = match ($user->role) {
                    'admin' => 'super_admin',
                    'photo_admin' => 'photo_admin',
                    'user' => 'customer',
                    default => 'customer',
                };

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role_new' => $mappedRole]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_new', 'role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role_new', ['admin', 'user'])
                ->default('user')
                ->after('password');
        });

        DB::table('users')->select('id', 'role')->orderBy('id')->chunk(100, function ($users): void {
            foreach ($users as $user) {
                $mappedRole = match ($user->role) {
                    'super_admin' => 'admin',
                    'photo_admin' => 'admin',
                    default => 'user',
                };

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role_new' => $mappedRole]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_new', 'role');
        });
    }
};
