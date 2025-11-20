<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $table = 'session';
    protected $primaryKey = 'session_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'location',
        'session_token',
        'refresh_token',
        'ip_address',
        'create_time',
        'expire_time',
    ];

    protected $casts = [
        'create_time' => 'datetime',
        'expire_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isExpired()
    {
        return $this->expire_time < now();
    }

    public function isValid()
    {
        return !$this->isExpired();
    }

    public function scopeValid($query)
    {
        return $query->where('expire_time', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expire_time', '<=', now());
    }
}