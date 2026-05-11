<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('username', 50)->unique();
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('password_hash')->nullable();
            $table->enum('account_type', [
                'customer', 'food_creator',
                'restaurant', 'bar', 'lounge', 'bakery',
                'chef', 'caterer', 'food_vendor', 'coffee_shop',
                'juice_vendor', 'food_truck',
                'delivery_rider', 'delivery_company',
                'super_admin', 'admin', 'accountant',
                'delivery_manager', 'restaurant_manager', 'moderator'
            ])->default('customer');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_email_verified')->default(false);
            $table->boolean('is_phone_verified')->default(false);
            $table->boolean('is_identity_verified')->default(false);
            $table->enum('status', ['active', 'suspended', 'banned', 'pending'])->default('active');
            $table->string('referral_code', 20)->unique()->nullable();
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->string('fcm_token')->nullable();
            $table->string('device_token')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('referred_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['account_type', 'status']);
            $table->index('last_active_at');
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->string('cover_photo')->nullable();
            $table->string('country', 5)->default('GH');
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('website')->nullable();
            $table->json('social_links')->nullable();
            $table->enum('privacy_type', ['public', 'private', 'followers_only'])->default('public');
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->json('food_preferences')->nullable();
            $table->json('dietary_restrictions')->nullable();
            $table->integer('follower_count')->default(0);
            $table->integer('following_count')->default(0);
            $table->integer('post_count')->default(0);
            $table->integer('recipe_count')->default(0);
            $table->timestamps();
        });

        Schema::create('user_privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('private_account')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->boolean('allow_direct_messages')->default(true);
            $table->boolean('show_stories_to_followers')->default(true);
            $table->boolean('show_location')->default(true);
            $table->boolean('show_email')->default(false);
            $table->boolean('show_phone')->default(false);
            $table->boolean('allow_tagging')->default(true);
            $table->boolean('allow_mentions')->default(true);
            $table->boolean('two_factor_enabled')->default(false);
            $table->json('two_factor_methods')->nullable();
            $table->timestamps();
        });

        Schema::create('followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted'])->default('accepted');
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
            $table->index('following_id');
        });

        Schema::create('blocked_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'blocked_user_id']);
        });

        Schema::create('muted_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('muted_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'muted_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muted_users');
        Schema::dropIfExists('blocked_users');
        Schema::dropIfExists('followers');
        Schema::dropIfExists('user_privacy_settings');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
    }
};
