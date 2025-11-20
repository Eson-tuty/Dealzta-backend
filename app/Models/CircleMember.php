<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CircleMember extends Model
{
    use HasFactory;

    protected $table = 'circle_members';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'circle_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function circle()
    {
        return $this->belongsTo(Circle::class, 'circle_id', 'circle_id');
    }
}
