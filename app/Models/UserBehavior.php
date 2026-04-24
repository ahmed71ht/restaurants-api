<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBehavior extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'fingerprint_hash',
        'login_count',
        'failed_logins',
        'last_login'
    ];

    protected $casts = [
        'last_login' => 'datetime',
    ];
}