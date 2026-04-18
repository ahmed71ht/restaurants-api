<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'delivery_man_id', 'status', 'delivered_at'
    ];

    // الطلب
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // عامل التوصيل
    public function deliveryMan()
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }
}
