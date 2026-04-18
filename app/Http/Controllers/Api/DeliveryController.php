<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Get delivery orders for restaurant
     */
    public function index(Restaurant $restaurant)
    {
        $orders = $restaurant->orders()
            ->with(['foods', 'customer'])
            ->where('status', 'accepted')
            ->whereNotNull('delivery_status')
            ->latest()
            ->get();

        return response()->json([
            'restaurant' => $restaurant,
            'orders' => $orders
        ]);
    }

    /**
     * Update delivery status
     */
    public function updateStatus(Request $request, Restaurant $restaurant, Order $order)
    {
        $user = $request->user();

        if (!$user || ($user->role !== 'admin' && $user->id !== $restaurant->delivery_id)) {
            return response()->json([
                'message' => 'ليس لديك صلاحية'
            ], 403);
        }

        if ($order->restaurant_id !== $restaurant->id) {
            return response()->json([
                'message' => 'الطلب غير تابع لهذا المطعم'
            ], 404);
        }

        $request->validate([
            'delivery_status' => 'required|in:pending_delivery,on_the_way,delivered'
        ]);

        $order->update([
            'delivery_status' => $request->delivery_status
        ]);

        return response()->json([
            'message' => 'تم تحديث حالة التوصيل',
            'order' => $order
        ]);
    }

    /**
     * Delete delivered orders
     */
    public function deleteDelivered(Request $request)
    {
        $user = $request->user();

        // فقط الادمن
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'غير مصرح'
            ], 403);
        }

        $deleted = Order::where('delivery_status', 'delivered')->delete();

        return response()->json([
            'message' => 'تم حذف الطلبات الموصلة',
            'deleted_count' => $deleted
        ], 200);
    }
}