<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostLike;
use App\Models\PostComment;
use App\Models\SavedPost;
use App\Models\Hashtag;
use App\Services\FeedService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function __construct(protected FeedService $feedService, protected NotificationService $notificationService) {}

    public function feed(Request $request)
    {
        $posts = $this->feedService->getHomeFeed(auth()->user(), $request->page ?? 1);
        return response()->json(['success'=>true,'data'=>['posts'=>$posts->items(),'meta'=>['current_page'=>$posts->currentPage(),'last_page'=>$posts->lastPage(),'total'=>$posts->total()]]]);
    }

    public function explore(Request $request)
    {
        $posts = $this->feedService->getExploreFeed(auth()->user(), $request->page ?? 1);
        return response()->json(['success'=>true,'data'=>['posts'=>$posts->items()]]);
    }

    public function reels(Request $request)
    {
        $posts = $this->feedService->getReelsFeed(auth()->user(), $request->page ?? 1);
        return response()->json(['success'=>true,'data'=>['reels'=>$posts->items()]]);
    }

    public function stories()
    {
        $stories = $this->feedService->getActiveStories(auth()->user());
        return response()->json(['success'=>true,'data'=>$stories]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'post_type'   => 'required|in:photo,video,reel,recipe_share,review,check_in,promotion,event',
            'caption'     => 'nullable|string|max:2200',
            'visibility'  => 'nullable|in:public,followers,private',
            'media'       => 'required|array|min:1',
            'media.*'     => 'file|mimes:jpg,jpeg,png,gif,mp4,mov|max:102400',
            'business_id' => 'nullable|exists:businesses,id',
            'location_name'=>'nullable|string',
            'location_lat'=> 'nullable|numeric',
            'location_lng'=> 'nullable|numeric',
            'hashtags'    => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $post = Post::create([
                'uuid'         => Str::uuid(),
                'user_id'      => auth()->id(),
                'business_id'  => $request->business_id,
                'post_type'    => $request->post_type,
                'caption'      => $request->caption,
                'visibility'   => $request->visibility ?? 'public',
                'location_name'=> $request->location_name,
                'location_lat' => $request->location_lat,
                'location_lng' => $request->location_lng,
            ]);

            $now = now();
            PostMedia::insert(
                collect($request->file('media'))->map(fn($file, $index) => [
                    'post_id'     => $post->id,
                    'media_type'  => str_contains($file->getMimeType(), 'video') ? 'video' : 'image',
                    'media_url'   => $file->store("posts/{$post->id}", 'public'),
                    'size'        => $file->getSize(),
                    'order_index' => $index,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ])->all()
            );

            if ($request->hashtags) {
                foreach ($request->hashtags as $tag) {
                    $hashtag = Hashtag::firstOrCreate(['name' => strtolower($tag)], ['slug' => Str::slug($tag)]);
                    $hashtag->increment('post_count');
                    $post->hashtags()->attach($hashtag->id);
                }
            }

            // Award points
            app(\App\Services\WalletService::class)->awardPoints(auth()->user(), 2, 'post', 'Posted content');
            auth()->user()->profile?->increment('post_count');

            DB::commit();
            return response()->json(['success'=>true,'message'=>'Post created','data'=>$this->feedService->formatPost($post->load(['user.profile','media','hashtags']), auth()->user())], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()], 500);
        }
    }

    public function show(string $uuid)
    {
        $post = Post::with(['user.profile','media','hashtags'])->where('uuid',$uuid)->firstOrFail();
        return response()->json(['success'=>true,'data'=>$this->feedService->formatPost($post, auth()->user())]);
    }

    public function like(Post $post)
    {
        $user = auth()->user();
        $liked = PostLike::where('post_id',$post->id)->where('user_id',$user->id)->exists();
        if ($liked) {
            PostLike::where('post_id',$post->id)->where('user_id',$user->id)->delete();
            $post->decrement('like_count');
            return response()->json(['success'=>true,'liked'=>false,'like_count'=>$post->like_count]);
        }
        PostLike::create(['post_id'=>$post->id,'user_id'=>$user->id]);
        $post->increment('like_count');
        $this->notificationService->notifyPostLike($user, $post);
        return response()->json(['success'=>true,'liked'=>true,'like_count'=>$post->like_count]);
    }

    public function comment(Request $request, Post $post)
    {
        $request->validate(['body'=>'required|string|max:1000']);
        if (!$post->allow_comments) return response()->json(['success'=>false,'message'=>'Comments are disabled'],403);
        $comment = $post->comments()->create(['user_id'=>auth()->id(),'body'=>$request->body]);
        $post->increment('comment_count');
        $this->notificationService->notifyPostComment(auth()->user(), $post, $request->body);
        return response()->json(['success'=>true,'data'=>$comment->load('user.profile')], 201);
    }

    public function save(Post $post)
    {
        $user = auth()->user();
        $saved = SavedPost::where('user_id',$user->id)->where('post_id',$post->id)->first();
        if ($saved) { $saved->delete(); $post->decrement('save_count'); return response()->json(['success'=>true,'saved'=>false]); }
        SavedPost::create(['user_id'=>$user->id,'post_id'=>$post->id]);
        $post->increment('save_count');
        return response()->json(['success'=>true,'saved'=>true]);
    }

    public function destroy(Post $post)
    {
        if ($post->user_id !== auth()->id()) return response()->json(['success'=>false,'message'=>'Unauthorized'],403);
        $post->delete();
        auth()->user()->profile?->decrement('post_count');
        return response()->json(['success'=>true,'message'=>'Post deleted']);
    }
}
