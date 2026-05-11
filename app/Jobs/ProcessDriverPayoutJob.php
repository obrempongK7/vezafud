<?php
namespace App\Jobs;
use App\Models\DeliveryProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDriverPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(private int $driverId) {}
    public function handle(): void
    {
        $driver = DeliveryProfile::find($this->driverId);
        if (!$driver || $driver->balance <= 0) return;
        // Actual payout via Paystack transfer or Mobile Money
        \Log::info("Processing payout of GH₵{$driver->balance} for driver #{$driver->id}");
    }
}
