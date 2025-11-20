<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Builder;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'user_id';
   public $incrementing = true;

    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email_id',
        'phone_number',
        'password',
        'profile_photo',
        'bio',
        'user_interest',
        'location',
        'website',
        'occupation',
        'birthdate',
        'gender',
        'profile_visibility',
        'show_email_publicly',
        'show_phone_number',
        'show_location',
        'show_website',
        'show_occupation',
        'show_birth_date',
        'allow_direct_messages',
        'show_online_status',
        'share_location',
        'auto_update_location',
        'has_business_profile',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'show_email_publicly' => 'boolean',
        'show_phone_number' => 'boolean',
        'show_location' => 'boolean',
        'show_website' => 'boolean',
        'show_occupation' => 'boolean',
        'show_birth_date' => 'boolean',
        'allow_direct_messages' => 'boolean',
        'show_online_status' => 'boolean',
        'share_location' => 'boolean',
        'auto_update_location' => 'boolean',
        'has_business_profile' => 'boolean',
        'is_active' => 'boolean',
        'birthdate' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function hasVerifiedPhone()
    {
        return $this->phone_verified_at !== null;
    }

    public function hasVerifiedEmail()
    {
        return $this->email_verified_at !== null;
    }

    public function businessVerifications()
    {
        return $this->hasMany(\App\Models\BusinessVerification::class, 'user_id', 'user_id');
    }
    public function circlesCreated()
    {
        return $this->hasMany(Circle::class, 'created_by', 'user_id');
    }

    public function circleMemberships()
    {
        return $this->hasMany(CircleMember::class, 'user_id', 'user_id');
    }

    public function circleInvitations()
    {
        return $this->hasMany(CircleInvitation::class, 'user_id', 'user_id');
    }
}
