<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء الحساب',
            'token' => $token,
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $validated['email'])->first();

        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'تم حظر المحاولات مؤقتاً'
            ], 429);
        }

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'message' => 'بيانات غير صحيحة'
            ], 401);
        }

        RateLimiter::clear($key);

        // ✅ FIX
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول',
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج'
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'تم حذف الحساب'
        ]);
    }
}