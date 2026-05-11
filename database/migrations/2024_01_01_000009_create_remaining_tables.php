<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RECIPES
        Schema::create('recipe_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('recipe_categories')->nullOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('video_url')->nullable();
            $table->integer('prep_time')->nullable()->comment('minutes');
            $table->integer('cook_time')->nullable()->comment('minutes');
            $table->integer('total_time')->storedAs('prep_time + cook_time');
            $table->integer('servings')->default(2);
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'expert'])->default('medium');
            $table->enum('visibility', ['public', 'followers', 'private'])->default('public');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->json('cuisine_tags')->nullable();
            $table->json('dietary_tags')->nullable();
            $table->integer('calorie_count')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->integer('like_count')->default(0);
            $table->integer('save_count')->default(0);
            $table->integer('view_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('quantity', 50)->nullable();
            $table->string('unit', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->integer('group_number')->default(1)->comment('For ingredient groups');
            $table->string('group_name')->nullable();
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('recipe_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->integer('step_number');
            $table->string('title', 200)->nullable();
            $table->text('instructions');
            $table->string('image')->nullable();
            $table->string('video_url')->nullable();
            $table->integer('timer_minutes')->nullable();
            $table->string('tip')->nullable();
            $table->timestamps();

            $table->index(['recipe_id', 'step_number']);
        });

        Schema::create('recipe_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->enum('media_type', ['image', 'video'])->default('image');
            $table->string('media_url');
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        Schema::create('saved_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'recipe_id']);
        });

        Schema::create('recipe_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'recipe_id']);
        });

        Schema::create('recipe_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('recipe_comments')->nullOnDelete();
            $table->text('body');
            $table->integer('like_count')->default(0);
            $table->enum('status', ['active', 'hidden', 'removed'])->default('active');
            $table->timestamps();
        });

        Schema::create('recipe_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('review')->nullable();
            $table->timestamps();
            $table->unique(['recipe_id', 'user_id']);
        });

        // RESERVATIONS
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('business_branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('guest_count')->default(1);
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->text('special_requests')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'no_show', 'completed'])->default('pending');
            $table->string('table_number')->nullable();
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('event_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_name', 200);
            $table->date('event_date');
            $table->time('event_time');
            $table->integer('guest_count')->default(1);
            $table->text('event_description')->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->enum('status', ['inquiry', 'quoted', 'confirmed', 'completed', 'cancelled'])->default('inquiry');
            $table->timestamps();
        });

        Schema::create('chef_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chef_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 100)->nullable();
            $table->date('booking_date');
            $table->time('booking_time');
            $table->integer('guest_count')->default(1);
            $table->string('location', 300)->nullable();
            $table->json('menu_preferences')->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->enum('status', ['pending', 'accepted', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        Schema::create('catering_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caterer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 100)->nullable();
            $table->date('event_date');
            $table->string('venue', 300)->nullable();
            $table->integer('guest_count')->default(1);
            $table->text('requirements')->nullable();
            $table->json('package_details')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->enum('status', ['new', 'responded', 'negotiating', 'confirmed', 'completed', 'cancelled'])->default('new');
            $table->timestamps();
        });

        // MESSAGING
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['direct', 'group', 'business', 'order', 'delivery'])->default('direct');
            $table->string('name', 100)->nullable();
            $table->string('avatar')->nullable();
            $table->foreignId('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->foreignId('order_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->boolean('is_muted')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'file', 'location', 'order', 'system'])->default('text');
            $table->text('body')->nullable();
            $table->json('media')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['message_id', 'user_id']);
        });

        // LOCATIONS
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 100)->nullable();
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 5)->default('GH');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('place_id')->nullable()->comment('Google Places ID');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
        });

        // ANALYTICS
        Schema::create('user_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('post_views')->default(0);
            $table->integer('profile_visits')->default(0);
            $table->integer('new_followers')->default(0);
            $table->integer('recipe_views')->default(0);
            $table->integer('engagement_score')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('business_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('order_count')->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->integer('new_customers')->default(0);
            $table->decimal('avg_order_value', 10, 2)->default(0);
            $table->integer('profile_views')->default(0);
            $table->integer('new_followers')->default(0);
            $table->integer('menu_views')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'date']);
        });

        Schema::create('order_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_commissions', 12, 2)->default(0);
            $table->integer('cancelled_orders')->default(0);
            $table->decimal('avg_order_value', 10, 2)->default(0);
            $table->integer('delivery_count')->default(0);
            $table->timestamps();

            $table->unique('date');
        });

        Schema::create('engagement_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('source')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'event_type']);
            $table->index(['user_id', 'event_type']);
        });

        // MODERATION & SECURITY
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable');
            $table->string('reason', 100);
            $table->text('description')->nullable();
            $table->json('evidence')->nullable();
            $table->enum('status', ['pending', 'reviewing', 'resolved', 'dismissed'])->default('pending');
            $table->enum('action_taken', ['none', 'warning', 'content_removed', 'account_suspended', 'account_banned'])->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('target');
            $table->string('action', 100);
            $table->text('reason')->nullable();
            $table->json('previous_state')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('flag_type', 100);
            $table->string('description')->nullable();
            $table->json('data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved', 'dismissed'])->default('open');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->timestamps();
        });

        Schema::create('banned_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reason', 300);
            $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('banned_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->timestamps();
        });

        // PLATFORM SETTINGS
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string');
            $table->string('group', 50)->default('general');
            $table->string('label', 200)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('spin_wheel_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->enum('reward_type', ['points', 'cashback', 'coupon', 'free_delivery', 'discount', 'no_win'])->default('points');
            $table->decimal('reward_value', 10, 2)->nullable();
            $table->integer('points_value')->nullable();
            $table->string('coupon_code')->nullable();
            $table->integer('probability_weight')->default(10);
            $table->string('color', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('spin_wheel_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->nullable()->constrained('spin_wheel_rewards')->nullOnDelete();
            $table->boolean('won')->default(false);
            $table->decimal('reward_given', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $tables = [
            'spin_wheel_attempts', 'spin_wheel_rewards', 'platform_settings',
            'banned_accounts', 'fraud_flags', 'audit_logs', 'moderation_logs', 'reports',
            'engagement_analytics', 'order_analytics', 'business_analytics', 'user_analytics',
            'locations', 'message_reads', 'messages', 'conversation_participants', 'conversations',
            'catering_requests', 'chef_bookings', 'event_bookings', 'reservations',
            'recipe_ratings', 'recipe_comments', 'recipe_likes', 'saved_recipes',
            'recipe_media', 'recipe_steps', 'recipe_ingredients', 'recipes', 'recipe_categories',
        ];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
