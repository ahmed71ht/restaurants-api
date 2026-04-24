<?php

namespace App\Services;

class DecisionEngineService
{
    public function decide($securityScore, $behaviorScore, $adaptiveRisk)
    {
        $score = min(($securityScore * 0.5) + ($behaviorScore * 0.3) + ($adaptiveRisk * 0.2), 100);

        if ($score >= 85) {
            return [
                'action' => 'block',
                'score' => $score
            ];
        }

        if ($score >= 60) {
            return [
                'action' => 'challenge',
                'score' => $score
            ];
        }

        return [
            'action' => 'allow',
            'score' => $score
        ];
    }
}