<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Food;
use Illuminate\Http\Request;
use App\Services\FoodService;
use Illuminate\Support\Facades\Storage;

class FoodController extends Controller
{
    protected $foodService;

    public function __construct(FoodService $foodService)
    {
        $this->foodService = $foodService;
    }

    public function store(Request $request, Restaurant $restaurant)
    {
        $this->authorize('manage', $restaurant);

        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'image' => 'required|image',
            'price' => 'required|numeric',
        ]);

        $data = $request->only(['name', 'description', 'price']);

        $data['image'] = $request->file('image')->store('foods', 'public');

        $food = $this->foodService->create($restaurant, $data);

        return response()->json([
            'message' => 'تم إضافة الأكلة',
            'food' => $food
        ]);
    }

    public function update(Request $request, Food $food)
    {
        $this->authorize('manage', $food->restaurant);

        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric',
            'image' => 'nullable|image',
        ]);

        $data = $request->only(['name', 'description', 'price']);

        if ($request->hasFile('image')) {
            if ($food->image) {
                Storage::disk('public')->delete($food->image);
            }

            $data['image'] = $request->file('image')->store('foods', 'public');
        }

        $food = $this->foodService->update($food, $data);

        return response()->json([
            'message' => 'تم تحديث الأكلة',
            'food' => $food
        ]);
    }

    public function destroy(Restaurant $restaurant, Food $food)
    {
        $this->authorize('manage', $restaurant);

        // ✅ FIX مهم
        if ($food->restaurant_id !== $restaurant->id) {
            return response()->json(['message' => 'غير موجود'], 404);
        }

        if ($food->image) {
            Storage::disk('public')->delete($food->image);
        }

        $food->delete();

        return response()->json([
            'message' => 'تم حذف الأكلة'
        ]);
    }
}