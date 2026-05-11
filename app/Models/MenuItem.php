<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MenuItem extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function menu() { return $this->belongsTo(Menu::class); }
    public function media() { return $this->hasMany(MenuItemMedia::class); }
}
