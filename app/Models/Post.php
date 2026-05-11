<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use SoftDeletes;
    protected $fillable = ['uuid','user_id','business_id','post_type','caption','visibility','location_id','location_name','location_lat','location_lng','allow_comments','allow_sharing','status','is_featured','is_sponsored','tags'];
    protected $casts = ['tags'=>'array','allow_comments'=>'boolean','allow_sharing'=>'boolean','is_featured'=>'boolean'];
    protected static function boot() {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid());
    }
    public function user() { return $this->belongsTo(User::class); }
    public function business() { return $this->belongsTo(Business::class); }
    public function media() { return $this->hasMany(PostMedia::class)->orderBy('order_index'); }
    public function likes() { return $this->hasMany(PostLike::class); }
    public function comments() { return $this->hasMany(PostComment::class)->where('status','active'); }
    public function saves() { return $this->hasMany(SavedPost::class); }
    public function hashtags() { return $this->belongsToMany(Hashtag::class,'post_hashtags'); }
    public function isLikedBy(User $user): bool { return $this->likes()->where('user_id',$user->id)->exists(); }
    public function isSavedBy(User $user): bool { return $this->saves()->where('user_id',$user->id)->exists(); }
    public function scopePublic($q) { return $q->where('visibility','public')->where('status','active'); }
    public function scopeFeed($q,$user) {
        $following = $user->following()->pluck('following_id');
        return $q->where('status','active')->where(function($sq) use($user,$following) {
            $sq->where('visibility','public')
               ->orWhere(function($sq2) use($user,$following) {
                   $sq2->where('visibility','followers')->whereIn('user_id',$following);
               })
               ->orWhere('user_id',$user->id);
        })->orderByDesc('created_at');
    }
}
