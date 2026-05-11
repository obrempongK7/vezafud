<?php

namespace App\Services;

use App\Models\User;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class FeedService
{
    private int $cacheTtl = 300; // 5 minutes

    public function getHomeFeed(User $user, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $followingIds = Cache::remember("user:{$user->id}:following", 600, fn() =>
            $user->following()->pluck('following_id')->toArray()
        );
        $businessIds = Cache::remember("user:{$user->id}:businesses", 600, fn() =>
            $user->followedBusinesses()->pluck('business_id')->toArray()
        );

        return Post::with(['user.profile', 'media', 'likes' => fn($q) => $q->where('user_id', $user->id), 'saves' => fn($q) => $q->where('user_id', $user->id)])
            ->where('status', 'active')
            ->where(function ($q) use ($user, $followingIds, $businessIds) {
                $q->where('visibility', 'public')
                  ->orWhere(function ($sq) use ($user, $followingIds) {
                      $sq->where('visibility', 'followers')->whereIn('user_id', $followingIds);
                  })
                  ->orWhere('user_id', $user->id);
            })
            ->where(function ($q) use ($followingIds, $businessIds, $user) {
                $q->whereIn('user_id', array_merge($followingIds, [$user->id]))
                  ->orWhereIn('business_id', $businessIds)
                  ->orWhere('is_featured', true);
            })
            ->withCount(['likes', 'comments'])
            ->orderByRaw("
                CASE
                    WHEN user_id IN (" . (empty($followingIds) ? '0' : implode(',', $followingIds)) . ") THEN 3
                    WHEN is_featured = 1 THEN 2
                    ELSE 1
                END DESC,
                created_at DESC
            ")
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getExploreFeed(User $user, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $blockedIds = $user->blockedUsers()->pluck('blocked_user_id')->toArray();

        return Post::with(['user.profile', 'media'])
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->whereNotIn('user_id', array_merge($blockedIds, [$user->id]))
            ->orderByRaw('(like_count * 1 + comment_count * 2 + share_count * 1.5 + view_count * 0.1) DESC, created_at DESC')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getReelsFeed(User $user, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        return Post::with(['user.profile', 'media'])
            ->where('post_type', 'reel')
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->orderByRaw('(view_count * 0.1 + like_count * 1.5 + share_count * 2) DESC, created_at DESC')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getTrendingHashtags(int $limit = 10): array
    {
        return Cache::remember('trending_hashtags', 1800, fn() =>
            \App\Models\Hashtag::where('is_banned', false)
                ->orderByDesc('post_count')
                ->limit($limit)
                ->get(['id', 'name', 'slug', 'post_count'])
                ->toArray()
        );
    }

    public function getActiveStories(User $user): array
    {
        $followingIds = $user->following()->pluck('following_id')->toArray();

        return \App\Models\Story::with('user.profile')
            ->where('expires_at', '>', now())
            ->whereNotNull('media_url')
            ->where(function ($q) use ($user, $followingIds) {
                $q->whereIn('user_id', array_merge($followingIds, [$user->id]))
                  ->orWhere('visibility', 'public');
            })
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn($stories) => [
                'user'    => $stories->first()->user->only(['id', 'uuid', 'username']) + ['avatar' => $stories->first()->user->profile?->avatar],
                'stories' => $stories->map(fn($s) => [
                    'id'        => $s->id,
                    'media_url' => $s->media_url,
                    'media_type'=> $s->media_type,
                    'duration'  => $s->duration ?? 5,
                    'expires_at'=> $s->expires_at,
                    'viewed'    => $s->views()->where('user_id', $user->id)->exists(),
                ]),
                'has_unviewed' => $stories->contains(fn($s) => !$s->views()->where('user_id', $user->id)->exists()),
            ])
            ->values()
            ->toArray();
    }

    public function getSuggestedUsers(User $user, int $limit = 10): array
    {
        $followingIds = $user->following()->pluck('following_id')->toArray();
        $followingIds[] = $user->id;

        return User::with('profile')
            ->whereNotIn('id', $followingIds)
            ->where('status', 'active')
            ->where('is_verified', true)
            ->whereIn('account_type', ['food_creator', 'chef', 'customer'])
            ->withCount('followers as follower_count_raw')
            ->orderByDesc('follower_count_raw')
            ->limit($limit)
            ->get()
            ->map(fn($u) => [
                'id'             => $u->id,
                'username'       => $u->username,
                'full_name'      => $u->profile?->full_name,
                'avatar'         => $u->profile?->avatar,
                'account_type'   => $u->account_type,
                'follower_count' => $u->profile?->follower_count ?? 0,
                'is_verified'    => $u->is_verified,
            ])
            ->toArray();
    }

    public function formatPost(Post $post, ?User $viewer = null): array
    {
        return [
            'id'           => $post->id,
            'uuid'         => $post->uuid,
            'post_type'    => $post->post_type,
            'caption'      => $post->caption,
            'visibility'   => $post->visibility,
            'location'     => $post->location_name ? ['name' => $post->location_name, 'lat' => $post->location_lat, 'lng' => $post->location_lng] : null,
            'media'        => $post->media->map(fn($m) => [
                'id'        => $m->id,
                'type'      => $m->media_type,
                'url'       => asset('storage/' . $m->media_url),
                'thumbnail' => $m->thumbnail_url ? asset('storage/' . $m->thumbnail_url) : null,
                'duration'  => $m->duration,
            ]),
            'hashtags'     => $post->hashtags->pluck('name'),
            'like_count'   => $post->like_count,
            'comment_count'=> $post->comment_count,
            'share_count'  => $post->share_count,
            'save_count'   => $post->save_count,
            'view_count'   => $post->view_count,
            'is_liked'     => $viewer ? $post->isLikedBy($viewer) : false,
            'is_saved'     => $viewer ? $post->isSavedBy($viewer) : false,
            'allow_comments' => $post->allow_comments,
            'user'         => [
                'id'         => $post->user->id,
                'uuid'       => $post->user->uuid,
                'username'   => $post->user->username,
                'full_name'  => $post->user->profile?->full_name,
                'avatar'     => $post->user->profile?->avatar ? asset('storage/' . $post->user->profile->avatar) : null,
                'is_verified'=> $post->user->is_verified,
            ],
            'business'     => $post->business ? ['id' => $post->business->id, 'name' => $post->business->name, 'slug' => $post->business->slug] : null,
            'created_at'   => $post->created_at->diffForHumans(),
            'created_at_iso' => $post->created_at->toIso8601String(),
        ];
    }
}
