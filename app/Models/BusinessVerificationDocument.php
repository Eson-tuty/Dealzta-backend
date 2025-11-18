<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessVerificationDocument extends Model
{
    use HasFactory;

    protected $table = 'business_verification_documents';

    public $timestamps = false; // using uploaded_at instead

    protected $fillable = [
        'verification_id',
        'user_id',
        'doc_type',
        'file_path',
        'file_name',
        'mime_type',
        'size',
        'status',
        'notes',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    /** Relationships */

    public function verification()
    {
        return $this->belongsTo(BusinessVerification::class, 'verification_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
