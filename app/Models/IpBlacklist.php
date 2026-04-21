<?php

// IpBlacklist.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class IpBlacklist extends Model
{
    protected $fillable = ['ip_address'];
}