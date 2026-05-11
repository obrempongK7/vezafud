<?php
namespace App\Providers;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\TierUpgraded::class => [
            \App\Listeners\SendTierUpgradeNotification::class,
        ],
        \App\Events\OrderStatusChanged::class => [
            \App\Listeners\UpdateOrderAnalytics::class,
        ],
    ];
}
