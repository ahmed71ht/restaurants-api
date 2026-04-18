<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'restaurant_id',
        'status',
        'delivery_status',
        'total_price',
        'location',
        'phone',
    ];

    // الزبون
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    // المطعم
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    // الأصناف الموجودة بالطلب
    public function foods()
    {
        return $this->belongsToMany(Food::class, 'order_food')
                    ->withPivot('quantity', 'price');
    }

    // التوصيل المرتبط بالطلب
    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

}
