<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Restaurant::class => \App\Policies\RestaurantPolicy::class,
    ];

    /**
     * Bootstrap any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }

}
