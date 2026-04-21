<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrowserFingerprint extends Model
{
    protected $fillable = [
        'fingerprint_hash',
        'fingerprint_data',
        'is_blocked',
        'user_count'
    ];

    protected $casts = [
        'fingerprint_data' => 'array',
        'is_blocked' => 'boolean'
    ];
}