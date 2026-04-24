<?php

namespace App\Services;

use App\Models\RiskState;
use Illuminate\Http\Request;

class RiskEngineService
{
    private function getKey(Request $request, $fingerprintHash = null)
    {
        $ipKey = 'ip:' . $request->ip();
        $fpKey = $fingerprintHash ? 'fp:' . $fingerprintHash : null;

        return [$ipKey, $fpKey];
    }

    private function getState($key)
    {
        return RiskState::firstOrCreate(
            ['key' => $key],
            [
                'risk_score' => 0,
                'failures' => 0,
                'successes' => 0
            ]
        );
    }

    // 🔥 تسجيل فشل (محاولة مشبوهة)
    public function addFailure(Request $request, $fingerprintHash = null)
    {
        foreach ($this->getKey($request, $fingerprintHash) as $key) {
            if (!$key) continue;

            $state = $this->getState($key);

            $state->failures += 1;
            $state->risk_score += 15;

            $state->last_seen = now();
            $state->save();
        }
    }

    // ✅ تسجيل نجاح (سلوك طبيعي)
    public function addSuccess(Request $request, $fingerprintHash = null)
    {
        foreach ($this->getKey($request, $fingerprintHash) as $key) {
            if (!$key) continue;

            $state = $this->getState($key);

            $state->successes += 1;

            // تقليل الخطورة تدريجيًا
            $state->risk_score -= 5;

            if ($state->risk_score < 0) {
                $state->risk_score = 0;
            }

            $state->last_seen = now();
            $state->save();
        }
    }

    // 🔥 جلب الخطورة الحالية
    public function getRisk(Request $request, $fingerprintHash = null)
    {
        $maxRisk = 0;

        foreach ($this->getKey($request, $fingerprintHash) as $key) {
            if (!$key) continue;

            $state = $this->getState($key);

            $maxRisk = max($maxRisk, $state->risk_score);
        }

        return min($maxRisk, 100);
    }

    public function decay($state)
    {
        if (!$state->last_seen) return;

        $hours = now()->diffInHours($state->last_seen);

        // كل 6 ساعات نزّل risk شوي
        $decaySteps = intdiv($hours, 6);

        if ($decaySteps > 0) {
            $state->risk_score -= $decaySteps * 5;

            if ($state->risk_score < 0) {
                $state->risk_score = 0;
            }

            $state->last_decay_at = now();
            $state->save();
        }
    }
}