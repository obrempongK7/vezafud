<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Business;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected WalletService $walletService,
        protected DeliveryService $deliveryService,
        protected NotificationService $notificationService,
    ) {}

    // ─── CART ────────────────────────────────────────────────────────────

    public function getOrCreateCart(User $user, int $businessId): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->id, 'business_id' => $businessId],
            ['uuid' => Str::uuid()]
        );
    }

    public function addToCart(User $user, array $data): Cart
    {
        $cart = $this->getOrCreateCart($user, $data['business_id']);

        $existing = $cart->items()->where('menu_item_id', $data['menu_item_id'])
            ->where('variant_id', $data['variant_id'] ?? null)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $data['quantity'] ?? 1);
        } else {
            $menuItem = \App\Models\MenuItem::findOrFail($data['menu_item_id']);
            $price = $data['variant_id']
                ? \App\Models\MenuItemVariant::find($data['variant_id'])?->price ?? $menuItem->price
                : ($menuItem->discount_price ?? $menuItem->price);

            $cart->items()->create([
                'menu_item_id'   => $data['menu_item_id'],
                'variant_id'     => $data['variant_id'] ?? null,
                'quantity'       => $data['quantity'] ?? 1,
                'unit_price'     => $price,
                'selected_addons'=> $data['addons'] ?? null,
                'instructions'   => $data['instructions'] ?? null,
            ]);
        }

        return $cart->load('items.menuItem');
    }

    public function updateCartItem(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->update(['quantity' => $quantity]);
        }
    }

    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->delete();
    }

    public function applyCoupon(Cart $cart, string $code): array
    {
        $coupon = Coupon::where('code', strtoupper($code))
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q) => $q->whereNull('business_id')->orWhere('business_id', $cart->business_id))
            ->first();

        if (!$coupon) throw new \Exception('Invalid or expired coupon code');

        $subtotal = $cart->items->sum(fn($i) => $i->unit_price * $i->quantity);

        if ($coupon->minimum_order && $subtotal < $coupon->minimum_order) {
            throw new \Exception("Minimum order of " . config('platform.currency_symbol') . $coupon->minimum_order . " required");
        }

        $usageCount = CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $cart->user_id)->count();
        if ($usageCount >= $coupon->per_user_limit) {
            throw new \Exception('You have already used this coupon');
        }

        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            throw new \Exception('This coupon has reached its usage limit');
        }

        $discount = match ($coupon->discount_type) {
            'percentage'    => min($subtotal * ($coupon->discount_value / 100), $coupon->maximum_discount ?? PHP_FLOAT_MAX),
            'fixed'         => min($coupon->discount_value, $subtotal),
            'free_delivery' => 0, // handled at checkout
            default         => 0,
        };

        $cart->update(['promo_code' => $code, 'discount_amount' => $discount]);

        return ['coupon' => $coupon, 'discount' => $discount, 'type' => $coupon->discount_type];
    }

    // ─── ORDER PLACEMENT ─────────────────────────────────────────────────

    public function placeOrder(User $user, Cart $cart, array $data): Order
    {
        DB::beginTransaction();
        try {
            $items = $cart->items()->with('menuItem')->get();
            if ($items->isEmpty()) throw new \Exception('Cart is empty');

            $business = Business::findOrFail($cart->business_id);
            $subtotal = $items->sum(fn($i) => $i->unit_price * $i->quantity);
            $deliveryFee = $this->calculateDeliveryFee($data, $business);
            $tax = round($subtotal * (config('platform.tax_rate', 0) / 100), 2);
            $discount = $cart->discount_amount ?? 0;

            // Free delivery coupon
            $coupon = $cart->promo_code ? Coupon::where('code', $cart->promo_code)->first() : null;
            if ($coupon?->discount_type === 'free_delivery') { $deliveryFee = 0; }

            $total = max(0, $subtotal + $deliveryFee + $tax - $discount);
            $platformFee = round($total * (config('platform.commission_rate', 10) / 100), 2);

            $order = Order::create([
                'customer_id'        => $user->id,
                'business_id'        => $cart->business_id,
                'branch_id'          => $cart->branch_id,
                'coupon_id'          => $coupon?->id,
                'order_type'         => $data['order_type'] ?? 'delivery',
                'subtotal'           => $subtotal,
                'delivery_fee'       => $deliveryFee,
                'tax'                => $tax,
                'discount'           => $discount,
                'platform_fee'       => $platformFee,
                'total'              => $total,
                'payment_method'     => $data['payment_method'],
                'delivery_address'   => $data['delivery_address'] ?? null,
                'delivery_lat'       => $data['delivery_lat'] ?? null,
                'delivery_lng'       => $data['delivery_lng'] ?? null,
                'special_instructions'=> $data['special_instructions'] ?? null,
                'scheduled_at'       => $data['scheduled_at'] ?? null,
                'commission_amount'  => $platformFee,
                'business_earning'   => $subtotal - $platformFee,
            ]);

            // Create order items
            foreach ($items as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'menu_item_id'  => $item->menu_item_id,
                    'item_name'     => $item->menuItem->name,
                    'variant_id'    => $item->variant_id,
                    'variant_name'  => $item->variant?->name,
                    'quantity'      => $item->quantity,
                    'unit_price'    => $item->unit_price,
                    'total_price'   => $item->unit_price * $item->quantity,
                    'addons'        => $item->selected_addons,
                    'instructions'  => $item->instructions,
                ]);
            }

            // Process payment
            $this->processPayment($user, $order, $data['payment_method']);

            // Log coupon usage
            if ($coupon) {
                CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_applied' => $discount]);
                $coupon->increment('usage_count');
            }

            // Clear cart
            $this->clearCart($cart);

            // Notify business
            $this->notificationService->notifyNewOrder($order);

            // Award points
            $this->walletService->awardOrderPoints($user, $order);

            // Award cashback
            $this->walletService->awardCashback($order);

            DB::commit();
            return $order->load(['items', 'business', 'delivery']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function acceptOrder(Order $order): void
    {
        if ($order->order_status !== 'pending') throw new \Exception('Order cannot be accepted');
        $order->updateStatus('accepted');
        $this->notificationService->notifyOrderAccepted($order);
        $this->deliveryService->assignDriver($order);
    }

    public function rejectOrder(Order $order, string $reason): void
    {
        $order->updateStatus('cancelled', $reason);
        // Refund
        if ($order->payment_status === 'paid') {
            $this->walletService->refundOrder($order->customer, $order, $order->total, $reason);
        }
        $this->notificationService->notifyOrderCancelled($order, $reason);
    }

    public function markPreparing(Order $order): void
    {
        $order->updateStatus('preparing');
        $this->notificationService->notifyOrderPreparing($order);
    }

    public function markReadyForPickup(Order $order): void
    {
        $order->updateStatus('ready_for_pickup');
        $this->notificationService->notifyReadyForPickup($order);
    }

    public function cancelOrder(User $user, Order $order, string $reason): void
    {
        if (!in_array($order->order_status, ['pending', 'accepted'])) {
            throw new \Exception('Order cannot be cancelled at this stage');
        }
        $order->updateStatus('cancelled', $reason);
        $order->update(['cancellation_reason' => $reason]);
        if ($order->payment_status === 'paid') {
            $this->walletService->refundOrder($user, $order, $order->total, "Cancelled: {$reason}");
        }
        $this->notificationService->notifyOrderCancelled($order, $reason);
    }

    private function processPayment(User $user, Order $order, string $method): void
    {
        if ($method === 'wallet') {
            $this->walletService->payOrder($user, $order);
        }
        // card/mobile_money payments are handled via payment gateway webhooks
    }

    private function calculateDeliveryFee(array $data, Business $business): float
    {
        if (($data['order_type'] ?? 'delivery') !== 'delivery') return 0;
        return (float) ($business->mainBranch?->delivery_fee ?? config('platform.default_delivery_fee', 8));
    }
}
