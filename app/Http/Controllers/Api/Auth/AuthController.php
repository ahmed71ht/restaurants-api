<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;

use App\Models\IpBlacklist;
use App\Models\IpWhitelist;
use App\Models\Setting;
use App\Models\UserIpLog;
use App\Models\EmailVerification;

use App\Services\SecurityService;
use App\Services\DeviceFingerprintService;
use App\Services\BehaviorService;
use App\Services\RiskEngineService;
use App\Services\DecisionEngineService;

use App\Mail\VerifyEmailOtp;

class AuthController extends Controller
{
    private function log($user, $request, $action, $fingerprint = null)
    {
        UserIpLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'action' => $action,
            'fingerprint_hash' => $fingerprint['hash'] ?? null,
        ]);
    }

    private function rateLimit(Request $request, $deviceHash = null)
    {
        $key = 'auth:' . sha1($deviceHash . $request->ip() . sha1($request->userAgent() ?? 'na'));

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['error' => 'Too many attempts']);
        }

        RateLimiter::hit($key, 60);
    }

    private function checkVpn(Request $request)
    {
        $ip = $request->ip();
        $cacheKey = "vpn:$ip";

        if (cache()->has($cacheKey) && cache($cacheKey) === true) {
            throw ValidationException::withMessages(['error' => 'VPN detected']);
        }

        if (IpBlacklist::where('ip_address', $ip)->exists()) {
            throw ValidationException::withMessages(['error' => 'Access denied']);
        }

        $apiKey = env('IPHUB_API_KEY');
        if (!$apiKey) return;

        try {
            $res = Http::timeout(4)
                ->withHeaders(['X-Key' => $apiKey])
                ->get("http://v2.api.iphub.info/ip/$ip");

            if (!$res->successful()) {
                cache()->put($cacheKey, false, now()->addHour());
                return;
            }

            $data = $res->json();

            $score = 0;
            if (($data['block'] ?? 0) == 1) $score += 50;
            if (($data['proxy'] ?? 0) == 1) $score += 40;

            $org = strtolower($data['org'] ?? '');
            if (str_contains($org, 'vpn')) $score += 30;
            if (str_contains($org, 'hosting')) $score += 20;
            if (str_contains($org, 'datacenter')) $score += 20;

            $isVpn = $score >= 50;

            cache()->put($cacheKey, $isVpn, now()->addHours(6));

            if ($isVpn) {
                IpBlacklist::firstOrCreate(['ip_address' => $ip]);
                throw ValidationException::withMessages(['error' => 'VPN detected']);
            }

        } catch (\Throwable $e) {
            Log::warning($e->getMessage());
            cache()->put($cacheKey, false, now()->addHour());
        }
    }

    private function safeResponse($user = null)
    {
        if (!$user) {
            return response()->json(['message' => 'OK']);
        }

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => $user
        ]);
    }

    // ================= REGISTER =================
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $device = (new DeviceFingerprintService())->generateDeviceId($request);

        $this->rateLimit($request, $device['hash']);
        $this->checkVpn($request);

        // منع السبام
        $otpKey = 'otp:' . sha1($validated['email'] . $request->ip());
        if (RateLimiter::tooManyAttempts($otpKey, 3)) {
            throw ValidationException::withMessages(['error' => 'Too many OTP requests']);
        }

        RateLimiter::hit($otpKey, 120);

        $code = (string) random_int(100000, 999999);

        // نحذف القديم
        EmailVerification::where('email', $validated['email'])->delete();

        EmailVerification::create([
            'email' => $validated['email'],
            'code' => $code, // 🔥 بدون Hash
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

    // ================= VERIFY OTP =================
    public function verifyOtp(Request $request)
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

        if (now()->greaterThan($record->expires_at)) {
            $record->delete();
            throw ValidationException::withMessages(['error' => 'Code expired']);
        }

        // 🔥 تنظيف الكود
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
    // ================= RESEND OTP =================
    public function resendOtp(Request $request)
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

        return response()->json([
            'message' => 'OTP resent'
        ]);
    }

    // ================= LOGIN =================
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $device = (new DeviceFingerprintService())->generateDeviceId($request);

        $this->rateLimit($request, $device['hash']);
        $this->checkVpn($request);

        if (!Auth::attempt($validated)) {
            (new RiskEngineService())->addFailure($request, $device['hash']);
            throw ValidationException::withMessages(['error' => 'Invalid credentials']);
        }

        $user = Auth::user();

        $behaviorScore = (new BehaviorService())->analyze($request, $device['hash'], $user);
        $adaptiveRisk = (new RiskEngineService())->getRisk($request, $device['hash']);
        $baseScore = (new SecurityService())->calculateRisk($request, $user);

        $decision = (new DecisionEngineService())
            ->decide($baseScore, $behaviorScore, $adaptiveRisk);

        if ($decision['action'] === 'block') {
            Auth::logout();
            (new RiskEngineService())->addFailure($request, $device['hash']);
            throw ValidationException::withMessages(['error' => 'Blocked']);
        }

        if ($decision['action'] === 'captcha') {
            return response()->json(['type' => 'captcha']);
        }

        if ($decision['action'] === 'otp') {
            return response()->json(['type' => 'otp']);
        }

        (new SecurityService())->applyResult($user, $decision['score']);
        (new RiskEngineService())->addSuccess($request, $device['hash']);

        $this->log($user, $request, 'login', $device);

        return $this->safeResponse($user);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}