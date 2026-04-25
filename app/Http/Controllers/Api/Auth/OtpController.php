<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;

use App\Models\User;
use App\Models\EmailVerification;
use App\Mail\VerifyEmailOtp;

class OtpController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required'
        ]);

        $record = EmailVerification::where('email', $request->email)
            ->latest()
            ->first();

        if (!$record) {
            throw ValidationException::withMessages(['error' => 'Code not found']);
        }

        // ❗ حماية عدد المحاولات
        if ($record->attempts >= 5) {
            $record->delete();

            throw ValidationException::withMessages([
                'error' => 'Too many attempts'
            ]);
        }

        if (now()->greaterThan($record->expires_at)) {
            $record->delete();
            throw ValidationException::withMessages(['error' => 'Code expired']);
        }

        $inputCode = preg_replace('/\s+/', '', (string) $request->code);

        if ($inputCode !== $record->code) {
            $record->increment('attempts');

            throw ValidationException::withMessages([
                'error' => 'Invalid code'
            ]);
        }

        $data = $record->payload;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $record->delete();

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'user'  => $user
        ]);
    }

    public function resend(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $record = EmailVerification::where('email', $request->email)->latest()->first();

        if (!$record) {
            throw ValidationException::withMessages(['error' => 'No OTP found']);
        }

        if ($record->updated_at->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages(['error' => 'Wait before retry']);
        }

        $code = (string) random_int(100000, 999999);

        $record->update([
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0
        ]);

        Mail::to($request->email)->send(new VerifyEmailOtp($code));

        return response()->json(['message' => 'OTP resent']);
    }
}