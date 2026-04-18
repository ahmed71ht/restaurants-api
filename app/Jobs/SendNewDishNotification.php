<?php

namespace App\Jobs;

use App\Models\Food;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendNewDishNotification implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $food;

    public function __construct(Food $food)
    {
        $this->food = $food;
    }

    public function handle()
    {
        $restaurant = $this->food->restaurant;

        foreach ($restaurant->followers as $follower) {
            $follower->notify(new \App\Notifications\NewDishEmail($this->food));
        }
    }
}