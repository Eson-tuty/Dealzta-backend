<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Circle extends Model
{
    use HasFactory;

    protected $primaryKey = 'circle_id';
    public $timestamps = true;

    protected $fillable = [
        'circle_name',
        'description',
        'profile_photo',
        'categories',
        'circle_type',
        'allow_join_request',
        'only_admin_can_post',
        'join_payment',
        'payment',
        'post_payment',
        'post_cost',
        'enable_sponsor_price',
        'sponsor_price',
        'created_by',
        'status',
        'invitations_sent',
        'invitations_accepted',
        'invitations_declined',
    ];

    protected $casts = [
        'categories' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** RELATIONSHIPS */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function members()
    {
        return $this->hasMany(CircleMember::class, 'circle_id', 'circle_id');
    }

    public function invitations()
    {
        return $this->hasMany(CircleInvitation::class, 'circle_id', 'circle_id');
    }
}
