<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function cart(Request $request)
    {
        $cart = Cart::with(['items.menuItem.media','items.variant'])->where('user_id',auth()->id())->where('business_id',$request->business_id)->first();
        return response()->json(['success'=>true,'data'=>$cart]);
    }

    public function addToCart(Request $request)
    {
        $request->validate(['business_id'=>'required|exists:businesses,id','menu_item_id'=>'required|exists:menu_items,id','quantity'=>'nullable|integer|min:1']);
        $cart = $this->orderService->addToCart(auth()->user(), $request->all());
        return response()->json(['success'=>true,'data'=>$cart]);
    }

    public function updateCartItem(Request $request, \App\Models\CartItem $item)
    {
        $this->orderService->updateCartItem($item, $request->quantity ?? 0);
        return response()->json(['success'=>true,'message'=>'Cart updated']);
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['cart_id'=>'required|exists:carts,id','code'=>'required|string']);
        $cart = Cart::findOrFail($request->cart_id);
        if ($cart->user_id !== auth()->id()) return response()->json(['success'=>false,'message'=>'Unauthorized'],403);
        try {
            $result = $this->orderService->applyCoupon($cart,$request->code);
            return response()->json(['success'=>true,'data'=>$result]);
        } catch (\Exception $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],400); }
    }

    public function placeOrder(Request $request)
    {
        $request->validate(['cart_id'=>'required|exists:carts,id','payment_method'=>'required|in:wallet,card,mobile_money,cash_on_delivery','order_type'=>'nullable|in:delivery,pickup,dine_in','delivery_address'=>'nullable|array']);
        $cart = Cart::with('items')->findOrFail($request->cart_id);
        if ($cart->user_id !== auth()->id()) return response()->json(['success'=>false,'message'=>'Unauthorized'],403);
        try {
            $order = $this->orderService->placeOrder(auth()->user(),$cart,$request->all());
            return response()->json(['success'=>true,'message'=>'Order placed successfully','data'=>$order],201);
        } catch (\Exception $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],400); }
    }

    public function myOrders(Request $request)
    {
        $orders = Order::with(['business','items','delivery'])->where('customer_id',auth()->id())->when($request->status,fn($q)=>$q->where('order_status',$request->status))->orderByDesc('created_at')->paginate(15);
        return response()->json(['success'=>true,'data'=>$orders]);
    }

    public function show(string $uuid)
    {
        $order = Order::with(['business','items.menuItem','delivery.driver.user.profile','statusLogs'])->where('uuid',$uuid)->where('customer_id',auth()->id())->firstOrFail();
        return response()->json(['success'=>true,'data'=>$order]);
    }

    public function cancel(Request $request, Order $order)
    {
        $request->validate(['reason'=>'required|string']);
        try {
            $this->orderService->cancelOrder(auth()->user(),$order,$request->reason);
            return response()->json(['success'=>true,'message'=>'Order cancelled']);
        } catch (\Exception $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],400); }
    }

    public function track(string $uuid)
    {
        $order = Order::where('uuid',$uuid)->where('customer_id',auth()->id())->firstOrFail();
        $delivery = $order->delivery;
        if (!$delivery) return response()->json(['success'=>false,'message'=>'No delivery assigned'],404);
        $tracking = app(\App\Services\DeliveryService::class)->getTrackingData($delivery);
        return response()->json(['success'=>true,'data'=>$tracking]);
    }

    // Business-side order management
    public function businessOrders(Request $request, \App\Models\Business $business)
    {
        if ($business->owner_id !== auth()->id()) abort(403);
        $orders = Order::with(['customer.profile','items','delivery'])->where('business_id',$business->id)->when($request->status,fn($q)=>$q->where('order_status',$request->status))->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$orders]);
    }

    public function accept(Order $order)
    {
        if ($order->business->owner_id !== auth()->id()) abort(403);
        try { $this->orderService->acceptOrder($order); return response()->json(['success'=>true,'message'=>'Order accepted']); }
        catch (\Exception $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],400); }
    }

    public function reject(Request $request, Order $order)
    {
        if ($order->business->owner_id !== auth()->id()) abort(403);
        $request->validate(['reason'=>'required|string']);
        $this->orderService->rejectOrder($order,$request->reason);
        return response()->json(['success'=>true,'message'=>'Order rejected']);
    }

    public function markPreparing(Order $order) { if ($order->business->owner_id !== auth()->id()) abort(403); $this->orderService->markPreparing($order); return response()->json(['success'=>true]); }
    public function markReady(Order $order) { if ($order->business->owner_id !== auth()->id()) abort(403); $this->orderService->markReadyForPickup($order); return response()->json(['success'=>true]); }
}
