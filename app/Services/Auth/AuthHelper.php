<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\IpBlacklist;
use App\Models\UserIpLog;

class AuthHelper
{
    public static function rateLimit(Request $request, $deviceHash = null)
    {
        $key = 'auth:' . sha1($deviceHash . $request->ip() . sha1($request->userAgent() ?? 'na'));

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['error' => 'Too many attempts']);
        }

        RateLimiter::hit($key, 60);
    }

    public static function checkVpn(Request $request)
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

    public static function log($user, $request, $action, $fingerprint = null)
    {
        UserIpLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'action' => $action,
            'fingerprint_hash' => $fingerprint['hash'] ?? null,
        ]);
    }
}