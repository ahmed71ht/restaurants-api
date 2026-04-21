<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserIpLog extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'action',
        'fingerprint_hash'
    ];
}