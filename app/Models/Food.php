<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    protected $table = 'foods';

    protected $fillable = [
        'restaurant_id',
        'name',
        'description',
        'price',
        'image'
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    // 🔥 توحيد pivot مع Order
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_food')
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }
}