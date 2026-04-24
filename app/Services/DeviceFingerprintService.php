<?php

namespace App\Services;

use Illuminate\Http\Request;

class DeviceFingerprintService
{
    public function generateDeviceId(Request $request)
    {
        $ip = $request->ip();

        $data = [
            'ua' => $request->userAgent(),
            'lang' => $request->header('Accept-Language'),
            'platform' => $request->header('Sec-CH-UA-Platform') ?? 'unknown',
            'screen' => $request->header('X-Screen-Res') ?? 'unknown',
            'timezone' => $request->header('X-Timezone') ?? 'unknown',
            'encoding' => $request->header('Accept-Encoding'),
            'ip_subnet' => $this->subnet($ip),
        ];

        ksort($data);

        return [
            'hash' => hash('sha256', json_encode($data)),
            'data' => $data
        ];
    }

    private function subnet($ip)
    {
        return $ip ? substr($ip, 0, strrpos($ip, '.')) : null;
    }
}