<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->enum('post_type', ['photo', 'video', 'reel', 'recipe_share', 'review', 'check_in', 'promotion', 'event'])->default('photo');
            $table->text('caption')->nullable();
            $table->enum('visibility', ['public', 'followers', 'private'])->default('public');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('location_name')->nullable();
            $table->decimal('location_lat', 10, 8)->nullable();
            $table->decimal('location_lng', 11, 8)->nullable();
            $table->boolean('allow_comments')->default(true);
            $table->boolean('allow_sharing')->default(true);
            $table->enum('status', ['active', 'archived', 'removed'])->default('active');
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->integer('share_count')->default(0);
            $table->integer('save_count')->default(0);
            $table->integer('view_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_sponsored')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['visibility', 'status', 'created_at']);
            $table->index(['business_id', 'created_at']);
        });

        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->enum('media_type', ['image', 'video', 'reel'])->default('image');
            $table->string('media_url');
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration')->nullable()->comment('seconds for video/reel');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable()->comment('bytes');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('order_index')->default(0);
            $table->string('alt_text')->nullable();
            $table->timestamps();

            $table->index('post_id');
        });

        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction_type')->default('like');
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('image')->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('reply_count')->default(0);
            $table->enum('status', ['active', 'hidden', 'removed'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('comment_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('post_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('replied_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->integer('like_count')->default(0);
            $table->enum('status', ['active', 'hidden', 'removed'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('comment_id');
        });

        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('post_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
        });

        Schema::create('post_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('share_type', ['repost', 'story', 'external', 'message'])->default('repost');
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'user_id']);
        });

        Schema::create('saved_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('collection_name')->nullable()->default('All');
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
        });

        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->integer('post_count')->default(0);
            $table->boolean('is_trending')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->timestamps();
        });

        Schema::create('post_hashtags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hashtag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'hashtag_id']);
            $table->index('hashtag_id');
        });

        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->morphs('mentionable');
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentioned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('mentioned_user_id');
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('media_type', ['image', 'video', 'text'])->default('image');
            $table->string('media_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->text('text_content')->nullable();
            $table->string('background_color')->nullable();
            $table->json('stickers')->nullable();
            $table->json('links')->nullable();
            $table->enum('visibility', ['public', 'followers', 'close_friends'])->default('followers');
            $table->boolean('allow_replies')->default(true);
            $table->integer('view_count')->default(0);
            $table->integer('duration')->nullable()->comment('seconds');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction')->nullable();
            $table->text('reply_text')->nullable();
            $table->timestamps();

            $table->unique(['story_id', 'user_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index('type');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 50);
            $table->string('description')->nullable();
            $table->morphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('mentions');
        Schema::dropIfExists('post_hashtags');
        Schema::dropIfExists('hashtags');
        Schema::dropIfExists('saved_posts');
        Schema::dropIfExists('post_shares');
        Schema::dropIfExists('comment_likes');
        Schema::dropIfExists('comment_replies');
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
