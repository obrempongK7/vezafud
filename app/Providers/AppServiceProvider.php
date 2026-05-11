<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\OtpService::class);
        $this->app->singleton(\App\Services\WalletService::class);
        $this->app->singleton(\App\Services\FeedService::class);
        $this->app->singleton(\App\Services\NotificationService::class);
        $this->app->singleton(\App\Services\DeliveryService::class);
        $this->app->singleton(\App\Services\OrderService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('api', fn(Request $r) => Limit::perMinute(60)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('auth', fn(Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('otp', fn(Request $r) => Limit::perMinute(3)->by($r->ip()));
    }
}
