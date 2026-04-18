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

        if ($user->role === 'admin') {
            return $next($request);
        }

        $restaurant = $request->route('restaurant');
        $food = $request->route('food');

        // handle food route safely
        if ($food && !$restaurant) {

            if ($food instanceof \App\Models\Food) {
                $restaurant = $food->restaurant;
            } else {
                $foodModel = \App\Models\Food::find($food);

                if (!$foodModel) {
                    return response()->json(['message' => 'Food not found'], 404);
                }

                $restaurant = $foodModel->restaurant;
            }
        }

        if (is_numeric($restaurant)) {
            $restaurant = \App\Models\Restaurant::find($restaurant);
        }

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        if ((int)$user->id !== (int)$restaurant->owner_id) {
            return response()->json(['message' => 'Forbidden - owner only'], 403);
        }

        return $next($request);
    }
}