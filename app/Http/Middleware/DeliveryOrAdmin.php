<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Restaurant;

class DeliveryOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Admin bypass
        if ($user->role === 'admin') {
            return $next($request);
        }

        $restaurant = $request->route('restaurant');

        if (is_numeric($restaurant)) {
            $restaurant = Restaurant::find($restaurant);
        }

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        // 🔥 FIX: حماية من null delivery_id
        if ((int)$user->id !== (int)($restaurant->delivery_id ?? 0)) {
            return response()->json([
                'message' => 'Forbidden - delivery only'
            ], 403);
        }

        return $next($request);
    }
}