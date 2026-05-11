<?php
namespace App\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;
    public function __construct(public int $userId, public \App\Models\Notification $notification) {}
    public function broadcastOn(): array { return [new PrivateChannel("notifications.{$this->userId}")]; }
    public function broadcastWith(): array { return ['notification' => $this->notification]; }
}
