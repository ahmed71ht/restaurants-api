<?php

namespace App\Services;

use App\Models\UserIpLog;
use App\Models\IpBlacklist;

class BehaviorService
{
    public function analyze($request, $fingerprintHash = null, $user = null)
    {
        $ip = $request->ip();
        $score = 0;

        $recent = UserIpLog::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recent >= 5) {
            $score += 30;
        }

        if ($user) {
            $deviceCount = UserIpLog::where('user_id', $user->id)
                ->distinct('fingerprint_hash')
                ->count('fingerprint_hash');

            if ($deviceCount > 4) {
                $score += 25;
            }
        }

        if (IpBlacklist::where('ip_address', $ip)->exists()) {
            $score += 50;
        }

        return min($score, 100);
    }
}