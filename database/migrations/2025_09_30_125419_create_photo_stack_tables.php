<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Classes
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('school');
            $table->string('grade');
            $table->string('label');
            $table->timestamps();
        });

        // Update users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->enum('role', ['admin', 'user'])->default('user')->after('password');
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete()->after('role');
        });

        // Albums
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->enum('visibility', ['public', 'link'])->default('link');
            $table->timestamps();
        });

        // Photos
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('albums')->cascadeOnDelete();
            $table->string('path');
            $table->string('hash', 64);
            $table->integer('width')->unsigned();
            $table->integer('height')->unsigned();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('hash');
        });

        // Photo Notes
        Schema::create('photo_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('text');
            $table->timestamps();
        });

        // Print Sizes
        Schema::create('print_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->integer('width_mm')->unsigned();
            $table->integer('height_mm')->unsigned();
            $table->timestamps();
        });

        // Price Lists
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('albums')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Prices
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('print_size_id')->constrained('print_sizes')->cascadeOnDelete();
            $table->integer('gross_huf')->unsigned();
            $table->integer('digital_price_huf')->unsigned()->nullable();
            $table->timestamps();
        });

        // Packages
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('albums')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        // Package Items
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('print_size_id')->constrained('print_sizes')->cascadeOnDelete();
            $table->integer('qty')->unsigned();
            $table->timestamps();
        });

        // Carts
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['draft', 'ordered'])->default('draft');
            $table->timestamps();
        });

        // Cart Items
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->enum('type', ['print', 'digital'])->default('print');
            $table->foreignId('print_size_id')->nullable()->constrained('print_sizes')->nullOnDelete();
            $table->integer('qty')->unsigned()->default(1);
            $table->timestamps();
        });

        // Orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', [
                'draft', 'submitted', 'payment_pending', 'paid',
                'in_production', 'fulfilled', 'delivered',
                'cancelled', 'refunded',
            ])->default('draft');
            $table->integer('total_gross_huf')->unsigned();
            $table->string('stripe_pi')->nullable();
            $table->string('invoice_no')->nullable();
            $table->timestamps();
        });

        // Order Items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->enum('type', ['print', 'digital'])->default('print');
            $table->foreignId('print_size_id')->nullable()->constrained('print_sizes')->nullOnDelete();
            $table->integer('qty')->unsigned();
            $table->integer('unit_price_gross_huf')->unsigned();
            $table->timestamps();
        });

        // Settings (key-value store)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('prices');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('print_sizes');
        Schema::dropIfExists('photo_notes');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('albums');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropColumn(['phone', 'role', 'class_id']);
        });

        Schema::dropIfExists('classes');
    }
};
