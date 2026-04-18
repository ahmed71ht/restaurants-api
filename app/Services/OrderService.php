<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;

class OrderService
{
    public function accept(Restaurant $restaurant, Order $order)
    {
        if ($order->status === 'accepted') {
            throw new \Exception('الطلب مقبول مسبقاً');
        }

        if ($order->restaurant_id !== $restaurant->id) {
            throw new \Exception('الطلب غير تابع لهذا المطعم');
        }

        $order->update(['status' => 'accepted']);
        $restaurant->increment('orders_count');

        $order->load('foods');

        foreach ($order->foods as $food) {
            $food->increment('buyers_count');
        }

        return $order;
    }

    public function reject(Restaurant $restaurant, Order $order)
    {
        if ($order->restaurant_id !== $restaurant->id) {
            throw new \Exception('الطلب غير تابع لهذا المطعم');
        }

        $order->update(['status' => 'rejected']);

        return $order;
    }
}