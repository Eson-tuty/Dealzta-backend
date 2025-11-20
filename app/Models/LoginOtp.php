<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LoginOtp extends Model
{
    protected $table = 'login_otps';
    
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'contact',
        'otp_code',
        'otp_type',
        'expires_at',
        'ip_address',
        'attempt_count',
        'max_attempts',
        'attempts_started_at',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'attempts_started_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * ✅ Increment failed attempts and set attempts_started_at on first failure
     */
    public function incrementAttempts()
    {
        // ✅ Set attempts_started_at on FIRST failed attempt
        if ($this->attempt_count === 0 && $this->attempts_started_at === null) {
            $this->attempts_started_at = Carbon::now();
        }

        $this->attempt_count++;
        $this->save();
    }

    /**
     * Check if max attempts have been exceeded
     */
    public function isMaxAttemptsExceeded()
    {
        return $this->attempt_count >= $this->max_attempts;
    }

    /**
     * Check if OTP has expired
     */
    public function isExpired()
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    /**
     * Check if 24 hours have passed since first failed attempt
     */
    public function shouldResetAttempts()
    {
        if (!$this->attempts_started_at) {
            return false;
        }

        return Carbon::now()->diffInHours($this->attempts_started_at) >= 24;
    }

    /**
     * Reset attempt count and clear attempts_started_at
     */
    public function resetAttempts()
    {
        $this->attempt_count = 0;
        $this->attempts_started_at = null;
        $this->save();
    }

    /**
     * Mark OTP as verified
     */
    public function markAsVerified()
    {
        $this->is_verified = true;
        $this->verified_at = Carbon::now();
        $this->save();
    }

    /**
     * Get time until reset in human-readable format
     */
    public function getTimeUntilReset()
    {
        if (!$this->attempts_started_at) {
            return '24 hours';
        }

        $resetTime = $this->attempts_started_at->copy()->addHours(24);
        $now = Carbon::now();

        if ($now->isAfter($resetTime)) {
            return 'now';
        }

        $diff = $now->diffInMinutes($resetTime);
        
        if ($diff < 60) {
            return $diff . ' minute' . ($diff !== 1 ? 's' : '');
        }

        $hours = floor($diff / 60);
        $minutes = $diff % 60;

        if ($minutes === 0) {
            return $hours . ' hour' . ($hours !== 1 ? 's' : '');
        }

        return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' and ' . $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
    }

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}