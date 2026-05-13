<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Hashtag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostService
{
    public function createPost(array $data, $user, array $mediaFiles)
    {
        DB::beginTransaction();
        try {
            $post = Post::create([
                'uuid'         => Str::uuid(),
                'user_id'      => $user->id,
                'business_id'  => $data['business_id'] ?? null,
                'post_type'    => $data['post_type'],
                'caption'      => $data['caption'] ?? null,
                'visibility'   => $data['visibility'] ?? 'public',
                'location_name'=> $data['location_name'] ?? null,
                'location_lat' => $data['location_lat'] ?? null,
                'location_lng' => $data['location_lng'] ?? null,
            ]);

            $now = now();
            PostMedia::insert(
                collect($mediaFiles)->map(fn($file, $index) => [
                    'post_id'     => $post->id,
                    'media_type'  => str_contains($file->getMimeType(), 'video') ? 'video' : 'image',
                    'media_url'   => $file->store("posts/{$post->id}", 'public'),
                    'size'        => $file->getSize(),
                    'order_index' => $index,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ])->all()
            );

            if (isset($data['hashtags'])) {
                foreach ($data['hashtags'] as $tag) {
                    $hashtag = Hashtag::firstOrCreate(['name' => strtolower($tag)], ['slug' => Str::slug($tag)]);
                    $hashtag->increment('post_count');
                    $post->hashtags()->attach($hashtag->id);
                }
            }

            // Award points
            app(\App\Services\WalletService::class)->awardPoints($user, 2, 'post', 'Posted content');
            $user->profile?->increment('post_count');

            DB::commit();
            return $post;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
