<?php
namespace App\Jobs;
use App\Models\Hashtag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class UpdateTrendingHashtagsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    public function handle(): void
    {
        // Find hashtags with most posts in the last 48 hours
        $trending = \DB::table('post_hashtags')
            ->join('hashtags', 'post_hashtags.hashtag_id', 'hashtags.id')
            ->where('post_hashtags.created_at', '>=', now()->subHours(48))
            ->selectRaw('hashtag_id, COUNT(*) as recent_count')
            ->groupBy('hashtag_id')
            ->orderByDesc('recent_count')
            ->limit(20)
            ->pluck('recent_count', 'hashtag_id');

        Hashtag::query()->update(['is_trending' => false]);
        if ($trending->isNotEmpty()) {
            Hashtag::whereIn('id', $trending->keys())->update(['is_trending' => true]);
        }

        Cache::forget('trending_hashtags');
    }
}
