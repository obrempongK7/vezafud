<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Menu extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function business() { return $this->belongsTo(Business::class); }
    public function items() { return $this->hasMany(MenuItem::class); }
}
