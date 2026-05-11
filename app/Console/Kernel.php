<?php
namespace App\Console;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Expire 24-hour stories
        $schedule->job(new \App\Jobs\ExpireStoriesJob)->hourly();
        // Update trending hashtags every 30 mins
        $schedule->job(new \App\Jobs\UpdateTrendingHashtagsJob)->everyThirtyMinutes();
        // Auto-complete orders after 2 hours of delivery
        $schedule->call(function () {
            \App\Models\Order::where('order_status','delivered')
                ->where('delivered_at','<',now()->subHours(2))
                ->where('is_rated_by_customer',false)
                ->each(function ($order) {
                    \App\Models\Notification::create(['uuid'=>\Illuminate\Support\Str::uuid(),'user_id'=>$order->customer_id,'type'=>'rate_order','title'=>'Rate your order','body'=>"How was your order from {$order->business->name}?",'reference_type'=>'Order','reference_id'=>$order->id]);
                });
        })->everyTwoHours();
        // Expire reward points older than 1 year
        $schedule->call(function () {
            \App\Models\RewardPointTransaction::where('expires_at','<',now())->where('type','earn')->each(function($t) {
                $rp = \App\Models\RewardPoint::where('user_id',$t->user_id)->first();
                if ($rp && $rp->available_points > 0) {
                    $expire = min($t->points, $rp->available_points);
                    $rp->decrement('available_points',$expire);
                    $rp->increment('expired_points',$expire);
                }
                $t->update(['type'=>'expire']);
            });
        })->daily();
        // Clear old OTPs
        $schedule->call(fn() => \App\Models\OtpVerification::where('expires_at','<',now())->delete())->hourly();
        // Clear old login attempts
        $schedule->call(fn() => \App\Models\LoginAttempt::where('created_at','<',now()->subDays(30))->delete())->weekly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
