<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\DeliveryProfile;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(protected DeliveryService $deliveryService) {}

    public function profile()
    {
        $profile = DeliveryProfile::with('user.profile','vehicles')->where('user_id',auth()->id())->first();
        return response()->json(['success'=>true,'data'=>$profile]);
    }

    public function updateLocation(Request $request)
    {
        $request->validate(['lat'=>'required|numeric','lng'=>'required|numeric']);
        $profile = DeliveryProfile::where('user_id',auth()->id())->firstOrFail();
        $this->deliveryService->updateLocation($profile,$request->lat,$request->lng,$request->speed);
        return response()->json(['success'=>true]);
    }

    public function toggleAvailability(Request $request)
    {
        $request->validate(['status'=>'required|in:online,offline,busy,on_break']);
        DeliveryProfile::where('user_id',auth()->id())->update(['availability_status'=>$request->status]);
        return response()->json(['success'=>true,'status'=>$request->status]);
    }

    public function activeDelivery()
    {
        $profile = DeliveryProfile::where('user_id',auth()->id())->firstOrFail();
        $delivery = Delivery::with(['order.items','order.business','order.customer.profile'])->where('driver_id',$profile->id)->whereIn('status',['assigned','accepted','picked_up','on_the_way'])->latest()->first();
        return response()->json(['success'=>true,'data'=>$delivery]);
    }

    public function accept(Delivery $delivery)
    {
        $profile = DeliveryProfile::where('user_id',auth()->id())->firstOrFail();
        $this->deliveryService->driverAccept($delivery,$profile);
        return response()->json(['success'=>true,'message'=>'Delivery accepted']);
    }

    public function confirmPickup(Delivery $delivery)
    {
        $profile = DeliveryProfile::where('user_id',auth()->id())->firstOrFail();
        $this->deliveryService->driverPickup($delivery,$profile);
        return response()->json(['success'=>true]);
    }

    public function confirmDelivery(Request $request, Delivery $delivery)
    {
        $profile = DeliveryProfile::where('user_id',auth()->id())->firstOrFail();
        $proof = $request->hasFile('proof') ? $request->file('proof')->store("deliveries/{$delivery->id}/proof",'public') : null;
        $this->deliveryService->driverDeliver($delivery,$profile,$proof);
        return response()->json(['success'=>true,'message'=>'Delivery confirmed']);
    }

    public function history(Request $request)
    {
        $profile = DeliveryProfile::where('user_id',auth()->id())->firstOrFail();
        $deliveries = Delivery::with('order.business')->where('driver_id',$profile->id)->where('status','delivered')->orderByDesc('delivered_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$deliveries]);
    }

    public function earnings(Request $request)
    {
        $profile = DeliveryProfile::where('user_id',auth()->id())->firstOrFail();
        $earnings = \App\Models\DriverEarning::where('driver_id',$profile->id)->when($request->period,function($q) use($request) {
            match($request->period) {
                'today'  => $q->whereDate('earned_at',today()),
                'week'   => $q->whereBetween('earned_at',[now()->startOfWeek(),now()->endOfWeek()]),
                'month'  => $q->whereMonth('earned_at',now()->month),
                default  => null,
            };
        })->orderByDesc('earned_at')->paginate(20);
        $total = $profile->total_earnings;
        $balance = $profile->balance;
        return response()->json(['success'=>true,'data'=>['earnings'=>$earnings,'total'=>$total,'balance'=>$balance]]);
    }
}
