<?php
namespace App\Listeners;
use App\Events\TierUpgraded;
use App\Services\NotificationService;

class SendTierUpgradeNotification
{
    public function __construct(private NotificationService $ns) {}
    public function handle(TierUpgraded $event): void
    {
        $this->ns->notifyTierUpgrade($event->user, $event->tier);
    }
}
