<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    /**
     * Get followed restaurants
     */
    public function following(Request $request)
    {
        $restaurants = $request->user()
            ->followedRestaurants()
            ->get();

        return response()->json([
            'restaurants' => $restaurants
        ]);
    }

    /**
     * Follow restaurant
     */
    public function follow(Restaurant $restaurant, Request $request)
    {
        $user = $request->user();

        $alreadyFollowing = $user->followedRestaurants()
            ->where('restaurant_id', $restaurant->id)
            ->exists();

        if (!$alreadyFollowing) {
            $user->followedRestaurants()->attach($restaurant->id);
        }

        return response()->json([
            'message' => 'تمت المتابعة',
            'followed' => true
        ]);
    }   

    /**
     * Unfollow restaurant
     */
    public function unfollow(Restaurant $restaurant, Request $request)
    {
        $request->user()
            ->followedRestaurants()
            ->detach($restaurant->id);

        return response()->json([
            'message' => 'تم إلغاء المتابعة',
            'followed' => false
        ]);
    }
}