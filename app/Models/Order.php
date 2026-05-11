<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use SoftDeletes;
    protected $fillable = ['uuid','order_number','customer_id','business_id','branch_id','coupon_id','order_type','subtotal','delivery_fee','tax','discount','platform_fee','total','payment_method','payment_status','payment_reference','order_status','delivery_address','delivery_lat','delivery_lng','special_instructions','scheduled_at','commission_amount','rider_earning','business_earning'];
    protected $casts = ['delivery_address'=>'array','scheduled_at'=>'datetime','accepted_at'=>'datetime','prepared_at'=>'datetime','picked_up_at'=>'datetime','delivered_at'=>'datetime','cancelled_at'=>'datetime'];
    protected static function boot() {
        parent::boot();
        static::creating(function($m) {
            $m->uuid = $m->uuid ?? Str::uuid();
            $m->order_number = $m->order_number ?? 'VZF-'.strtoupper(Str::random(8));
        });
    }
    public function customer() { return $this->belongsTo(User::class,'customer_id'); }
    public function business() { return $this->belongsTo(Business::class); }
    public function branch() { return $this->belongsTo(BusinessBranch::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function delivery() { return $this->hasOne(Delivery::class); }
    public function statusLogs() { return $this->hasMany(OrderStatusLog::class)->orderByDesc('created_at'); }
    public function refundRequests() { return $this->hasMany(RefundRequest::class); }
    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function review() { return $this->hasOne(BusinessReview::class); }
    public function updateStatus(string $status, ?string $notes = null, ?int $changedBy = null): void {
        $prev = $this->order_status;
        $this->update(['order_status' => $status]);
        $this->statusLogs()->create(['status'=>$status,'previous_status'=>$prev,'notes'=>$notes,'changed_by'=>$changedBy]);
        $ts = match($status) {
            'accepted' => ['accepted_at' => now()],
            'preparing' => [],
            'picked_up' => ['picked_up_at' => now()],
            'delivered' => ['delivered_at' => now()],
            'cancelled' => ['cancelled_at' => now()],
            default => [],
        };
        if ($ts) $this->update($ts);
    }
    public function calculateCommission(): void {
        $rate = config('platform.commission_rate', 10) / 100;
        $this->update(['commission_amount' => $this->subtotal * $rate, 'business_earning' => $this->subtotal * (1 - $rate)]);
    }
}
