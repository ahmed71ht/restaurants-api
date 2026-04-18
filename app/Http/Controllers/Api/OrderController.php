<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use App\Services\OrderService;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Get restaurant orders
     */
    public function orders(Restaurant $restaurant)
    {
        $this->authorize('manage', $restaurant);

        $orders = $restaurant->orders()
            ->with(['foods', 'customer'])
            ->latest()
            ->get();

        return response()->json([
            'restaurant' => $restaurant,
            'orders' => $orders
        ]);
    }

    /**
     * Accept order
     */
    public function acceptOrder(Restaurant $restaurant, Order $order)
    {
        $this->authorize('manage', $restaurant);

        try {
            $order = $this->orderService->accept($restaurant, $order);

            return response()->json([
                'message' => 'تم قبول الطلب',
                'order' => $order
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reject order
     */
    public function rejectOrder(Restaurant $restaurant, Order $order)
    {
        $this->authorize('manage', $restaurant);

        try {
            $order = $this->orderService->reject($restaurant, $order);

            return response()->json([
                'message' => 'تم رفض الطلب',
                'order' => $order
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete rejected orders (ADMIN ONLY)
     */
    public function deleteRejected()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'غير مصرح'
            ], 403);
        }

        $deleted = Order::where('status', 'rejected')->delete();

        return response()->json([
            'message' => 'تم حذف الطلبات المرفوضة',
            'deleted_count' => $deleted
        ]);
    }
}