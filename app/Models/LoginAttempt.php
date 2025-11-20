<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = [
        'email_or_phone',
        'ip_address',
        'success',
        'failure_reason',
        'user_agent',
    ];

    protected $casts = [
        'success' => 'boolean',
        'created_at' => 'datetime',
    ];

    // Disable updated_at since we only need created_at
    const UPDATED_AT = null;
}