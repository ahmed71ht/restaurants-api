<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Get profile
     */
    public function show(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    /**
     * Update profile
     */
    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $request->user()->id,
        ]);

        $user = $request->user();

        $oldEmail = $user->email;

        $user->name = $request->name;
        $user->email = $request->email;

        // إذا تغير الإيميل → يحتاج إعادة تحقق
        if ($oldEmail !== $request->email) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي',
            'user' => $user
        ]);
    }

    /**
     * Delete account
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // حذف التوكنات (Sanctum)
        $user->tokens()->delete();

        $user->delete();

        return response()->json([
            'message' => 'تم حذف الحساب بنجاح'
        ]);
    }
}