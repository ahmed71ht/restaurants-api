<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Restaurant;
use App\Models\Food;

class OwnerOrAdmin
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
        $food = $request->route('food');

        // 🔥 food route handling
        if ($food && !$restaurant) {
            if ($food instanceof Food) {
                $restaurant = $food->restaurant;
            } else {
                $foodModel = Food::find($food);
                $restaurant = $foodModel?->restaurant;
            }
        }

        if (is_numeric($restaurant)) {
            $restaurant = Restaurant::find($restaurant);
        }

        if (!$restaurant) {
            return response()->json([
                'message' => 'Restaurant not found'
            ], 404);
        }

        // 🔥 FIX: null-safe check
        if ((int)$user->id !== (int)($restaurant->owner_id ?? 0)) {
            return response()->json([
                'message' => 'Forbidden - owner only'
            ], 403);
        }

        return $next($request);
    }
}