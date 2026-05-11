<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wallet extends Model
{
    protected $fillable = ['user_id','balance','currency','is_active','is_frozen','daily_limit','transaction_limit','transaction_pin_hash','pin_enabled'];
    protected $casts = ['is_active'=>'boolean','is_frozen'=>'boolean','pin_enabled'=>'boolean'];
    protected $hidden = ['transaction_pin_hash'];
    public function user() { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(WalletTransaction::class)->orderByDesc('created_at'); }
    public function canDebit(float $amount): bool { return !$this->is_frozen && $this->is_active && $this->balance >= $amount; }
    public function credit(float $amount, string $type, string $description = '', $relatedModel = null, $relatedId = null): WalletTransaction {
        $before = $this->balance;
        $this->increment('balance', $amount);
        return $this->transactions()->create([
            'uuid' => Str::uuid(), 'reference' => 'WTX-'.strtoupper(Str::random(12)),
            'user_id' => $this->user_id, 'type' => 'credit', 'transaction_type' => $type,
            'amount' => $amount, 'balance_before' => $before, 'balance_after' => $before + $amount,
            'description' => $description, 'related_model' => $relatedModel, 'related_id' => $relatedId,
            'status' => 'completed', 'completed_at' => now(),
        ]);
    }
    public function debit(float $amount, string $type, string $description = '', $relatedModel = null, $relatedId = null): WalletTransaction {
        if (!$this->canDebit($amount)) throw new \Exception('Insufficient wallet balance');
        $before = $this->balance;
        $this->decrement('balance', $amount);
        return $this->transactions()->create([
            'uuid' => Str::uuid(), 'reference' => 'WTX-'.strtoupper(Str::random(12)),
            'user_id' => $this->user_id, 'type' => 'debit', 'transaction_type' => $type,
            'amount' => $amount, 'balance_before' => $before, 'balance_after' => $before - $amount,
            'description' => $description, 'related_model' => $relatedModel, 'related_id' => $relatedId,
            'status' => 'completed', 'completed_at' => now(),
        ]);
    }
}
