<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->enum('vehicle_type', ['bicycle', 'motorbike', 'car', 'van', 'walking'])->default('motorbike');
            $table->string('license_number', 50)->nullable();
            $table->string('license_photo')->nullable();
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();
            $table->string('id_photo')->nullable();
            $table->string('profile_photo')->nullable();
            $table->enum('availability_status', ['online', 'offline', 'busy', 'on_break'])->default('offline');
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 11, 8)->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->integer('completed_deliveries')->default(0);
            $table->integer('cancelled_deliveries')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->timestamp('last_location_update')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('delivery_profiles')->cascadeOnDelete();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('year', 10)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('plate_number', 20);
            $table->enum('vehicle_type', ['bicycle', 'motorbike', 'car', 'van'])->default('motorbike');
            $table->string('registration_doc')->nullable();
            $table->string('insurance_doc')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->decimal('center_lat', 10, 8)->nullable();
            $table->decimal('center_lng', 11, 8)->nullable();
            $table->integer('radius')->nullable()->comment('km');
            $table->json('polygon_coordinates')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->integer('estimated_time')->nullable()->comment('minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tracking_code', 20)->unique();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('delivery_profiles')->nullOnDelete();
            $table->json('pickup_address')->nullable();
            $table->decimal('pickup_lat', 10, 8)->nullable();
            $table->decimal('pickup_lng', 11, 8)->nullable();
            $table->json('delivery_address')->nullable();
            $table->decimal('delivery_lat', 10, 8)->nullable();
            $table->decimal('delivery_lng', 11, 8)->nullable();
            $table->decimal('distance', 8, 2)->nullable()->comment('km');
            $table->integer('estimated_duration')->nullable()->comment('minutes');
            $table->integer('actual_duration')->nullable()->comment('minutes');
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('driver_earning', 10, 2)->default(0);
            $table->enum('status', [
                'pending', 'assigned', 'accepted', 'picked_up',
                'on_the_way', 'arrived', 'delivered', 'failed', 'cancelled'
            ])->default('pending');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('proof_of_delivery')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->tinyInteger('customer_rating')->nullable();
            $table->text('customer_review')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index('status');
        });

        Schema::create('delivery_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('delivery_profiles')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed', 5, 2)->nullable()->comment('km/h');
            $table->decimal('heading', 5, 2)->nullable()->comment('degrees');
            $table->string('address_snapshot')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['delivery_id', 'recorded_at']);
        });

        Schema::create('delivery_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->string('previous_status', 50)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('delivery_profiles')->cascadeOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('earning_type', ['delivery', 'tip', 'bonus', 'incentive', 'adjustment'])->default('delivery');
            $table->text('description')->nullable();
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->index(['driver_id', 'earned_at']);
        });

        Schema::create('driver_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('delivery_profiles')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_reviews');
        Schema::dropIfExists('driver_earnings');
        Schema::dropIfExists('delivery_status_logs');
        Schema::dropIfExists('delivery_tracking');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('delivery_vehicles');
        Schema::dropIfExists('delivery_profiles');
    }
};
