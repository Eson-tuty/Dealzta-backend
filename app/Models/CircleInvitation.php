<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CircleInvitation extends Model
{
    use HasFactory;

    protected $table = 'circle_invitations';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'circle_id',
        'user_id',
        'status',
        'accepted_at',
        'declined_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
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
