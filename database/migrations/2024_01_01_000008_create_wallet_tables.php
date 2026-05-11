<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('currency', 5)->default('GHS');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_frozen')->default(false);
            $table->decimal('daily_limit', 10, 2)->default(5000);
            $table->decimal('transaction_limit', 10, 2)->default(2000);
            $table->string('transaction_pin_hash')->nullable();
            $table->boolean('pin_enabled')->default(false);
            $table->integer('failed_pin_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 50)->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'credit', 'debit'
            ]);
            $table->enum('transaction_type', [
                'wallet_topup', 'order_payment', 'delivery_payment',
                'cashback_reward', 'referral_reward', 'promotional_bonus',
                'refund_credit', 'loyalty_reward', 'withdrawal',
                'transfer_in', 'transfer_out', 'adjustment'
            ]);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description')->nullable();
            $table->string('related_model')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('completed');
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['user_id', 'transaction_type']);
        });

        Schema::create('reward_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('total_points')->default(0);
            $table->integer('available_points')->default(0);
            $table->integer('redeemed_points')->default(0);
            $table->integer('expired_points')->default(0);
            $table->enum('tier', ['bronze', 'silver', 'gold', 'platinum', 'diamond'])->default('bronze');
            $table->integer('lifetime_points')->default(0);
            $table->timestamp('tier_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reward_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjust']);
            $table->integer('points');
            $table->enum('source', [
                'order', 'post', 'recipe', 'review', 'daily_login',
                'referral', 'profile_complete', 'check_in', 'campaign',
                'admin_adjustment', 'redemption'
            ]);
            $table->string('description')->nullable();
            $table->string('related_model')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->integer('points_before')->default(0);
            $table->integer('points_after')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('points_required');
            $table->enum('reward_type', ['discount', 'free_item', 'cashback', 'free_delivery', 'premium'])->default('discount');
            $table->decimal('reward_value', 10, 2)->nullable();
            $table->integer('quantity_available')->nullable();
            $table->integer('quantity_redeemed')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_reward_id')->constrained()->cascadeOnDelete();
            $table->integer('points_used');
            $table->string('redemption_code', 20)->unique();
            $table->boolean('is_used')->default(false);
            $table->foreignId('order_id')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cashback_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('order_amount', 10, 2);
            $table->decimal('cashback_percentage', 5, 2);
            $table->decimal('cashback_amount', 10, 2);
            $table->enum('status', ['pending', 'credited', 'expired'])->default('pending');
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('referrer_bonus', 10, 2)->default(0);
            $table->decimal('referred_bonus', 10, 2)->default(0);
            $table->integer('points_bonus')->default(0);
            $table->enum('status', ['pending', 'credited', 'cancelled'])->default('pending');
            $table->timestamp('credited_at')->nullable();
            $table->string('trigger_event')->nullable()->comment('first_order, registration');
            $table->timestamps();

            $table->unique('referred_id');
        });

        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 5)->default('GHS');
            $table->enum('payment_method', ['mobile_money', 'debit_card', 'credit_card', 'bank_transfer', 'gift_reward'])->default('mobile_money');
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_response')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('failure_reason')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('network_provider', 50)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('withdrawal_method', ['mobile_money', 'bank_transfer', 'card'])->default('mobile_money');
            $table->json('account_details')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('wallet_topups');
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('cashback_logs');
        Schema::dropIfExists('loyalty_redemptions');
        Schema::dropIfExists('loyalty_rewards');
        Schema::dropIfExists('reward_point_transactions');
        Schema::dropIfExists('reward_points');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
