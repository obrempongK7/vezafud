<?php
namespace App\Jobs;
use App\Models\CashbackLog;
use App\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreditCashbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(private CashbackLog $log) {}
    public function handle(WalletService $wallet): void
    {
        if ($this->log->status === 'pending') $wallet->creditCashback($this->log);
    }
}
