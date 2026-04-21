<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\BrowserFingerprint;

class DeviceFingerprintService
{
    public function generateDeviceId(Request $request)
    {
        $fingerprintData = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'accept_language' => $request->header('Accept-Language')
        ];

        $hash = hash('sha256', json_encode($fingerprintData));

        return [
            'hash' => $hash,
            'data' => $fingerprintData
        ];
    }

    public function registerDeviceId($hash, $data, $userId)
    {
        $fingerprint = BrowserFingerprint::firstOrCreate(
            ['fingerprint_hash' => $hash],
            ['fingerprint_data' => $data]
        );

        $fingerprint->user_count += 1;
        $fingerprint->save();

        return $fingerprint;
    }
}