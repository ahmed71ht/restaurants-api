<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\Food;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserOrdersController extends Controller
{
    /**
     * Get user orders for restaurant
     */
    public function userOrders(Restaurant $restaurant, Request $request)
    {
        $orders = $restaurant->orders()
            ->where('customer_id', $request->user()->id)
            ->with('foods', 'customer')
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders
        ]);
    }

    /**
     * Show order
     */
    public function edit(Restaurant $restaurant, Order $order, Request $request)
    {
        if ($order->customer_id != $request->user()->id) {
            return response()->json([
                'message' => 'غير مسموح'
            ], 403);
        }

        $order->load(['foods' => function ($q) {
            $q->withPivot('id', 'quantity', 'price');
        }]);

        return response()->json([
            'order' => $order
        ]);
    }

    /**
     * Update order (items + quantity)
     */
    public function update(Request $request, Restaurant $restaurant, Order $order)
    {
        if (
            $order->customer_id != $request->user()->id ||
            $order->status === 'accepted'
        ) {
            return response()->json([
                'message' => 'لا يمكنك تعديل هذا الطلب'
            ], 403);
        }

        $request->validate([
            'order_food_id' => 'required|array',
            'food_id' => 'required|array',
            'quantity' => 'required|array',
        ]);

        if (
            count($request->order_food_id) !== count($request->food_id) ||
            count($request->food_id) !== count($request->quantity)
        ) {
            return response()->json([
                'message' => 'البيانات غير متطابقة'
            ], 422);
        }

        DB::beginTransaction();

        try {

            foreach ($request->order_food_id as $index => $pivot_id) {

                $old = DB::table('order_food')->where('id', $pivot_id)->first();

                if (!$old) continue;

                DB::table('order_food')->where('id', $pivot_id)->delete();

                $newFood = Food::findOrFail($request->food_id[$index]);

                $order->foods()->attach($newFood->id, [
                    'quantity' => $request->quantity[$index],
                    'price' => $newFood->price,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'order' => $order->load('foods')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'حدث خطأ'
            ], 500);
        }
    }

    /**
     * Delete order
     */
    public function delete(Order $order, Request $request)
    {
        if (
            $order->customer_id != $request->user()->id ||
            $order->status === 'accepted'
        ) {
            return response()->json([
                'message' => 'لا يمكنك حذف هذا الطلب'
            ], 403);
        }

        $order->delete();

        return response()->json([
            'message' => 'تم حذف الطلب'
        ]);
    }
}