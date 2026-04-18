<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Food;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function dashboard()
    {
        $restaurantsCount = Restaurant::count();
        $foodsCount = Food::count();

        $latestRestaurants = Restaurant::latest()->take(10)->get();
        $latestFoods = Food::with('restaurant')->latest()->take(10)->get();

        $foodsPerRestaurantRaw = DB::table('foods')
            ->select('restaurant_id', DB::raw('count(*) as total'))
            ->groupBy('restaurant_id')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        $restaurants = Restaurant::whereIn(
            'id',
            $foodsPerRestaurantRaw->pluck('restaurant_id')
        )->get()->keyBy('id');

        $foodsPerRestaurant = $foodsPerRestaurantRaw->map(function ($row) use ($restaurants) {
            return [
                'restaurant_name' => $restaurants[$row->restaurant_id]->name ?? 'غير معروف',
                'total' => $row->total,
            ];
        });

        return response()->json([
            'restaurants_count' => $restaurantsCount,
            'foods_count' => $foodsCount,
            'latest_restaurants' => $latestRestaurants,
            'latest_foods' => $latestFoods,
            'foods_per_restaurant' => $foodsPerRestaurant,
        ]);
    }

    public function users()
    {
        return response()->json([
            'users' => User::paginate(20)
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'nullable|string',
            'password' => 'nullable|min:6',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        return response()->json([
            'message' => 'تم التحديث',
            'user' => $user
        ]);
    }
}