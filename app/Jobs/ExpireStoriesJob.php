<?php
namespace App\Jobs;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ExpireStoriesJob implements ShouldQueue
{
    use Dispatchable, Queueable;
    public function handle(): void
    {
        Story::where('expires_at', '<', now())->whereNull('deleted_at')->delete();
        \Log::info('Expired stories cleaned up at ' . now());
    }
}
