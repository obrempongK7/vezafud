<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\Notification;
use Illuminate\Support\Str;

class NotificationService
{
    public function send(User $user, string $type, string $title, string $body, array $data = [], ?int $actorId = null): Notification
    {
        $notif = Notification::create([
            'uuid'           => Str::uuid(),
            'user_id'        => $user->id,
            'actor_id'       => $actorId,
            'type'           => $type,
            'title'          => $title,
            'body'           => $body,
            'data'           => $data,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id'   => $data['reference_id'] ?? null,
            'action_url'     => $data['action_url'] ?? null,
        ]);

        // Broadcast via Reverb/WebSocket
        broadcast(new \App\Events\NotificationSent($user->id, $notif));

        // Push notification via FCM
        if ($user->fcm_token) {
            \App\Jobs\SendPushNotificationJob::dispatch($user->fcm_token, $title, $body, $data);
        }

        return $notif;
    }

    public function notifyNewOrder(Order $order): void
    {
        $business = $order->business;
        foreach ($business->staff as $staff) {
            $this->send($staff->user, 'new_order', 'New Order Received!',
                "New order #{$order->order_number} — {$order->currency}{$order->total}",
                ['reference_type' => 'Order', 'reference_id' => $order->id, 'action_url' => "/dashboard/orders/{$order->uuid}"]
            );
        }
    }

    public function notifyOrderAccepted(Order $order): void
    {
        $this->send($order->customer, 'order_accepted', 'Order Accepted! 🎉',
            "Your order from {$order->business->name} has been accepted and is being prepared",
            ['reference_type' => 'Order', 'reference_id' => $order->id, 'action_url' => "/orders/{$order->uuid}"]
        );
    }

    public function notifyOrderPreparing(Order $order): void
    {
        $this->send($order->customer, 'order_preparing', 'Your order is being prepared 👨‍🍳',
            "{$order->business->name} is preparing your food",
            ['reference_type' => 'Order', 'reference_id' => $order->id]
        );
    }

    public function notifyReadyForPickup(Order $order): void
    {
        $this->send($order->customer, 'order_ready', 'Order Ready for Pickup!',
            "Your order from {$order->business->name} is ready",
            ['reference_type' => 'Order', 'reference_id' => $order->id]
        );
    }

    public function notifyOrderStatus(Order $order, string $message): void
    {
        $this->send($order->customer, 'order_update', 'Order Update 🛍️', $message,
            ['reference_type' => 'Order', 'reference_id' => $order->id, 'action_url' => "/orders/{$order->uuid}/track"]
        );
    }

    public function notifyOrderCancelled(Order $order, string $reason = ''): void
    {
        $this->send($order->customer, 'order_cancelled', 'Order Cancelled',
            "Your order #{$order->order_number} was cancelled. {$reason}",
            ['reference_type' => 'Order', 'reference_id' => $order->id]
        );
    }

    public function notifyOrderDelivered(Order $order): void
    {
        $this->send($order->customer, 'order_delivered', 'Order Delivered! ✅',
            "Your order from {$order->business->name} has been delivered. Enjoy your meal! 🍽️",
            ['reference_type' => 'Order', 'reference_id' => $order->id, 'action_url' => "/orders/{$order->uuid}/review"]
        );
    }

    public function notifyDriverAssigned(Delivery $delivery): void
    {
        $driver = $delivery->driver;
        if (!$driver) return;
        $this->send($driver->user, 'new_delivery', 'New Delivery Request!',
            "Pickup from {$delivery->order->business->name}",
            ['reference_type' => 'Delivery', 'reference_id' => $delivery->id, 'action_url' => "/delivery/{$delivery->uuid}"]
        );
        $this->send($delivery->order->customer, 'driver_assigned', 'Driver Assigned! 🏍️',
            "A driver has been assigned to your order",
            ['reference_type' => 'Order', 'reference_id' => $delivery->order_id, 'action_url' => "/orders/{$delivery->order->uuid}/track"]
        );
    }

    public function notifyNewFollow(User $follower, User $followed): void
    {
        $this->send($followed, 'new_follow', 'New Follower!',
            "@{$follower->username} started following you",
            ['reference_type' => 'User', 'reference_id' => $follower->id, 'action_url' => "/@{$follower->username}"],
            $follower->id
        );
    }

    public function notifyPostLike(User $liker, \App\Models\Post $post): void
    {
        if ($liker->id === $post->user_id) return;
        $this->send($post->user, 'post_like', 'Someone liked your post ❤️',
            "@{$liker->username} liked your post",
            ['reference_type' => 'Post', 'reference_id' => $post->id, 'action_url' => "/posts/{$post->uuid}"],
            $liker->id
        );
    }

    public function notifyPostComment(User $commenter, \App\Models\Post $post, string $comment): void
    {
        if ($commenter->id === $post->user_id) return;
        $this->send($post->user, 'post_comment', 'New comment on your post 💬',
            "@{$commenter->username}: " . Str::limit($comment, 80),
            ['reference_type' => 'Post', 'reference_id' => $post->id, 'action_url' => "/posts/{$post->uuid}"],
            $commenter->id
        );
    }

    public function notifyTierUpgrade(User $user, string $tier): void
    {
        $this->send($user, 'tier_upgrade', "You've reached {$tier} tier! 🏆",
            "Congratulations! You've been upgraded to " . ucfirst($tier) . " membership",
            ['action_url' => '/wallet/rewards']
        );
    }

    public function markAllRead(User $user): void
    {
        $user->notifications()->where('is_read', false)->update(['is_read' => true]);
    }
}
