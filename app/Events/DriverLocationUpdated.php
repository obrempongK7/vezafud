<?php
namespace App\Events;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    public function __construct(public int $deliveryId, public float $lat, public float $lng) {}
    public function broadcastOn(): array { return [new Channel("delivery.{$this->deliveryId}")]; }
    public function broadcastWith(): array { return ['lat' => $this->lat, 'lng' => $this->lng, 'timestamp' => now()->toIso8601String()]; }
}
