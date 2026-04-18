<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'delivery_id',
        'name',
        'description',
        'image',
        'phone',
        'location',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // 🔥 كان ناقص
    public function deliveryMan()
    {
        return $this->belongsTo(User::class, 'delivery_id');
    }

    public function foods()
    {
        return $this->hasMany(Food::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function comments()
    {
        return $this->hasMany(RestaurantComment::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'restaurant_followers');
    }
}