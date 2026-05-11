<?php
namespace App\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    public function __construct(public \App\Models\Order $order, public string $status) {}
    public function broadcastOn(): array { return [new PrivateChannel("orders.{$this->order->id}")]; }
    public function broadcastWith(): array { return ['order_id' => $this->order->id, 'status' => $this->status, 'updated_at' => now()->toIso8601String()]; }
}
