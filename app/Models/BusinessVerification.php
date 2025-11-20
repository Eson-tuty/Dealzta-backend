<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessVerification extends Model
{
    use HasFactory;

    protected $table = 'business_verifications';

    protected $fillable = [
        'user_id',
        'business_name',
        'business_description',
        'business_type',
        'business_country',
        'registration_number',
        'registration_date',
        'has_registration',
        'gst_verified',
        'owner_name',
        'owner_email',
        'phone_number',
        'alternative_phone',
        'website',
        'business_address',
        'location_address_line',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        'country',
        'annual_revenue',
        'number_of_employees',
        'years_in_business',
        'account_holder_name',
        'account_number',
        'ifsc_routing',
        'bank_name',
        'branch_name',
        'upi_id',
        'status',
        'terms_accepted',
        'meta',
        'industry_category',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'meta' => 'array',
        'has_registration' => 'boolean',
        'gst_verified' => 'boolean',
        'terms_accepted' => 'boolean',
    'industry_category' => 'array',
    ];

    /** Relationships */

    // User who created the verification
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // Industries selected (1..5)
    public function industries()
    {
        return $this->hasMany(BusinessVerificationIndustry::class, 'verification_id');
    }

    // Uploaded documents
    public function documents()
    {
        return $this->hasMany(BusinessVerificationDocument::class, 'verification_id');
    }

    // Status history
    public function auditLogs()
    {
        return $this->hasMany(BusinessVerificationAudit::class, 'verification_id');
    }
}
