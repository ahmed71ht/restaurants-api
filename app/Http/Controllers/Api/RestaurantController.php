<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RestaurantController extends Controller
{
    /**
     * Get all restaurants
     */
    public function index()
    {
        $restaurants = Cache::remember('restaurants_all', 60, function () {
            return Restaurant::with('owner')->get();
        });

        return response()->json([
            'restaurants' => $restaurants
        ]);
    }

    /**
     * Show restaurant
     */
    public function show(Restaurant $restaurant)
    {
        $data = Cache::remember("restaurant_{$restaurant->id}", 60, function () use ($restaurant) {
            return $restaurant->load(['foods', 'owner']);
        });

        return response()->json([
            'restaurant' => $data
        ]);
    }

    /**
     * Create restaurant
     */
    public function store(Request $request)
    {
        $request->validate([
            'delivery_id' => 'required|exists:users,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'image' => 'required|image',
            'phone' => 'required|string',
            'location' => 'required|string',
        ]);

        $data = $request->only([
            'delivery_id','name','description','phone','location'
        ]);

        $data['owner_id'] = $request->user()->id;

        // ✅ FIX
        $data['image'] = $request->file('image')->store('restaurants', 'public');

        $restaurant = Restaurant::create($data);

        \Cache::forget('restaurants_all');

        return response()->json([
            'message' => 'تم إنشاء المطعم',
            'restaurant' => $restaurant
        ]);
    }

    /**
     * Update restaurant
     */
    public function update(Request $request, Restaurant $restaurant)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string',
        ]);

        $restaurant->update($request->only('name', 'location'));

        Cache::forget("restaurant_{$restaurant->id}");
        Cache::forget("restaurants_all");
        
        return response()->json([
            'message' => 'تم تحديث المطعم',
            'restaurant' => $restaurant
        ]);
    }

    /**
     * Delete restaurant
     */
    public function destroy(Restaurant $restaurant)
    {
        if ($restaurant->image) {
            \Storage::disk('public')->delete($restaurant->image);
        }

        $restaurant->delete();

        \Cache::forget("restaurant_{$restaurant->id}");
        \Cache::forget("restaurants_all");

        return response()->json([
            'message' => 'تم حذف المطعم'
        ]);
    }

    /**
     * Search restaurants
     */
    public function search(Request $request)
    {
        $query = $request->input('query');

        $restaurants = Restaurant::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->get();

        return response()->json([
            'restaurants' => $restaurants
        ]);
    }

    /**
     * Create restaurant (create view removed)
     */
    public function create()
    {
        return response()->json([
            'users' => User::all()
        ]);
    }
}