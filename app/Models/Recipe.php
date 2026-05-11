<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Recipe extends Model
{
    use SoftDeletes;
    protected $fillable = ['uuid','user_id','category_id','title','slug','description','cover_image','video_url','prep_time','cook_time','servings','difficulty','visibility','status','cuisine_tags','dietary_tags','calorie_count','is_featured'];
    protected $casts = ['cuisine_tags'=>'array','dietary_tags'=>'array','is_featured'=>'boolean'];
    protected static function boot() {
        parent::boot();
        static::creating(function($m) {
            $m->uuid = $m->uuid ?? Str::uuid();
            $m->slug = $m->slug ?? Str::slug($m->title).'-'.Str::random(6);
        });
    }
    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(RecipeCategory::class); }
    public function ingredients() { return $this->hasMany(RecipeIngredient::class)->orderBy('order_index'); }
    public function steps() { return $this->hasMany(RecipeStep::class)->orderBy('step_number'); }
    public function media() { return $this->hasMany(RecipeMedia::class)->orderBy('order_index'); }
    public function likes() { return $this->hasMany(RecipeLike::class); }
    public function comments() { return $this->hasMany(RecipeComment::class)->where('status','active'); }
    public function ratings() { return $this->hasMany(RecipeRating::class); }
    public function saves() { return $this->hasMany(SavedRecipe::class); }
    public function scopePublished($q) { return $q->where('status','published')->where('visibility','public'); }
    public function getTotalTimeAttribute() { return ($this->prep_time ?? 0) + ($this->cook_time ?? 0); }
    public function isLikedBy(User $user): bool { return $this->likes()->where('user_id',$user->id)->exists(); }
    public function isSavedBy(User $user): bool { return $this->saves()->where('user_id',$user->id)->exists(); }
}
