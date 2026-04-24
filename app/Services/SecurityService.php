<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserIpLog;
use App\Models\IpBlacklist;

class SecurityService
{
    public function calculateRisk($request, $user = null)
    {
        $score = 0;
        $ip = $request->ip();

        if (IpBlacklist::where('ip_address', $ip)->exists()) {
            $score += 50;
        }

        $ipSpam = UserIpLog::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        $score += min($ipSpam * 8, 30);

        if ($user) {

            $deviceCount = UserIpLog::where('user_id', $user->id)
                ->distinct('fingerprint_hash')
                ->count('fingerprint_hash');

            if ($deviceCount > 3) {
                $score += 20;
            }

            $last = UserIpLog::where('user_id', $user->id)->latest()->first();

            if ($last && $last->user_agent !== $request->userAgent()) {
                $score += 15;
            }
        }

        return min($score, 100);
    }

    public function applyResult(User $user, $score)
    {
        $user->risk_score = $score;
        $user->is_suspicious = false;
        $user->is_blocked = false;

        if ($score >= 85) {
            $user->is_blocked = true;
        } elseif ($score >= 60) {
            $user->is_suspicious = true;
        }

        $user->save();
    }
}