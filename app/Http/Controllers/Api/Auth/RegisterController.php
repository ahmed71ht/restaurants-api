<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

use App\Models\EmailVerification;
use App\Mail\VerifyEmailOtp;
use App\Services\DeviceFingerprintService;
use App\Services\Auth\AuthHelper;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $device = (new DeviceFingerprintService())->generateDeviceId($request);

        AuthHelper::rateLimit($request, $device['hash']);
        AuthHelper::checkVpn($request);

        $otpKey = 'otp:' . sha1($validated['email'] . $request->ip());

        if (RateLimiter::tooManyAttempts($otpKey, 3)) {
            throw ValidationException::withMessages(['error' => 'Too many OTP requests']);
        }

        RateLimiter::hit($otpKey, 120);

        $code = (string) random_int(100000, 999999);

        EmailVerification::where('email', $validated['email'])->delete();

        EmailVerification::create([
            'email' => $validated['email'],
            'code' => $code,
            'payload' => $validated,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0
        ]);

        Mail::to($validated['email'])->send(new VerifyEmailOtp($code));

        return response()->json([
            'type' => 'otp',
            'message' => 'Verification code sent'
        ]);
    }
}