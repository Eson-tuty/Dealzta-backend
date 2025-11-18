<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessVerificationAudit extends Model
{
    use HasFactory;

    protected $table = 'business_verification_audit';

    public $timestamps = false;

    protected $fillable = [
        'verification_id',
        'changed_by',
        'from_status',
        'to_status',
        'comment',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** Relationships */

    public function verification()
    {
        return $this->belongsTo(BusinessVerification::class, 'verification_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'changed_by', 'user_id');
    }
}
