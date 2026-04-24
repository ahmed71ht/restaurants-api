<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\FoodController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\UserOrdersController;
use App\Http\Controllers\Api\RestaurantCommentController;
use App\Http\Controllers\Api\CommentReplyController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    // register مع rate limit خفيف
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');

    // login حماية ضد brute force
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login')
        ->middleware('throttle:5,1');

    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::delete('/me', [AuthController::class, 'deleteAccount']);
    });
});

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/restaurants', [RestaurantController::class, 'index'])
    ->middleware('throttle:api');

Route::get('/restaurants/search', [RestaurantController::class, 'search'])
    ->middleware('throttle:api');

Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])
    ->middleware('throttle:api');

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    /*
    |-------------------------
    | PROFILE
    |-------------------------
    */
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);

    /*
    |-------------------------
    | FOLLOW
    |-------------------------
    */
    Route::get('/restaurants/following', [FollowController::class, 'following']);
    Route::post('/restaurants/{restaurant}/follow', [FollowController::class, 'follow']);
    Route::delete('/restaurants/{restaurant}/unfollow', [FollowController::class, 'unfollow']);

    /*
    |-------------------------
    | ORDERS (USER)
    |-------------------------
    */
    Route::get('/restaurants/{restaurant}/my-orders', [UserOrdersController::class, 'userOrders']);
    Route::get('/restaurants/{restaurant}/orders/{order}', [UserOrdersController::class, 'edit']);
    Route::put('/restaurants/{restaurant}/orders/{order}', [UserOrdersController::class, 'update']);
    Route::delete('/orders/{order}', [UserOrdersController::class, 'delete']);

    /*
    |-------------------------
    | BUY / CHECKOUT
    |-------------------------
    */
    Route::post('/foods/{food}/buy', [FoodController::class, 'buyStore'])
        ->middleware('throttle:10,1');

    // حماية قوية للـ checkout
    Route::post('/checkout', [FoodController::class, 'checkout'])
        ->middleware('throttle:3,1');

    /*
    |-------------------------
    | COMMENTS
    |-------------------------
    */
    Route::post('/comments', [RestaurantCommentController::class, 'store']);
    Route::put('/comments/{id}', [RestaurantCommentController::class, 'update']);
    Route::delete('/comments/{id}', [RestaurantCommentController::class, 'destroy']);

    Route::post('/comments/{comment}/replies', [CommentReplyController::class, 'store']);
    Route::put('/replies/{id}', [CommentReplyController::class, 'update']);
    Route::delete('/replies/{id}', [CommentReplyController::class, 'destroy']);

    /*
    |-------------------------
    | OWNER OR ADMIN
    |-------------------------
    */
    Route::middleware('ownerOrAdmin')->group(function () {

        Route::get('/restaurants/{restaurant}/orders', [OrderController::class, 'orders']);
        Route::put('/restaurants/{restaurant}/orders/{order}/accept', [OrderController::class, 'acceptOrder']);
        Route::put('/restaurants/{restaurant}/orders/{order}/reject', [OrderController::class, 'rejectOrder']);

        // 🔥 تم تصحيح المسار
        Route::delete('/admin/orders/rejected', [OrderController::class, 'deleteRejected']);

        Route::post('/restaurants/{restaurant}/foods', [FoodController::class, 'store']);
        Route::delete('/restaurants/{restaurant}/foods/{food}', [FoodController::class, 'destroy']);
        Route::put('/foods/{food}', [FoodController::class, 'update']);
    });

    /*
    |-------------------------
    | DELIVERY OR ADMIN
    |-------------------------
    */
    Route::middleware('deliveryOrAdmin')->group(function () {

        Route::get('/restaurants/{restaurant}/delivery', [DeliveryController::class, 'index']);
        Route::put('/restaurants/{restaurant}/delivery/{order}', [DeliveryController::class, 'updateStatus']);

        // 🔥 تم إزالة restaurant param لأنه غير مستخدم
        Route::delete('/delivery/delivered', [DeliveryController::class, 'deleteDelivered']);
    });

    /*
    |-------------------------
    | ADMIN ONLY
    |-------------------------
    */
    Route::prefix('admin')->middleware('admin')->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Restaurants
        Route::post('/restaurants', [RestaurantController::class, 'store']);
        Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
        Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy']);

        // Users
        Route::get('/users', [AdminController::class, 'users']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    });

});