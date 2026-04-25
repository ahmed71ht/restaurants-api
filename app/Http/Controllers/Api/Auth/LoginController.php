<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

use App\Services\DeviceFingerprintService;
use App\Services\SecurityService;
use App\Services\BehaviorService;
use App\Services\RiskEngineService;
use App\Services\DecisionEngineService;
use App\Services\Auth\AuthHelper;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $device = (new DeviceFingerprintService())->generateDeviceId($request);

        AuthHelper::rateLimit($request, $device['hash']);
        AuthHelper::checkVpn($request);

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

        AuthHelper::log($user, $request, 'login', $device);

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => $user
        ]);
    }
}