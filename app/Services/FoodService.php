<?php

namespace App\Services;

use App\Models\Food;
use App\Models\Restaurant;

class FoodService
{
    public function create(Restaurant $restaurant, array $data)
    {
        $data['restaurant_id'] = $restaurant->id;
        return Food::create($data);
    }

    public function update(Food $food, array $data)
    {
        $food->update($data);
        return $food;
    }
}