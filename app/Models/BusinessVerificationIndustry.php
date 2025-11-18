<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessVerificationIndustry extends Model
{
    use HasFactory;

    protected $table = 'business_verification_industries';

    public $timestamps = false;

    protected $fillable = [
        'verification_id',
        'industry_key',
        'display_label',
        'selection_order',
    ];

    /** Relationships */

    public function verification()
    {
        return $this->belongsTo(BusinessVerification::class, 'verification_id');
    }
}
