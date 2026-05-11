<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('business_categories')->nullOnDelete();
            $table->enum('business_type', [
                'restaurant', 'bar', 'lounge', 'bakery', 'chef',
                'caterer', 'food_vendor', 'coffee_shop', 'juice_vendor',
                'food_truck', 'nightclub', 'hotel_restaurant', 'catering_company'
            ]);
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('tagline', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('cover_photo')->nullable();
            $table->json('gallery')->nullable();
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->enum('price_range', ['budget', 'moderate', 'expensive', 'luxury'])->nullable();
            $table->json('cuisine_types')->nullable();
            $table->json('features')->nullable();
            $table->json('payment_methods')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('delivery_available')->default(false);
            $table->boolean('pickup_available')->default(true);
            $table->boolean('dine_in_available')->default(true);
            $table->boolean('reservation_available')->default(false);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->integer('avg_delivery_time')->nullable()->comment('minutes');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('review_count')->default(0);
            $table->integer('follower_count')->default(0);
            $table->integer('order_count')->default(0);
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_type', 'is_active']);
            $table->index(['owner_id']);
            $table->index(['is_featured', 'rating_avg']);
        });

        Schema::create('business_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('branch_name', 200);
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone', 20)->nullable();
            $table->json('opening_hours')->nullable();
            $table->boolean('delivery_available')->default(true);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->integer('delivery_radius')->default(10)->comment('km');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main_branch')->default(false);
            $table->timestamps();

            $table->index('business_id');
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('business_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager', 'staff', 'dispatcher', 'cashier'])->default('staff');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week')->comment('0=Sun, 6=Sat');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_24_hours')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'day_of_week']);
        });

        Schema::create('business_gallery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->enum('media_type', ['image', 'video'])->default('image');
            $table->string('media_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('caption')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('business_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->tinyInteger('rating')->comment('1-5');
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->json('images')->nullable();
            $table->tinyInteger('food_rating')->nullable();
            $table->tinyInteger('service_rating')->nullable();
            $table->tinyInteger('delivery_rating')->nullable();
            $table->tinyInteger('ambiance_rating')->nullable();
            $table->text('business_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->enum('status', ['active', 'hidden', 'removed'])->default('active');
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status', 'created_at']);
            $table->unique(['business_id', 'user_id', 'order_id']);
        });

        Schema::create('business_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('business_verification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_registration_no')->nullable();
            $table->string('tax_id')->nullable();
            $table->json('documents')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_verification');
        Schema::dropIfExists('business_followers');
        Schema::dropIfExists('business_reviews');
        Schema::dropIfExists('business_gallery');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('business_staff');
        Schema::dropIfExists('business_branches');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('business_categories');
    }
};
