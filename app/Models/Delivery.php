<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Delivery extends Model
{
    protected $fillable = ['uuid','tracking_code','order_id','driver_id','pickup_address','pickup_lat','pickup_lng','delivery_address','delivery_lat','delivery_lng','distance','estimated_duration','actual_duration','delivery_fee','driver_earning','status'];
    protected $casts = ['pickup_address'=>'array','delivery_address'=>'array'];
    protected static function boot() {
        parent::boot();
        static::creating(function($m) {
            $m->uuid = $m->uuid ?? Str::uuid();
            $m->tracking_code = $m->tracking_code ?? 'TRK'.strtoupper(Str::random(8));
        });
    }
    public function order() { return $this->belongsTo(Order::class); }
    public function driver() { return $this->belongsTo(DeliveryProfile::class,'driver_id'); }
    public function trackingPoints() { return $this->hasMany(DeliveryTracking::class)->orderByDesc('recorded_at'); }
    public function statusLogs() { return $this->hasMany(DeliveryStatusLog::class); }
    public function latestLocation() { return $this->hasOne(DeliveryTracking::class)->latestOfMany(); }
}
