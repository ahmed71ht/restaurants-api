<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmailOtp;
use Illuminate\Support\Str;

class LogoutController extends Controller
{

// =============================>Logout<============================= \\
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    }

// =============================>DeleteAccount<============================= \\
    public function requestDeleteAccount(Request $request)
    {
        $user = $request->user();

        $cacheKey = 'delete_account_otp:' . $user->id . ':' . $request->ip();

        if (cache()->has($cacheKey)) {
            return response()->json([
                'error' => 'Please wait 60 seconds before requesting another code'
            ], 429);
        }

        $code = (string) random_int(100000, 999999);

        EmailVerification::where('email', $user->email)
            ->where('type', 'delete_account')
            ->delete();

        EmailVerification::create([
            'email' => $user->email,
            'code' => Hash::make($code),
            'payload' => ['user_id' => $user->id],
            'type' => 'delete_account',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0
        ]);

        Mail::to($user->email)->send(new VerifyEmailOtp($code));

        cache()->put($cacheKey, true, now()->addSeconds(60));

        return response()->json([
            'message' => 'Delete verification code sent'
        ]);
    }


    public function confirmDeleteAccount(Request $request)
    {
        $request->validate([
            'code' => 'required'
        ]);

        $user = $request->user();

        $record = EmailVerification::where('email', $user->email)
            ->where('type', 'delete_account')
            ->latest()
            ->first();

        if (!$record) {
            return response()->json(['error' => 'No request found'], 404);
        }

        if (now()->greaterThan($record->expires_at)) {
            $record->delete();
            return response()->json(['error' => 'Code expired'], 422);
        }

        // ❗ حماية عدد المحاولات
        if ($record->attempts >= 5) {
            $record->delete();

            return response()->json([
                'error' => 'Too many attempts'
            ], 429);
        }

        $inputCode = preg_replace('/\s+/', '', (string) $request->code);

        if (!Hash::check($inputCode, $record->code)) {
            $record->increment('attempts');

            return response()->json(['error' => 'Invalid code'], 422);
        }

        // 🔥 حذف نهائي
        $user->tokens()->delete();
        $user->delete();

        $record->delete();

        return response()->json([
            'message' => 'Account deleted successfully'
        ]);
    }
}