<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use App\Models\BrowserFingerprint;
use App\Models\IpBlacklist;
use App\Models\IpWhitelist;
use App\Models\Setting;
use App\Services\DeviceFingerprintService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    protected function logUserIp($user, $request, $action, $fingerprintData = null)
    {
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        $fingerprintHash = $fingerprintData['hash'] ?? null;

        \App\Models\UserIpLog::create([
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'action' => $action,
            'fingerprint_hash' => $fingerprintHash,
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6'],
        ]);

        $deviceService = new DeviceFingerprintService();
        $fingerprint = $deviceService->generateDeviceId($request);
        $hash = $fingerprint['hash'];
        $data = $fingerprint['data'];

        // تحقق من max accounts per device
        $maxAccounts = Setting::where('key','max_accounts_per_device')->value('value') ?? 0;
        $existingDevice = BrowserFingerprint::where('fingerprint_hash', $hash)->first();

        if($existingDevice && $maxAccounts > 0 && $existingDevice->user_count >= $maxAccounts){
            throw ValidationException::withMessages([
                'error' => "تم الوصول للحد الأقصى للحسابات على هذا الجهاز."
            ]);
        }

        // التحقق من VPN / IP blacklist
        $ip = $request->ip();
        $vpnEnabled = Setting::where('key','vpn_detection_enabled')->value('value') == 1;

    if($vpnEnabled){
        // 1. تحقق القائمة السوداء أولاً
        if(IpBlacklist::where('ip_address', $ip)->exists()){
            throw ValidationException::withMessages([
                'error' => "تم حظر هذا IP."
            ]);
        }

        // 2. تحقق القائمة البيضاء
        if(IpWhitelist::where('ip_address', $ip)->exists()){
            $vpnDetected = false; // IP مو VPN
        } else {
            $apiKey = env('IPHUB_API_KEY');
            if(empty($apiKey)){
                $vpnDetected = false; // نتخطى VPN
            } else {
                try{
                    $response = Http::withHeaders([
                        'X-Key' => $apiKey
                    ])->get("http://v2.api.iphub.info/ip/".$ip);

                    $apiData = $response->json();

                    if(isset($apiData["block"]) && $apiData["block"] == 1){
                        // VPN مكتشف → أضف للقائمة السوداء
                        IpBlacklist::create(['ip_address' => $ip]);
                        throw ValidationException::withMessages([
                            'error' => "تم الكشف عن VPN/Proxy. التسجيل مرفوض."
                        ]);
                    } else {
                        // IP مو VPN → أضف للقائمة البيضاء
                        IpWhitelist::create(['ip_address' => $ip]);
                    }

                }catch(\Exception $e){
                    // خطأ بالـ API → نتخطى
                    Log::error("VPN API error: ".$e->getMessage());
                    $vpnDetected = false;
                }
            }
        }
    }

        // إنشاء المستخدم
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'browser_fingerprint' => $hash,
            'fingerprint_data' => $data
        ]);

        $this->logUserIp($user, $request, 'register', $fingerprint);

        // تسجيل الجهاز
        $deviceService->registerDeviceId($hash, $data, $user->id);

        $token = $user->createToken($request->userAgent() ?? 'auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء الحساب',
            'token' => $token,
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string']
        ]);

        $key = 'login:' . $request->ip() . '|' . $validated['email'];

        // إذا حاول المستخدم أكثر من 5 مرات خلال دقيقة واحدة
        if(RateLimiter::tooManyAttempts($key, 5)){
            return response()->json([
                'message' => 'عدد محاولات الدخول كبير جدًا. حاول لاحقًا.'
            ], 429);
        }

        if(!Auth::attempt($validated)){
            RateLimiter::hit($key, 60); // hit لمدة 60 ثانية
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة'
            ], 401);
        }

        RateLimiter::clear($key); // إزالة عد المحاولات بعد تسجيل الدخول بنجاح

        $user = Auth::user();

        // تسجيل الدخول متعدد الأجهزة → إنشاء Token جديد لكل جهاز
        $token = $user->createToken($request->userAgent() ?? 'auth_token')->plainTextToken;

        // تسجيل IP
        $this->logUserIp($user, $request, 'login');

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    // تسجيل الخروج من الجهاز الحالي
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'تم تسجيل الخروج']);
    }

    // تسجيل الخروج من جميع الأجهزة
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج من جميع الأجهزة']);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // حذف جميع Tokens
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'تم حذف الحساب']);
    }
}