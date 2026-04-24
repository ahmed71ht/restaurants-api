<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskState extends Model
{
    protected $fillable = [
        'key',
        'risk_score',
        'last_seen',
        'failures',
        'successes',
    ];
}