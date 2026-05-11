<?php
namespace App\Listeners;
use App\Events\OrderStatusChanged;

class UpdateOrderAnalytics
{
    public function handle(OrderStatusChanged $event): void
    {
        if ($event->status === 'delivered') {
            \App\Models\BusinessAnalytic::updateOrCreate(
                ['business_id' => $event->order->business_id, 'date' => today()],
                []
            )->increment('order_count');
            \App\Models\BusinessAnalytic::where('business_id',$event->order->business_id)
                ->whereDate('date',today())
                ->increment('revenue', $event->order->subtotal);
        }
    }
}
