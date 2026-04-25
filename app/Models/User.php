<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'email_verified_at',
        'two_factor_enabled',
        'two_factor_enabled_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'two_factor_enabled' => 'boolean',
        'two_factor_enabled_at' => 'datetime',
    ];

    public function restaurants()
    {
        return $this->hasMany(Restaurant::class, 'owner_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    // خليتها بس إذا رح تستخدمها فعلاً
    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'delivery_man_id');
    }

    public function followedRestaurants()
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_followers');
    }
}