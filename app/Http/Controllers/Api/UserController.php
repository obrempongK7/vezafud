<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function __construct(protected NotificationService $notificationService) {}

    public function profile(string $username)
    {
        $user = User::with(['profile','rewardPoints'])->where('username',$username)->firstOrFail();
        $viewer = auth()->user();
        $isMe = $viewer?->id === $user->id;
        return response()->json(['success'=>true,'data'=>array_merge($user->profile?->toArray()??[],[
            'id'=>$user->id,'uuid'=>$user->uuid,'username'=>$user->username,'account_type'=>$user->account_type,
            'is_verified'=>$user->is_verified,'status'=>$user->status,
            'is_following'=>!$isMe && $viewer ? $viewer->isFollowing($user) : false,
            'is_followed_by'=>!$isMe && $viewer ? $user->isFollowing($viewer) : false,
            'tier'=>$user->rewardPoints?->tier??'bronze',
        ])]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate(['full_name'=>'nullable|string|max:100','bio'=>'nullable|string|max:500','city'=>'nullable|string','country'=>'nullable|string|max:5','website'=>'nullable|url','gender'=>'nullable|in:male,female,other,prefer_not','date_of_birth'=>'nullable|date']);
        auth()->user()->profile?->update($request->only(['full_name','bio','city','country','address','website','gender','date_of_birth','food_preferences','dietary_restrictions']));
        return response()->json(['success'=>true,'message'=>'Profile updated']);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar'=>'required|image|max:5120']);
        $path = $request->file('avatar')->store('avatars/'.auth()->id(),'public');
        auth()->user()->profile?->update(['avatar'=>$path]);
        return response()->json(['success'=>true,'avatar_url'=>asset('storage/'.$path)]);
    }

    public function follow(string $username)
    {
        $target = User::where('username',$username)->firstOrFail();
        $user = auth()->user();
        if ($target->id === $user->id) return response()->json(['success'=>false,'message'=>"Can't follow yourself"],400);
        $isFollowing = $user->isFollowing($target);
        if ($isFollowing) {
            $user->following()->detach($target->id);
            $user->profile?->decrement('following_count');
            $target->profile?->decrement('follower_count');
            return response()->json(['success'=>true,'following'=>false]);
        }
        $user->following()->attach($target->id);
        $user->profile?->increment('following_count');
        $target->profile?->increment('follower_count');
        $this->notificationService->notifyNewFollow($user,$target);
        return response()->json(['success'=>true,'following'=>true]);
    }

    public function followers(string $username)
    {
        $user = User::where('username',$username)->firstOrFail();
        $followers = $user->followers()->with('profile')->paginate(20);
        return response()->json(['success'=>true,'data'=>$followers]);
    }

    public function following(string $username)
    {
        $user = User::where('username',$username)->firstOrFail();
        $following = $user->following()->with('profile')->paginate(20);
        return response()->json(['success'=>true,'data'=>$following]);
    }

    public function posts(string $username, Request $request)
    {
        $user = User::where('username',$username)->firstOrFail();
        $posts = $user->posts()->with('media')->where('status','active')->when(!auth()->check()||auth()->id()!==$user->id,fn($q)=>$q->where('visibility','public'))->orderByDesc('created_at')->paginate(18);
        return response()->json(['success'=>true,'data'=>$posts]);
    }

    public function savedPosts()
    {
        $saved = auth()->user()->savedPosts ?? \App\Models\SavedPost::with(['post.media','post.user.profile'])->where('user_id',auth()->id())->latest()->paginate(18);
        return response()->json(['success'=>true,'data'=>$saved]);
    }

    public function notifications(Request $request)
    {
        $notifs = auth()->user()->notifications()->with('actor.profile')->when($request->unread_only,fn($q)=>$q->where('is_read',false))->orderByDesc('created_at')->paginate(20);
        $unreadCount = auth()->user()->notifications()->where('is_read',false)->count();
        return response()->json(['success'=>true,'data'=>['notifications'=>$notifs,'unread_count'=>$unreadCount]]);
    }

    public function markNotificationsRead()
    {
        $this->notificationService->markAllRead(auth()->user());
        return response()->json(['success'=>true]);
    }

    public function search(Request $request)
    {
        $q = $request->q;
        $users = User::with('profile')->where('username','like',"%{$q}%")->orWhereHas('profile',fn($pq)=>$pq->where('full_name','like',"%{$q}%"))->where('status','active')->limit(10)->get();
        $businesses = \App\Models\Business::where('name','like',"%{$q}%")->where('is_active',true)->limit(10)->get();
        $recipes = \App\Models\Recipe::where('title','like',"%{$q}%")->where('status','published')->limit(10)->get();
        return response()->json(['success'=>true,'data'=>['users'=>$users,'businesses'=>$businesses,'recipes'=>$recipes]]);
    }

    public function updatePrivacy(Request $request)
    {
        $request->validate(['private_account'=>'nullable|boolean','allow_comments'=>'nullable|boolean','allow_direct_messages'=>'nullable|boolean','show_stories_to_followers'=>'nullable|boolean']);
        auth()->user()->privacySettings?->update($request->only(['private_account','allow_comments','allow_direct_messages','show_stories_to_followers','show_location','allow_tagging','allow_mentions','two_factor_enabled']));
        return response()->json(['success'=>true]);
    }
}
