<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Restaurant;

class RestaurantPolicy
{
    public function manage(User $user, Restaurant $restaurant)
    {
        return $user->role === 'admin' || $user->id === $restaurant->owner_id;
    }
}