<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'uuid', 'username', 'email', 'phone', 'password_hash',
        'account_type', 'is_verified', 'is_email_verified', 'is_phone_verified',
        'status', 'referral_code', 'referred_by', 'fcm_token', 'last_active_at',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_email_verified' => 'boolean',
        'is_phone_verified' => 'boolean',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? Str::uuid();
            $model->referral_code = $model->referral_code ?? Str::upper(Str::random(8));
        });
    }

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return ['uuid' => $this->uuid, 'type' => $this->account_type]; }

    // Profile
    public function profile() { return $this->hasOne(UserProfile::class); }
    public function privacySettings() { return $this->hasOne(UserPrivacySetting::class); }

    // Social
    public function posts() { return $this->hasMany(Post::class); }
    public function stories() { return $this->hasMany(Story::class); }
    public function recipes() { return $this->hasMany(Recipe::class); }

    // Following system
    public function following()
    {
        return $this->belongsToMany(User::class, 'followers', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'followers', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function blockedUsers()
    {
        return $this->belongsToMany(User::class, 'blocked_users', 'user_id', 'blocked_user_id')
            ->withTimestamps();
    }

    // Business
    public function businesses() { return $this->hasMany(Business::class, 'owner_id'); }
    public function staffOf() { return $this->hasMany(BusinessStaff::class); }
    public function followedBusinesses()
    {
        return $this->belongsToMany(Business::class, 'business_followers')->withTimestamps();
    }

    // Orders
    public function orders() { return $this->hasMany(Order::class, 'customer_id'); }

    // Delivery
    public function deliveryProfile() { return $this->hasOne(DeliveryProfile::class); }

    // Wallet
    public function wallet() { return $this->hasOne(Wallet::class); }
    public function rewardPoints() { return $this->hasOne(RewardPoint::class); }

    // Notifications
    public function notifications() { return $this->hasMany(Notification::class); }

    // Messaging
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')->withPivot('role', 'last_read_at');
    }

    // Devices
    public function devices() { return $this->hasMany(Device::class); }

    // Helpers
    public function isFollowing(User $user): bool
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function isFollowedBy(User $user): bool
    {
        return $this->followers()->where('follower_id', $user->id)->exists();
    }

    public function hasBlocked(User $user): bool
    {
        return $this->blockedUsers()->where('blocked_user_id', $user->id)->exists();
    }

    public function isBusinessAccount(): bool
    {
        return in_array($this->account_type, [
            'restaurant', 'bar', 'lounge', 'bakery', 'chef',
            'caterer', 'food_vendor', 'coffee_shop', 'juice_vendor', 'food_truck',
        ]);
    }

    public function isDeliveryAccount(): bool
    {
        return in_array($this->account_type, ['delivery_rider', 'delivery_company']);
    }

    public function isAdminAccount(): bool
    {
        return in_array($this->account_type, [
            'super_admin', 'admin', 'accountant',
            'delivery_manager', 'restaurant_manager', 'moderator',
        ]);
    }

    public function getOrCreateWallet(): Wallet
    {
        return $this->wallet ?? Wallet::create(['user_id' => $this->id]);
    }

    public function scopeActive($query) { return $query->where('status', 'active'); }
    public function scopeVerified($query) { return $query->where('is_verified', true); }
}
