<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessFollower;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $q = Business::with(['mainBranch','category'])->where('is_active',true);
        if ($request->type) $q->where('business_type',$request->type);
        if ($request->search) $q->where('name','like',"%{$request->search}%");
        if ($request->lat && $request->lng) $q->scopeNearby($request->lat,$request->lng,$request->radius ?? 10);
        if ($request->verified) $q->where('verification_status','verified');
        if ($request->open_now) $q->get()->filter(fn($b)=>$b->isOpenNow());
        $businesses = $q->orderByDesc('is_featured')->orderByDesc('rating_avg')->paginate($request->per_page ?? 20);
        return response()->json(['success'=>true,'data'=>$businesses]);
    }

    public function show(string $slug)
    {
        $business = Business::with(['branches','hours','gallery','category','owner.profile'])->where('slug',$slug)->firstOrFail();
        return response()->json(['success'=>true,'data'=>array_merge($business->toArray(),['is_open'=>$business->isOpenNow(),'is_following'=>auth()->check() ? $business->followers()->where('user_id',auth()->id())->exists() : false])]);
    }

    public function menu(Request $request, string $slug)
    {
        $business = Business::where('slug',$slug)->firstOrFail();
        $menus = $business->activeMenus()->with(['categories.items.media','categories.items.variants','categories.items.addons'])->get();
        return response()->json(['success'=>true,'data'=>$menus]);
    }

    public function reviews(Request $request, string $slug)
    {
        $business = Business::where('slug',$slug)->firstOrFail();
        $reviews = $business->reviews()->with('user.profile')->where('status','active')->latest()->paginate(15);
        $stats = ['avg'=>$business->rating_avg,'count'=>$business->review_count,'breakdown'=>$business->reviews()->selectRaw('rating,count(*) as count')->groupBy('rating')->pluck('count','rating')];
        return response()->json(['success'=>true,'data'=>['reviews'=>$reviews,'stats'=>$stats]]);
    }

    public function follow(string $slug)
    {
        $business = Business::where('slug',$slug)->firstOrFail();
        $user = auth()->user();
        $existing = $business->followers()->where('user_id',$user->id)->exists();
        if ($existing) { $business->followers()->detach($user->id); $business->decrement('follower_count'); return response()->json(['success'=>true,'following'=>false]); }
        $business->followers()->attach($user->id);
        $business->increment('follower_count');
        return response()->json(['success'=>true,'following'=>true]);
    }

    public function nearby(Request $request)
    {
        $request->validate(['lat'=>'required|numeric','lng'=>'required|numeric']);
        $businesses = Business::with('mainBranch')
            ->where('is_active',true)
            ->whereHas('branches',function($q) use ($request) {
                $q->selectRaw("*,(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",[$request->lat,$request->lng,$request->lat])
                  ->having('distance','<=',$request->radius ?? 10)->orderBy('distance');
            })->limit(30)->get();
        return response()->json(['success'=>true,'data'=>$businesses]);
    }

    public function store(Request $request)
    {
        $request->validate(['name'=>'required|string|max:200','business_type'=>'required','description'=>'nullable|string','phone'=>'nullable|string','email'=>'nullable|email']);
        $business = Business::create(array_merge($request->only(['name','business_type','description','phone','email','price_range','cuisine_types']),[
            'owner_id'=>auth()->id(),
            'slug'=>Str::slug($request->name).'-'.Str::random(6),
        ]));
        if ($request->hasFile('logo')) $business->update(['logo'=>$request->file('logo')->store("businesses/{$business->id}",'public')]);
        return response()->json(['success'=>true,'message'=>'Business registered','data'=>$business],201);
    }

    public function update(Request $request, Business $business)
    {
        if ($business->owner_id !== auth()->id()) return response()->json(['success'=>false,'message'=>'Unauthorized'],403);
        $business->update($request->only(['name','description','phone','email','price_range','cuisine_types','features','payment_methods','delivery_available','min_order_amount']));
        return response()->json(['success'=>true,'data'=>$business]);
    }
}
