<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WithdrawalRequest;
use App\Models\ReferralReward;
use App\Models\RewardPoint;
use App\Models\RewardPointTransaction;
use App\Models\CashbackLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    private array $pointsConfig = [
        'order'            => 10,  // points per order
        'post'             => 2,
        'recipe'           => 5,
        'review'           => 3,
        'daily_login'      => 1,
        'referral'         => 50,
        'profile_complete' => 20,
        'check_in'         => 3,
        'campaign'         => 0,   // variable
    ];

    private array $tierThresholds = [
        'bronze'   => 0,
        'silver'   => 500,
        'gold'     => 2000,
        'platinum' => 5000,
        'diamond'  => 15000,
    ];

    // ─── WALLET OPERATIONS ───────────────────────────────────────────────

    public function topup(User $user, float $amount, string $method, array $gatewayData = []): WalletTopup
    {
        $topup = WalletTopup::create([
            'user_id'        => $user->id,
            'amount'         => $amount,
            'payment_method' => $method,
            'gateway'        => $gatewayData['gateway'] ?? null,
            'status'         => 'pending',
        ]);

        return $topup;
    }

    public function confirmTopup(WalletTopup $topup, string $gatewayRef, array $response = []): void
    {
        DB::transaction(function () use ($topup, $gatewayRef, $response) {
            $topup->update([
                'status'             => 'success',
                'gateway_reference'  => $gatewayRef,
                'gateway_response'   => $response,
                'completed_at'       => now(),
            ]);

            $wallet = $topup->user->getOrCreateWallet();
            $wallet->credit($topup->amount, 'wallet_topup', "Wallet top-up via {$topup->payment_method}");
        });
    }

    public function payOrder(User $user, \App\Models\Order $order): void
    {
        DB::transaction(function () use ($user, $order) {
            $wallet = $user->wallet;
            if (!$wallet || !$wallet->canDebit($order->total)) {
                throw new \Exception('Insufficient wallet balance');
            }
            $wallet->debit($order->total, 'order_payment', "Payment for order #{$order->order_number}", 'Order', $order->id);
            $order->update(['payment_status' => 'paid', 'payment_method' => 'wallet']);
        });
    }

    public function refundOrder(User $user, \App\Models\Order $order, float $amount, string $reason = ''): void
    {
        DB::transaction(function () use ($user, $order, $amount, $reason) {
            $wallet = $user->getOrCreateWallet();
            $wallet->credit($amount, 'refund_credit', "Refund for order #{$order->order_number}: {$reason}", 'Order', $order->id);
        });
    }

    public function processReferral(int $referrerId, int $referredId): void
    {
        DB::transaction(function () use ($referrerId, $referredId) {
            $referrer = User::findOrFail($referrerId);
            $referred = User::findOrFail($referredId);

            $referrerBonus = (float) config('platform.referrer_bonus', 10.00);
            $referredBonus = (float) config('platform.referred_bonus', 5.00);

            ReferralReward::create([
                'referrer_id'    => $referrerId,
                'referred_id'    => $referredId,
                'referrer_bonus' => $referrerBonus,
                'referred_bonus' => $referredBonus,
                'points_bonus'   => $this->pointsConfig['referral'],
                'status'         => 'pending',
                'trigger_event'  => 'registration',
            ]);

            // Give referrer wallet bonus
            $referrer->getOrCreateWallet()->credit($referrerBonus, 'referral_reward', "Referral bonus for inviting @{$referred->username}");
            $referred->getOrCreateWallet()->credit($referredBonus, 'referral_reward', "Welcome bonus from referral");

            // Award points
            $this->awardPoints($referrer, $this->pointsConfig['referral'], 'referral', "Referral bonus", 'User', $referredId);

            ReferralReward::where('referrer_id', $referrerId)->where('referred_id', $referredId)->update(['status' => 'credited', 'credited_at' => now()]);
        });
    }

    public function awardCashback(\App\Models\Order $order): void
    {
        $cashbackRate = (float) config('platform.cashback_rate', 2.0);
        $cashbackAmount = round($order->subtotal * ($cashbackRate / 100), 2);

        if ($cashbackAmount <= 0) return;

        DB::transaction(function () use ($order, $cashbackAmount, $cashbackRate) {
            $log = CashbackLog::create([
                'user_id'             => $order->customer_id,
                'order_id'            => $order->id,
                'business_id'         => $order->business_id,
                'order_amount'        => $order->subtotal,
                'cashback_percentage' => $cashbackRate,
                'cashback_amount'     => $cashbackAmount,
                'status'              => 'pending',
            ]);

            // Credit after a short delay (via job)
            \App\Jobs\CreditCashbackJob::dispatch($log)->delay(now()->addMinutes(30));
        });
    }

    public function creditCashback(CashbackLog $log): void
    {
        DB::transaction(function () use ($log) {
            $user = User::findOrFail($log->user_id);
            $wallet = $user->getOrCreateWallet();
            $wallet->credit($log->cashback_amount, 'cashback_reward', "Cashback reward for order", 'CashbackLog', $log->id);
            $log->update(['status' => 'credited', 'credited_at' => now()]);
        });
    }

    // ─── REWARD POINTS ───────────────────────────────────────────────────

    public function awardPoints(User $user, int $points, string $source, string $description = '', ?string $model = null, ?int $modelId = null): void
    {
        DB::transaction(function () use ($user, $points, $source, $description, $model, $modelId) {
            $reward = $user->rewardPoints ?? RewardPoint::create(['user_id' => $user->id]);

            $before = $reward->available_points;
            $reward->increment('available_points', $points);
            $reward->increment('total_points', $points);
            $reward->increment('lifetime_points', $points);

            RewardPointTransaction::create([
                'user_id'        => $user->id,
                'type'           => 'earn',
                'points'         => $points,
                'source'         => $source,
                'description'    => $description,
                'related_model'  => $model,
                'related_id'     => $modelId,
                'points_before'  => $before,
                'points_after'   => $before + $points,
            ]);

            $this->checkTierUpgrade($user, $reward->fresh());
        });
    }

    public function redeemPoints(User $user, int $points, \App\Models\LoyaltyReward $reward): \App\Models\LoyaltyRedemption
    {
        DB::transaction(function () use ($user, $points, $reward) {
            $rp = $user->rewardPoints;
            if (!$rp || $rp->available_points < $points) {
                throw new \Exception('Insufficient reward points');
            }

            $before = $rp->available_points;
            $rp->decrement('available_points', $points);
            $rp->increment('redeemed_points', $points);

            RewardPointTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'redeem',
                'points'        => -$points,
                'source'        => 'redemption',
                'description'   => "Redeemed for: {$reward->name}",
                'related_model' => 'LoyaltyReward',
                'related_id'    => $reward->id,
                'points_before' => $before,
                'points_after'  => $before - $points,
            ]);

            return \App\Models\LoyaltyRedemption::create([
                'user_id'          => $user->id,
                'loyalty_reward_id'=> $reward->id,
                'points_used'      => $points,
                'redemption_code'  => strtoupper(Str::random(10)),
                'expires_at'       => now()->addDays(30),
            ]);
        });
    }

    public function awardOrderPoints(User $user, \App\Models\Order $order): void
    {
        $points = $this->pointsConfig['order'] + (int) floor($order->total / 10);
        $this->awardPoints($user, $points, 'order', "Points for order #{$order->order_number}", 'Order', $order->id);
    }

    private function checkTierUpgrade(User $user, RewardPoint $reward): void
    {
        $lifetime = $reward->lifetime_points;
        $newTier = 'bronze';

        foreach (array_reverse($this->tierThresholds, true) as $tier => $threshold) {
            if ($lifetime >= $threshold) {
                $newTier = $tier;
                break;
            }
        }

        if ($newTier !== $reward->tier) {
            $reward->update(['tier' => $newTier, 'tier_updated_at' => now()]);
            // Fire event for notification
            event(new \App\Events\TierUpgraded($user, $newTier));
        }
    }

    // ─── WITHDRAWAL ──────────────────────────────────────────────────────

    public function requestWithdrawal(User $user, float $amount, string $method, array $accountDetails): WithdrawalRequest
    {
        $wallet = $user->wallet;

        if (!$wallet || !$wallet->canDebit($amount)) {
            throw new \Exception('Insufficient balance for withdrawal');
        }

        if ($amount < 10) {
            throw new \Exception('Minimum withdrawal amount is GH₵10');
        }

        DB::transaction(function () use ($user, $wallet, $amount, $method, $accountDetails, &$request) {
            $wallet->debit($amount, 'withdrawal', "Withdrawal request via {$method}");
            $request = WithdrawalRequest::create([
                'user_id'            => $user->id,
                'amount'             => $amount,
                'withdrawal_method'  => $method,
                'account_details'    => $accountDetails,
                'status'             => 'pending',
            ]);
        });

        return $request;
    }
}
