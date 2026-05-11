<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private string $fcmToken,
        private string $title,
        private string $body,
        private array  $data = []
    ) {}

    public function handle(): void
    {
        $serverKey = config('services.fcm.server_key');
        if (!$serverKey) return;

        try {
            Http::withHeaders([
                'Authorization' => "key={$serverKey}",
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to'           => $this->fcmToken,
                'notification' => ['title' => $this->title, 'body' => $this->body, 'sound' => 'default'],
                'data'         => $this->data,
                'priority'     => 'high',
            ]);
        } catch (\Throwable $e) {
            Log::warning("FCM push failed: " . $e->getMessage());
        }
    }
}
