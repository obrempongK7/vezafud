<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid','owner_id','category_id','business_type','name','slug','description',
        'tagline','phone','email','website','logo','cover_photo','gallery',
        'verification_status','price_range','cuisine_types','features','payment_methods',
        'is_active','is_featured','delivery_available','pickup_available','dine_in_available',
        'reservation_available','min_order_amount','avg_delivery_time','rating_avg',
        'review_count','follower_count','order_count',
    ];

    protected $casts = [
        'gallery' => 'array', 'cuisine_types' => 'array', 'features' => 'array',
        'payment_methods' => 'array', 'is_active' => 'boolean', 'is_featured' => 'boolean',
        'delivery_available' => 'boolean', 'pickup_available' => 'boolean',
        'dine_in_available' => 'boolean', 'reservation_available' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? Str::uuid();
            $model->slug = $model->slug ?? Str::slug($model->name) . '-' . Str::random(6);
        });
    }

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function category() { return $this->belongsTo(BusinessCategory::class); }
    public function branches() { return $this->hasMany(BusinessBranch::class); }
    public function mainBranch() { return $this->hasOne(BusinessBranch::class)->where('is_main_branch', true); }
    public function staff() { return $this->hasMany(BusinessStaff::class); }
    public function hours() { return $this->hasMany(BusinessHour::class); }
    public function gallery() { return $this->hasMany(BusinessGallery::class); }
    public function reviews() { return $this->hasMany(BusinessReview::class); }
    public function followers() { return $this->belongsToMany(User::class, 'business_followers')->withTimestamps(); }
    public function verification() { return $this->hasOne(BusinessVerification::class); }
    public function menus() { return $this->hasMany(Menu::class); }
    public function activeMenus() { return $this->hasMany(Menu::class)->where('is_active', true); }
    public function orders() { return $this->hasMany(Order::class); }
    public function specialOffers() { return $this->hasMany(SpecialOffer::class)->where('is_active', true); }
    public function analytics() { return $this->hasMany(BusinessAnalytic::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeVerified($q) { return $q->where('verification_status', 'verified'); }
    public function scopeFeatured($q) { return $q->where('is_featured', true); }
    public function scopeNearby($q, $lat, $lng, $radius = 10)
    {
        return $q->whereHas('branches', function($b) use ($lat, $lng, $radius) {
            $b->selectRaw("*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
              ->having('distance', '<=', $radius)
              ->orderBy('distance');
        });
    }

    public function isOpenNow(): bool
    {
        $dayOfWeek = now()->dayOfWeek;
        $currentTime = now()->format('H:i:s');
        $hours = $this->hours->firstWhere('day_of_week', $dayOfWeek);
        if (!$hours || $hours->is_closed) return false;
        if ($hours->is_24_hours) return true;
        return $currentTime >= $hours->opens_at && $currentTime <= $hours->closes_at;
    }
}
