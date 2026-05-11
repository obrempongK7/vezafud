<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BlockedUser extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
}
