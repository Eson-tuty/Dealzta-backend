<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
     use HasFactory;

    protected $fillable = [
        'user_id',
        'media_type',
        'media_url',
        'title',
        'description',
        'views',
        'is_boosted',
        'boost_expiry',
        'expires_at'
    ];

    protected $casts = [
        'boost_expiry' => 'datetime',
        'expires_at'   => 'datetime',
        'is_boosted'   => 'boolean',
        'views'        => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
