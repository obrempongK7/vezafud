<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('business_branches')->nullOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('menu_type', ['main', 'breakfast', 'lunch', 'dinner', 'drinks', 'desserts', 'specials', 'seasonal'])->default('main');
            $table->boolean('is_active')->default(true);
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            $table->json('available_days')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('menu_categories')->nullOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('sku', 100)->nullable();
            $table->enum('availability', ['available', 'unavailable', 'limited'])->default('available');
            $table->integer('stock_quantity')->nullable();
            $table->integer('preparation_time')->default(15)->comment('minutes');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_spicy')->default(false);
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_gluten_free')->default(false);
            $table->boolean('is_halal')->default(false);
            $table->json('allergens')->nullable();
            $table->json('nutritional_info')->nullable();
            $table->integer('calories')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('order_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['menu_id', 'availability']);
            $table->index(['is_featured', 'availability']);
        });

        Schema::create('menu_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->comment('e.g. Small, Medium, Large');
            $table->decimal('price', 10, 2);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('group_name', 100)->nullable()->comment('e.g. Extras, Sauce, Sides');
            $table->string('name', 100);
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_required')->default(false);
            $table->integer('max_selections')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_item_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->enum('media_type', ['image', 'video'])->default('image');
            $table->string('media_url');
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('business_branches')->nullOnDelete();
            $table->integer('quantity')->default(0);
            $table->boolean('track_inventory')->default(false);
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('notify_low_stock')->default(true);
            $table->timestamps();
        });

        Schema::create('special_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('offer_type', ['percentage', 'fixed', 'bogo', 'free_delivery', 'combo'])->default('percentage');
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('minimum_order', 10, 2)->nullable();
            $table->decimal('maximum_discount', 10, 2)->nullable();
            $table->json('applicable_items')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_offers');
        Schema::dropIfExists('inventory_status');
        Schema::dropIfExists('menu_item_media');
        Schema::dropIfExists('menu_item_addons');
        Schema::dropIfExists('menu_item_variants');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('menus');
    }
};
